<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const COMPANY_NAME = 'company_name';

    public const WORK_START = 'work_start';

    public const WORK_END = 'work_end';

    public const BREAK_START = 'break_start';

    public const BREAK_END = 'break_end';

    public const LATE_TOLERANCE = 'late_tolerance_minutes';

    public const WA_ADMIN_PHONE = 'wa_admin_phone';

    public const WA_RECAP_TIME = 'wa_recap_time';

    public const DEFAULTS = [
        self::COMPANY_NAME => 'PT Prototype Absensi RFID',
        self::WORK_START => '08:00',
        self::WORK_END => '17:00',
        self::BREAK_START => '12:00',
        self::BREAK_END => '13:00',
        self::LATE_TOLERANCE => '15',
        self::WA_ADMIN_PHONE => '',
        self::WA_RECAP_TIME => '17:00',
    ];

    public static function get(string $key): string
    {
        return (string) self::cached()->get($key)
            ?? (self::DEFAULTS[$key] ?? '');
    }

    public static function cached(): Collection
    {
        // Cache hanya array (bukan objek Collection) karena Laravel 13
        // menonaktifkan unserialize kelas dari cache secara default.
        $items = Cache::remember('app_settings', 3600, function () {
            return self::pluck('value', 'key')->all();
        });

        return collect($items);
    }

    public static function forgetCache(): void
    {
        Cache::forget('app_settings');
    }

    public static function workStart(): string
    {
        return self::get(self::WORK_START);
    }

    public static function workEnd(): string
    {
        return self::get(self::WORK_END);
    }

    public static function lateTolerance(): int
    {
        return (int) self::get(self::LATE_TOLERANCE);
    }

    public static function isWorkDay(\DateTimeInterface|string $date): bool
    {
        $dayIndex = is_string($date) ? (int) date('w', strtotime($date)) : (int) $date->format('w');

        return Cache::rememberForever(config('app.cache_prefix', '').'work_day_'.$dayIndex, function () use ($dayIndex) {
            $workDay = WorkDay::where('day_index', $dayIndex)->first();

            // Default: Senin-Jumat aktif, Sabtu-Minggu libur.
            if (! $workDay) {
                return $dayIndex >= 1 && $dayIndex <= 5;
            }

            return (bool) $workDay->is_work_day;
        });
    }
}
