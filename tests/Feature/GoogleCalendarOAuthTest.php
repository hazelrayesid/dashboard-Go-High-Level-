<?php

namespace Tests\Feature;

use App\Services\GoogleCalendarEventReader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarOAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

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

    public function test_google_calendar_hides_internal_top4_attendees(): void
    {
        $this->withoutVite();

        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    [
                        'id' => 'primary',
                        'summary' => 'Top4 Technology',
                        'primary' => true,
                    ],
                ],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event_123',
                        'summary' => 'Quick meeting and discussion with Chris Timmins',
                        'start' => ['dateTime' => '2026-09-01T11:00:00+07:00'],
                        'end' => ['dateTime' => '2026-09-01T11:30:00+07:00'],
                        'attendees' => [
                            ['displayName' => 'Michael Doyle', 'email' => 'michael@top4.com.au'],
                            ['email' => 'marketing@top4.com.au'],
                            ['email' => 'hello@top4.online'],
                            ['email' => 'funeral.directors1@outlook.com'],
                        ],
                    ],
                ],
            ]),
        ]);

        $state = app(GoogleCalendarEventReader::class)->upcoming(
            accessToken: 'access-token',
            month: '2026-09',
        );

        $this->assertSame(['funeral.directors1@outlook.com'], $state['events'][0]['attendees']);
        $this->assertSame('funeral.directors1@outlook.com', $state['events'][0]['attendees_title']);
        $this->assertSame('Calendar', $state['events'][0]['calendar']);
    }

    public function test_google_calendar_uses_dashboard_date_range_for_event_window(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Campaign calendar', 'primary' => true],
                ],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'event_456',
                        'summary' => 'Range meeting',
                        'start' => ['dateTime' => '2026-09-11T10:00:00+07:00'],
                        'end' => ['dateTime' => '2026-09-11T10:30:00+07:00'],
                    ],
                ],
            ]),
        ]);

        $state = app(GoogleCalendarEventReader::class)->upcoming(
            accessToken: 'access-token',
            month: '2026-09',
            dateRange: ['from' => '2026-09-10', 'to' => '2026-09-12'],
        );

        $this->assertSame('This range', $state['calendar_metric_label']);
        $this->assertSame('Sep 10, 2026 - Sep 12, 2026', $state['calendar_range_label']);
        $this->assertSame(['2026-09-11'], $state['event_dates']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/events')
            && str_starts_with($request->data()['timeMin'] ?? '', '2026-09-10T00:00:00')
            && str_starts_with($request->data()['timeMax'] ?? '', '2026-09-12T23:59:59'));
    }

    public function test_google_calendar_reuses_cached_month_events_when_switching_dates(): void
    {
        $eventRequests = 0;

        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Campaign calendar', 'primary' => true],
                ],
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => function () use (&$eventRequests) {
                $eventRequests++;

                return Http::response([
                    'items' => [
                        [
                            'id' => 'event_15',
                            'summary' => 'Cached date 15',
                            'start' => ['dateTime' => '2026-09-15T09:00:00+07:00'],
                            'end' => ['dateTime' => '2026-09-15T09:30:00+07:00'],
                        ],
                        [
                            'id' => 'event_16',
                            'summary' => 'Cached date 16',
                            'start' => ['dateTime' => '2026-09-16T10:00:00+07:00'],
                            'end' => ['dateTime' => '2026-09-16T10:30:00+07:00'],
                        ],
                    ],
                ]);
            },
        ]);

        $reader = app(GoogleCalendarEventReader::class);
        $firstState = $reader->upcoming(
            accessToken: 'access-token',
            month: '2026-09',
            date: '2026-09-16',
            cacheScope: 'account-a',
        );
        $secondState = $reader->upcoming(
            accessToken: 'access-token',
            month: '2026-09',
            date: '2026-09-15',
            cacheScope: 'account-a',
        );

        $this->assertSame('Cached date 16', $firstState['events'][0]['title']);
        $this->assertSame('Cached date 15', $secondState['events'][0]['title']);
        $this->assertSame(1, $eventRequests);
    }
}
