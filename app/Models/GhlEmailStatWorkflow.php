<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GhlEmailStatWorkflow extends Model
{
    protected $fillable = [
        'workflow_id',
        'source_id',
        'name',
        'status',
        'enabled',
        'sort_order',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
