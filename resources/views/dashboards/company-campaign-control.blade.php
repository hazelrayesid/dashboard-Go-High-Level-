<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Campaign Control</title>
    <script>
        const theme = window.localStorage.getItem('companyDashboard.theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        document.documentElement.classList.toggle('dark', theme ? theme === 'dark' : prefersDark);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f7fa] font-sans text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main data-dashboard-shell class="grid min-h-screen grid-cols-1 transition-[grid-template-columns] duration-200 xl:grid-cols-[288px_minmax(0,1fr)]">
        @include('dashboards.company-campaign-control.sidebar')

        <section class="min-w-0">
            @include('dashboards.company-campaign-control.header')

            <div class="grid gap-6 px-4 py-6 sm:px-6 lg:px-8">
                @include('dashboards.company-campaign-control.sync-alert')
                @include('dashboards.company-campaign-control.summary')
                @include('dashboards.company-campaign-control.segments')
                @include('dashboards.company-campaign-control.google-calendar')
                @include('dashboards.company-campaign-control.company-queue')
            </div>
        </section>
    </main>
</body>
</html>
