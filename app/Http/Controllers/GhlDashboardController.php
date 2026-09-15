<?php

namespace App\Http\Controllers;

use App\Services\CompanyCampaignControlViewData;
use App\Services\GhlCampaignDashboard;
use App\Services\GhlSyncStatus;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GhlDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        GhlCampaignDashboard $campaignDashboard,
        GoogleCalendarService $googleCalendar,
        CompanyCampaignControlViewData $viewData,
        GhlSyncStatus $syncStatus,
    ): View {
        $dateRange = [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];
        $dashboard = $campaignDashboard->build(dateRange: $dateRange);

        return view('dashboards.company-campaign-control', $viewData->make(
            dashboard: $dashboard,
            dateRange: $dateRange,
            googleCalendar: $googleCalendar->dashboardState($request),
            syncStatus: $syncStatus->summary(),
        ));
    }
}
