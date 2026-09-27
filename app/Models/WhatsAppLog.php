<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLog extends Model
{
    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'type',
        'recipient',
        'body_preview',
        'status',
        'related_type',
        'related_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'related_id');
    }
}