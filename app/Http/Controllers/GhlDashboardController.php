<?php

namespace App\Http\Controllers;

use App\Services\GhlCampaignDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GhlDashboardController extends Controller
{
    public function __invoke(Request $request, GhlCampaignDashboard $campaignDashboard): View
    {
        $dateRange = [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];
        $dashboard = $campaignDashboard->build(dateRange: $dateRange);

        return view('dashboards.company-campaign-control', [
            'dashboard' => $dashboard,
            'dateRange' => $dateRange,
            'googleCalendar' => [
                'configured' => filled(config('services.google_calendar.client_id')) && filled(config('services.google_calendar.client_secret')),
                'connected' => (bool) $request->session()->get('google_calendar.connected', false),
                'expires_at' => $request->session()->get('google_calendar.expires_at'),
                'account' => $request->session()->get('google_calendar.account', []),
            ],
        ]);
    }
}
