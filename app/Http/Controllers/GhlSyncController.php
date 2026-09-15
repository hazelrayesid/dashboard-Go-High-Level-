<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGhlContacts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GhlSyncController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        try {
            DB::table('jobs')
                ->where('queue', 'ghl-sync')
                ->delete();
        } catch (Throwable) {
            // Queue cleanup is best-effort; dispatch should still work in tests or limited environments.
        }

        Cache::forget('ghl:contacts-sync:cursor');

        SyncGhlContacts::dispatch(restart: true)->onQueue('ghl-sync');

        return redirect()
            ->route('dashboard')
            ->with('ghl_sync_status', 'HighLevel sync queued. The database will update in the background.');
    }
}
