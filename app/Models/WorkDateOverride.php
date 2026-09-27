<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkDateOverride extends Model
{
    protected $fillable = [
        'work_date',
        'is_work_day',
        'start_time',
        'end_time',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'is_work_day' => 'boolean',
        ];
    }
}
