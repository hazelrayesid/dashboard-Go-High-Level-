<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarOAuthTest extends TestCase
{
    public function test_google_calendar_connect_redirects_to_google_oauth(): void
    {
        config([
            'services.google_calendar.client_id' => 'google-client-id.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'http://localhost:8000/integrations/google-calendar/callback',
        ]);

        $response = $this->get(route('integrations.google-calendar.connect'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $response->assertRedirectContains('scope=openid%20email%20profile%20https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar.events.readonly');
        $this->assertNotEmpty(session('google_calendar_oauth_state'));
    }

    public function test_google_calendar_callback_exchanges_code_and_stores_session_token(): void
    {
        config([
            'services.google_calendar.client_id' => 'google-client-id.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'http://localhost:8000/integrations/google-calendar/callback',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'scope' => 'openid email profile https://www.googleapis.com/auth/calendar.events.readonly',
            ]),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'name' => 'Hazel Rayes',
                'email' => 'hazel@example.test',
                'picture' => 'https://example.test/avatar.jpg',
            ]),
        ]);

        $response = $this
            ->withSession(['google_calendar_oauth_state' => 'known-state'])
            ->get(route('integrations.google-calendar.callback', [
                'code' => 'oauth-code',
                'state' => 'known-state',
            ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(session('google_calendar.connected'));
        $this->assertNotSame('access-token', session('google_calendar.access_token'));
        $this->assertSame('hazel@example.test', session('google_calendar.account.email'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request->method() === 'POST'
            && $request->data()['grant_type'] === 'authorization_code'
            && $request->data()['code'] === 'oauth-code');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.googleapis.com/oauth2/v3/userinfo'
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_google_calendar_callback_rejects_invalid_state(): void
    {
        Http::preventStrayRequests();

        $response = $this
            ->withSession(['google_calendar_oauth_state' => 'known-state'])
            ->get(route('integrations.google-calendar.callback', [
                'code' => 'oauth-code',
                'state' => 'different-state',
            ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertNull(session('google_calendar'));
    }

    public function test_google_calendar_callback_handles_connection_failure(): void
    {
        config([
            'services.google_calendar.client_id' => 'google-client-id.apps.googleusercontent.com',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'http://localhost:8000/integrations/google-calendar/callback',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => fn () => throw new ConnectionException('SSL handshake timed out.'),
        ]);

        $response = $this
            ->withSession(['google_calendar_oauth_state' => 'known-state'])
            ->get(route('integrations.google-calendar.callback', [
                'code' => 'oauth-code',
                'state' => 'known-state',
            ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('google_calendar_error', 'Google Calendar could not be reached from this PHP installation.');
        $this->assertNull(session('google_calendar'));
    }
}
