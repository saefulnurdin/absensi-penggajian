<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    protected $fillable = [
        'device_name',
        'ip_address',
        'last_seen_at',
        'online',
        'power_source',
        'battery_percent',
        'wifi_rssi',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'online' => 'boolean',
            'battery_percent' => 'integer',
            'wifi_rssi' => 'integer',
        ];
    }
}
