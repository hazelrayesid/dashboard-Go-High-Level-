<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    public function connect(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $authorizationUrl = $googleCalendar->authorizationUrl($request);

        if (blank($authorizationUrl)) {
            return redirect()
                ->route('dashboard')
                ->with('google_calendar_error', 'Google Calendar OAuth credentials are missing.');
        }

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $result = $googleCalendar->connectFromCallback($request);

        return redirect()->route('dashboard')->with(
            $result['ok'] ? 'google_calendar_status' : 'google_calendar_error',
            $result['message'],
        );
    }

    public function disconnect(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $googleCalendar->disconnect($request);

        return redirect()
            ->route('dashboard')
            ->with('google_calendar_status', 'Google Calendar logged out.');
    }
}
