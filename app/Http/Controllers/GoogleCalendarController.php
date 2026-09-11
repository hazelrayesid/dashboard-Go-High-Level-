<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private const SCOPE = 'openid email profile https://www.googleapis.com/auth/calendar.events.readonly';

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->hasCredentials()) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar OAuth credentials are missing.');
        }

        $state = Str::random(40);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away(self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->forgetState($request)
                ->with('google_calendar_error', 'Google Calendar connection was cancelled.');
        }

        if (! $request->filled('code')) {
            return $this->forgetState($request)
                ->with('google_calendar_error', 'Google Calendar authorization code is missing.');
        }

        if (! hash_equals((string) $request->session()->pull('google_calendar_oauth_state'), (string) $request->query('state'))) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar connection could not be verified.');
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::TOKEN_URL, [
                    'client_id' => config('services.google_calendar.client_id'),
                    'client_secret' => config('services.google_calendar.client_secret'),
                    'code' => $request->query('code'),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.google_calendar.redirect_uri'),
                ]);
        } catch (ConnectionException) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar could not be reached from this PHP installation.');
        }

        if ($response->failed()) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar token exchange failed.');
        }

        $token = $response->json();
        if (blank($token['access_token'] ?? null)) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar did not return an access token.');
        }

        $account = $this->fetchAccount($token['access_token']);

        $request->session()->put('google_calendar', [
            'connected' => true,
            'access_token' => Crypt::encryptString($token['access_token']),
            'refresh_token' => filled($token['refresh_token'] ?? null) ? Crypt::encryptString($token['refresh_token']) : null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->timestamp,
            'scope' => $token['scope'] ?? self::SCOPE,
            'account' => $account,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('google_calendar_status', 'Google Calendar connected.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->session()->forget(['google_calendar', 'google_calendar_oauth_state']);

        return redirect()
            ->route('dashboard')
            ->with('google_calendar_status', 'Google Calendar logged out.');
    }

    private function hasCredentials(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && filled(config('services.google_calendar.redirect_uri'));
    }

    private function forgetState(Request $request): RedirectResponse
    {
        $request->session()->forget('google_calendar_oauth_state');

        return redirect()->route('dashboard');
    }

    private function fetchAccount(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get(self::USERINFO_URL);
        } catch (ConnectionException) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        return collect($response->json())
            ->only(['name', 'email', 'picture'])
            ->filter()
            ->all();
    }
}
