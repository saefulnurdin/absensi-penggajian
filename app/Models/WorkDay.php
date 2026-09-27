<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkDay extends Model
{
    protected $fillable = [
        'day_index',
        'day_name',
        'is_work_day',
    ];

    protected function casts(): array
    {
        return [
            'is_work_day' => 'boolean',
        ];
    }
}
