<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidUnknown extends Model
{
    protected $fillable = [
        'uid',
        'ip_address',
        'first_seen_at',
        'last_seen_at',
        'hits',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'hits' => 'integer',
        ];
    }
}
