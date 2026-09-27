<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'period',
        'as_of_date',
        'work_days',
        'hadir_count',
        'late_count',
        'izin_count',
        'absent_count',
        'incomplete_count',
        'base_salary',
        'daily_salary',
        'deduction',
        'kasbon_deduction',
        'net_salary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'daily_salary' => 'decimal:2',
            'deduction' => 'decimal:2',
            'kasbon_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
