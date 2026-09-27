<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'employee_id',
        'name',
        'position',
        'employment_type',
        'contract_start',
        'contract_end',
        'shift_id',
        'base_salary',
        'status',
        'hire_date',
        'email',
        'phone',
        'phone_wa',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date:Y-m-d',
            'contract_start' => 'date:Y-m-d',
            'contract_end' => 'date:Y-m-d',
            'base_salary' => 'decimal:2',
        ];
    }

    public const TYPES = [
        'KARTAP' => 'Karyawan Tetap (Kartap)',
        'KONTRAK' => 'Kontrak',
        'MAGANG' => 'Magang',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function rfidCard(): HasOne
    {
        return $this->hasOne(RfidCard::class)->where('is_active', true);
    }

    public function allRfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function kasbons(): HasMany
    {
        return $this->hasMany(Kasbon::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'Aktif';
    }

    public function employmentTypeLabel(): ?string
    {
        return self::TYPES[$this->employment_type] ?? $this->employment_type;
    }

    public function shiftLabel(): string
    {
        if (! $this->shift) {
            return 'Default (global)';
        }

        return $this->shift->name.' ('.$this->shift->start_time.'-'.$this->shift->end_time.')';
    }
}
