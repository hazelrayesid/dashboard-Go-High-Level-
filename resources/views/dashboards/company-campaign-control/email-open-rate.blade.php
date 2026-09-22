@php
    $emailStats = $emailStats ?? [];
    $emailTotals = $emailStats['totals'] ?? [];
    $emailPoints = collect($emailStats['points'] ?? []);
    $emailBreakdown = collect($emailStats['campaign_breakdown'] ?? []);
    $metricOptions = [
        'open_rate' => [
            'label' => 'Open Rate',
            'unit' => '%',
            'value' => (float) ($emailStats['open_rate'] ?? 0),
            'point_key' => 'open_rate',
            'tick_min' => 12,
            'rows' => [
                'Total Opened:' => $emailTotals['opened'] ?? 0,
                'Total Delivery:' => $emailTotals['delivered'] ?? 0,
            ],
        ],
        'unsubscribed' => [
            'label' => 'Unsubscribed',
            'unit' => '',
            'value' => (int) ($emailTotals['unsubscribed'] ?? 0),
            'point_key' => 'unsubscribed',
            'tick_min' => 5,
            'rows' => [
                'Total Unsubscribed:' => $emailTotals['unsubscribed'] ?? 0,
                'Total Delivery:' => $emailTotals['delivered'] ?? 0,
            ],
        ],
        'click_rate' => [
            'label' => 'Click Rate',
            'unit' => '%',
            'value' => ($emailTotals['delivered'] ?? 0) > 0 ? round((($emailTotals['clicked'] ?? 0) / $emailTotals['delivered']) * 100, 2) : 0,
            'point_key' => 'click_rate',
            'tick_min' => 5,
            'rows' => [
                'Total Clicked:' => $emailTotals['clicked'] ?? 0,
                'Total Delivery:' => $emailTotals['delivered'] ?? 0,
            ],
        ],
        'email_sent' => [
            'label' => 'Email sent',
            'unit' => '',
            'value' => (int) ($emailTotals['delivered'] ?? 0),
            'point_key' => 'delivered',
            'tick_min' => 5,
            'rows' => [
                'Total Delivered:' => $emailTotals['delivered'] ?? 0,
                'Total Delivery:' => $emailTotals['delivered'] ?? 0,
            ],
        ],
        'spam_complaints' => [
            'label' => 'Spam Complaints',
            'unit' => '',
            'value' => (int) ($emailTotals['spam_complaints'] ?? 0),
            'point_key' => 'spam_complaints',
            'tick_min' => 5,
            'rows' => [
                'Total Complaints:' => $emailTotals['spam_complaints'] ?? 0,
                'Total Delivery:' => $emailTotals['delivered'] ?? 0,
            ],
        ],
    ];
    $activeMetricKey = array_key_exists(request('email_metric'), $metricOptions) ? request('email_metric') : 'open_rate';
    $activeMetric = $metricOptions[$activeMetricKey];
    $isRateMetric = $activeMetric['unit'] === '%';
    $metricMax = max((float) $activeMetric['value'], (float) $emailPoints->max($activeMetric['point_key']), 0);
    $tickBase = max((float) $activeMetric['tick_min'], $metricMax);
    $tickMax = max((int) $activeMetric['tick_min'], (int) ceil($tickBase / 5) * 5);
    $chartTicks = collect(range(0, 5))->map(fn (int $tick): int => (int) round(($tickMax / 5) * $tick));
    $chartWidth = 900;
    $chartHeight = 220;
    $plotTop = 18;
    $plotBottom = 178;
    $plotLeft = 46;
    $plotRight = 884;
    $plotHeight = $plotBottom - $plotTop;
    $pointCount = max($emailPoints->count(), 1);
    $pathPoints = $emailPoints->values()->map(function (array $point, int $index) use ($pointCount, $plotLeft, $plotRight, $plotBottom, $plotHeight, $tickMax, $activeMetric): array {
        $x = $pointCount === 1 ? ($plotLeft + $plotRight) / 2 : $plotLeft + (($plotRight - $plotLeft) * ($index / ($pointCount - 1)));
        $y = $plotBottom - ($plotHeight * min((float) ($point[$activeMetric['point_key']] ?? 0), $tickMax) / $tickMax);

        return ['x' => round($x, 2), 'y' => round($y, 2)];
    });
    $linePath = '';

    if ($pathPoints->isNotEmpty()) {
        $pathValues = $pathPoints->values();
        $linePath = 'M '.$pathValues[0]['x'].' '.$pathValues[0]['y'];

        for ($index = 1; $index < $pathValues->count(); $index++) {
            $previous = $pathValues[$index - 1];
            $current = $pathValues[$index];
            $controlX = round(($previous['x'] + $current['x']) / 2, 2);

            $linePath .= ' C '.$controlX.' '.$previous['y'].' '.$controlX.' '.$current['y'].' '.$current['x'].' '.$current['y'];
        }
    }

    $areaPath = $pathPoints->isEmpty()
        ? ''
        : $linePath.' L '.$pathPoints->last()['x'].' '.$plotBottom.' L '.$pathPoints->first()['x'].' '.$plotBottom.' Z';
    $queryWithoutEmailRange = request()->except(['email_from', 'email_to']);
    $metricBaseQuery = request()->except(['email_metric']);
    $workflowOptions = collect($emailStats['workflow_options'] ?? []);
    $selectedWorkflowCount = (int) ($emailStats['selected_workflows_count'] ?? $workflowOptions->where('enabled', true)->count());
@endphp

@include('dashboards.company-campaign-control.email-open-rate-content')
