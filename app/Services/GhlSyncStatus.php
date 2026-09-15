<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Support\Facades\DB;

class GhlSyncStatus
{
    /**
     * @return array{pending: int, failed: int, contacts: int, active_contacts: int}
     */
    public function summary(): array
    {
        return [
            'pending' => DB::table('jobs')->where('queue', 'ghl-sync')->count(),
            'failed' => DB::table('failed_jobs')->where('queue', 'ghl-sync')->count(),
            'contacts' => GhlContact::withTrashed()->count(),
            'active_contacts' => GhlContact::query()->count(),
        ];
    }
}
