<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendance';

    public const HADIR = 'HADIR';

    public const TERLAMBAT = 'TERLAMBAT';

    public const IZIN = 'IZIN';

    public const ABSENSI_TIDAK_LENGKAP = 'ABSENSI TIDAK LENGKAP';

    public const TIDAK_HADIR = 'TIDAK HADIR';

    public const ALL_STATUSES = [
        self::HADIR,
        self::TERLAMBAT,
        self::IZIN,
        self::ABSENSI_TIDAK_LENGKAP,
        self::TIDAK_HADIR,
    ];

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'time_in',
        'time_out',
        'status',
        'late_minutes',
        'source',
        'note',
        'edited_by',
        'edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'late_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::HADIR => 'badge-success',
            self::TERLAMBAT => 'badge-warning',
            self::IZIN => 'badge-info',
            self::TIDAK_HADIR => 'badge-danger',
            default => 'badge-muted',
        };
    }

    public static function statusBadgeClassFor(string $status): string
    {
        return match ($status) {
            self::HADIR => 'badge-success',
            self::TERLAMBAT => 'badge-warning',
            self::IZIN => 'badge-info',
            self::TIDAK_HADIR => 'badge-danger',
            default => 'badge-muted',
        };
    }
}
