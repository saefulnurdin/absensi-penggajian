<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'type',
        'recipient',
        'subject',
        'body_preview',
        'status',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'related_id' => 'integer',
        ];
    }
}
