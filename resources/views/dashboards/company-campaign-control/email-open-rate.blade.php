@php
    $emailStats = $emailStats ?? [];
    $emailTotals = $emailStats['totals'] ?? [];
    $emailBreakdown = collect($emailStats['campaign_breakdown'] ?? []);
    $metricOptions = [
        'open_rate' => [
            'label' => 'Open Rate',
            'unit' => '%',
            'value' => (float) ($emailStats['open_rate'] ?? 0),
            'breakdown_key' => 'open_rate',
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
            'breakdown_key' => 'unsubscribed',
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
            'breakdown_key' => 'click_rate',
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
            'breakdown_key' => 'delivered',
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
            'breakdown_key' => 'spam_complaints',
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
    $metricBaseQuery = request()->except(['email_metric', 'email_from', 'email_to']);
    $workflowOptions = collect($emailStats['workflow_options'] ?? []);
    $selectedWorkflowCount = (int) ($emailStats['selected_workflows_count'] ?? $workflowOptions->where('enabled', true)->count());
    $chartBreakdown = $emailBreakdown
        ->filter(fn (array $item): bool => (float) ($item[$activeMetric['breakdown_key']] ?? 0) > 0 || (int) ($item['delivered'] ?? 0) > 0)
        ->values();
    $chartMax = max((float) $activeMetric['tick_min'], (float) $chartBreakdown->max($activeMetric['breakdown_key']), 1);
    $chartRows = $chartBreakdown
        ->sortByDesc(fn (array $item): float => (float) ($item[$activeMetric['breakdown_key']] ?? 0))
        ->values();
@endphp

@include('dashboards.company-campaign-control.email-open-rate-content')
