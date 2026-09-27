<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class NotifyMissingAttendance extends Command
{
    protected $signature = 'attendance:notify-missing';

    protected $description = 'Kirim email pemberitahuan tidak hadir untuk hari kerja yang belum memiliki catatan sama sekali.';

    public function handle(NotificationService $notifier): int
    {
        if (! Setting::isWorkDay(today())) {
            $this->info('Hari ini bukan hari kerja.');

            return self::SUCCESS;
        }

        $recorded = Attendance::where('attendance_date', today()->toDateString())->pluck('employee_id');

        $missing = Employee::where('status', 'Aktif')
            ->whereNotIn('id', $recorded)
            ->get();

        foreach ($missing as $employee) {
            $notifier->attendanceStatus($employee, 'tidak_hadir', [
                'date' => today()->toDateString(),
                'message' => 'Anda tidak memiliki catatan absensi pada hari kerja hari ini.',
            ]);
            $this->info("Email tidak hadir -> {$employee->name}");
        }

        if ($missing->isEmpty()) {
            $this->info('Semua pegawai aktif sudah tercatat hari ini.');
        }

        return self::SUCCESS;
    }
}
