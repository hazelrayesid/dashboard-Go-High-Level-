<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Campaign Control</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f7fa] font-sans text-slate-950 antialiased">
    @php
        $groups = $dashboard['groups'];
        $totals = $dashboard['totals'];
        $hasPartialFailure = $dashboard['ok'] && $totals['failed_segments'] > 0;
        $groupTotals = collect($groups)->map(fn (array $segments): int => collect($segments)->sum('total'));
        $maxGroupTotal = max($groupTotals->max() ?? 1, 1);
        $sentTotal = ($groupTotals['Sent, has website'] ?? 0) + ($groupTotals['Sent, no website'] ?? 0);
        $reportTotal = $groupTotals['Report opened'] ?? 0;
        $remainingTotal = $groupTotals['Remaining'] ?? 0;
        $reportRate = $sentTotal > 0 ? round(($reportTotal / $sentTotal) * 100, 1) : 0;
        $allSegments = collect($groups)
            ->flatMap(fn (array $segments, string $groupName): array => collect($segments)
                ->map(fn (array $segment): array => $segment + ['group' => $groupName])
                ->all());
        $prioritySegments = $allSegments->sortByDesc('total')->take(5);
        $segmentKey = fn (string $tag): string => (string) str($tag)->lower()
            ->replace(['audit outreach - ', ' (top4 signup)', ' - ', ' '], ['', ' top4 signup', '-', '-']);
        $filterKey = function (string $groupName, string $tag) use ($segmentKey): string {
            $keys = [(string) str($groupName)->lower()->replace([',', ' '], ['', '-']), $segmentKey($tag)];

            foreach (['has website', 'no website', 'plain', 'styled', 'top4 signup', 'report generated'] as $match) {
                if (str_contains($tag, $match)) {
                    $keys[] = str($match)->replace(' ', '-')->toString();
                }
            }

            if (in_array($tag, ['top4 signup', 'crazy domains'], true)) {
                $keys[] = 'remaining';
            }

            return collect($keys)->unique()->implode(' ');
        };
        $companyQueue = $allSegments
            ->map(fn (array $segment): array => array_merge($segment, [
                'filter_key' => $filterKey($segment['group'], $segment['tag']),
                'companies' => collect($segment['companies'])
                    ->map(fn (array $company): array => $company + [
                        'segment' => $segment['label'],
                        'tag' => $segment['tag'],
                        'group' => $segment['group'],
                    ])
                    ->all(),
            ]));
        $visibleCompanyCount = $companyQueue->sum(fn (array $segment): int => count($segment['companies']));
        $filterOptions = [
            ['label' => 'All', 'filter' => 'all'],
            ['label' => 'Has website', 'filter' => 'has-website'],
            ['label' => 'No website', 'filter' => 'no-website'],
            ['label' => 'Report generated', 'filter' => 'report-generated'],
            ['label' => 'Remaining', 'filter' => 'remaining'],
        ];
    @endphp

    <main data-dashboard-shell class="grid min-h-screen grid-cols-1 transition-[grid-template-columns] duration-200 xl:grid-cols-[288px_minmax(0,1fr)]">
        @include('dashboards.company-campaign-control.sidebar')

        <section class="min-w-0">
            @include('dashboards.company-campaign-control.header')

            <div class="grid gap-6 px-4 py-6 sm:px-6 lg:px-8">
                @include('dashboards.company-campaign-control.sync-alert')
                @include('dashboards.company-campaign-control.summary')
                @include('dashboards.company-campaign-control.segments')
                @include('dashboards.company-campaign-control.company-queue')
            </div>
        </section>
    </main>
</body>
</html>
