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
        $dashboard = $campaignDashboard->build();

        return view('dashboards.company-campaign-control', [
            'dashboard' => $dashboard,
            'dateRange' => $dateRange,
        ]);
    }
}
