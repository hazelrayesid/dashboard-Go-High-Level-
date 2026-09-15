<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GhlContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ghl_contact_id',
        'name',
        'email',
        'phone',
        'business',
        'website',
        'created_at_ghl',
        'created_date',
        'tags',
        'custom_fields',
        'audit_report_url',
        'raw_payload',
        'synced_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at_ghl' => 'datetime',
            'created_date' => 'date',
            'tags' => 'array',
            'custom_fields' => 'array',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
