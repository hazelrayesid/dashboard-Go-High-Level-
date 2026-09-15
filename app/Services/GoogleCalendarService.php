<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private const SCOPE = 'openid email profile https://www.googleapis.com/auth/calendar.events.readonly https://www.googleapis.com/auth/calendar.readonly';

    public function __construct(
        private readonly GoogleCalendarEventReader $eventReader,
        private readonly GoogleCalendarEmailMatcher $emailMatcher,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && filled(config('services.google_calendar.redirect_uri'));
    }

    public function authorizationUrl(Request $request): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $state = Str::random(40);
        $request->session()->put('google_calendar_oauth_state', $state);

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function connectFromCallback(Request $request): array
    {
        if ($request->filled('error')) {
            $request->session()->forget('google_calendar_oauth_state');

            return ['ok' => false, 'message' => 'Google Calendar connection was cancelled.'];
        }

        if (! $request->filled('code')) {
            $request->session()->forget('google_calendar_oauth_state');

            return ['ok' => false, 'message' => 'Google Calendar authorization code is missing.'];
        }

        if (! hash_equals((string) $request->session()->pull('google_calendar_oauth_state'), (string) $request->query('state'))) {
            return ['ok' => false, 'message' => 'Google Calendar connection could not be verified.'];
        }

        $token = $this->exchangeAuthorizationCode((string) $request->query('code'));

        if (($token['error'] ?? null) === 'connection') {
            return ['ok' => false, 'message' => 'Google Calendar could not be reached from this PHP installation.'];
        }

        if (($token['error'] ?? null) === 'exchange') {
            return ['ok' => false, 'message' => 'Google Calendar token exchange failed.'];
        }

        if (blank($token['access_token'] ?? null)) {
            return ['ok' => false, 'message' => 'Google Calendar did not return an access token.'];
        }

        $request->session()->put('google_calendar', [
            'connected' => true,
            'access_token' => Crypt::encryptString($token['access_token']),
            'refresh_token' => filled($token['refresh_token'] ?? null) ? Crypt::encryptString($token['refresh_token']) : null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->timestamp,
            'scope' => $token['scope'] ?? self::SCOPE,
            'account' => $this->fetchAccount($token['access_token']),
        ]);

        return ['ok' => true, 'message' => 'Google Calendar connected.'];
    }

    public function disconnect(Request $request): void
    {
        $request->session()->forget(['google_calendar', 'google_calendar_oauth_state']);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardState(Request $request): array
    {
        $state = [
            'configured' => $this->isConfigured(),
            'connected' => (bool) $request->session()->get('google_calendar.connected', false),
            'expires_at' => $request->session()->get('google_calendar.expires_at'),
            'account' => $request->session()->get('google_calendar.account', []),
            'events' => [],
            'events_status' => null,
        ];

        if (! $state['connected']) {
            return $state;
        }

        return array_merge($state, $this->upcomingEventsState($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function upcomingEventsState(Request $request): array
    {
        $accessToken = $this->validAccessToken($request);

        if (blank($accessToken)) {
            return ['events_status' => 'Google Calendar needs to be reconnected before events can be loaded.'];
        }

        $state = $this->eventReader->upcoming(
            accessToken: $accessToken,
            month: $request->query('calendar_month'),
            date: $request->query('calendar_date'),
            page: max((int) $request->query('calendar_page', 1), 1),
        );

        return $this->emailMatcher->match($state);
    }

    private function validAccessToken(Request $request): ?string
    {
        $expiresAt = (int) $request->session()->get('google_calendar.expires_at', 0);

        if ($expiresAt > now()->addMinute()->timestamp) {
            return $this->decryptSessionToken($request->session()->get('google_calendar.access_token'));
        }

        $refreshToken = $this->decryptSessionToken($request->session()->get('google_calendar.refresh_token'));

        if (blank($refreshToken)) {
            return null;
        }

        $token = $this->refreshAccessToken($refreshToken);

        if (blank($token['access_token'] ?? null)) {
            return null;
        }

        $googleCalendar = $request->session()->get('google_calendar', []);
        $googleCalendar['access_token'] = Crypt::encryptString($token['access_token']);
        $googleCalendar['expires_at'] = now()->addSeconds((int) ($token['expires_in'] ?? 3600))->timestamp;
        $request->session()->put('google_calendar', $googleCalendar);

        return $token['access_token'];
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCode(string $code): array
    {
        return $this->postToken([
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshAccessToken(string $refreshToken): array
    {
        return $this->postToken([
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postToken(array $payload): array
    {
        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, $payload);
        } catch (ConnectionException) {
            return ['error' => 'connection'];
        }

        return $response->failed() ? ['error' => 'exchange'] : $response->json();
    }

    private function decryptSessionToken(mixed $token): ?string
    {
        if (blank($token)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function fetchAccount(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->timeout(10)->get(self::USERINFO_URL);
        } catch (ConnectionException) {
            return [];
        }

        return $response->failed()
            ? []
            : collect($response->json())->only(['name', 'email', 'picture'])->filter()->all();
    }

}
