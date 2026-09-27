<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kasbon extends Model
{
    public const PENDING = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const CANCELLED = 'CANCELLED';
    public const PAID = 'PAID';

    protected $fillable = [
        'employee_id',
        'amount',
        'note',
        'deduct_period',
        'status',
        'approved_at',
        'paid_in_period',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}