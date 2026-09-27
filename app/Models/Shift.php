<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
