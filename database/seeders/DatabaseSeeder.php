<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\DeviceLog;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkDay;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->delete();
        RfidCard::query()->delete();
        Attendance::query()->delete();
        Employee::query()->delete();
        WorkDay::query()->delete();
        Setting::query()->delete();
        DeviceLog::query()->delete();

        // --- Admin ---
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@absensi.local',
            'password' => 'admin1234',
            'is_admin' => true,
        ]);

        // --- Pengaturan default ---
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        // --- Hari kerja default: Senin-Jumat ---
        $names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        foreach (range(0, 6) as $i) {
            WorkDay::create([
                'day_index' => $i,
                'day_name' => $names[$i],
                'is_work_day' => $i >= 1 && $i <= 5,
            ]);
        }

        Cache::flush();

        // --- Pegawai contoh ---
        $examples = [
            ['PGW001', 'John Doe', 'Staff', 5000000, 'A1B2C3D4'],
            ['PGW002', 'Jane Doe', 'Staff', 4500000, 'E5F6A7B8'],
            ['PGW003', 'Budi Santoso', 'Supervisor', 7500000, 'C9D0E1F2'],
            ['PGW004', 'Andi Wijaya', 'Staff', 4800000, '3A4B5C6D'],
            ['PGW005', 'Siti Aminah', 'Staff', 4700000, '7E8F9A0B'],
        ];

        $service = app(AttendanceService::class);

        foreach ($examples as [$employeeId, $name, $position, $salary, $uid]) {
            $employee = Employee::create([
                'employee_id' => $employeeId,
                'name' => $name,
                'position' => $position,
                'base_salary' => $salary,
                'status' => 'Aktif',
                'hire_date' => now()->subYear(),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            ]);

            RfidCard::create([
                'employee_id' => $employee->id,
                'uid' => $uid,
                'is_active' => true,
                'note' => 'Kartu default '.$name,
            ]);

            // --- Contoh data absensi bulan berjalan (hari yang sudah lewat) ---
            $month = now()->month;
            $year = now()->year;
            $workDates = $service->workDates($year, $month);

            foreach ($workDates as $dateString) {
                $date = Carbon::parse($dateString);

                // Jangan menyemai hari ini/mendatang agar data test deterministik.
                if ($date->isToday() || ! $date->isPast()) {
                    continue;
                }

                $seed = mt_rand(0, 100);

                if ($seed < 80) {
                    // HADIR / TERLAMBAT
                    $minute = $seed < 68 ? mt_rand(0, 8) : mt_rand(9, 40);
                    $clock = $date->copy()->setTime(8, $minute, mt_rand(0, 59));

                    try {
                        $service->checkIn($employee, $clock, 'RFID');
                        $out = $date->copy()->setTime(17, mt_rand(0, 25), mt_rand(0, 59));
                        $row = Attendance::where('employee_id', $employee->id)
                            ->where('attendance_date', $dateString)->first();

                        if ($row) {
                            $row->update(['time_out' => $out->format('H:i:s')]);
                        }
                    } catch (\DomainException) {
                        // abaikan
                    }
                } elseif ($seed < 90) {
                    $service->takeLeave($employee, $date->copy()->setTime(8, 0, 0), 'RFID', 'Izin dari seeder');
                }
                // 10% sisanya: tidak hadir
            }
        }

        // --- Perangkat contoh ---
        DeviceLog::create([
            'device_name' => 'ESP32-Absensi-1',
            'ip_address' => '192.168.1.50',
            'last_seen_at' => now(),
            'online' => true,
        ]);
    }
}
