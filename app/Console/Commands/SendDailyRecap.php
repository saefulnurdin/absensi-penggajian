<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyRecap extends Command
{
    protected $signature = 'wa:recap {--date= : Tanggal rekap (Y-m-d) untuk uji manual, default hari ini}';

    protected $description = 'Kirim rekap kehadiran harian ke nomor WhatsApp admin';

    public function handle(AttendanceService $attendance): int
    {
        $recapTime = Setting::get(Setting::WA_RECAP_TIME);

        if ($this->option('date') === null && $recapTime !== '' && now()->format('H:i') !== $recapTime) {
            $this->line('Bukan jam kirim rekap ('.$recapTime.'), dilewati.');

            return self::SUCCESS;
        }

        $adminPhone = Setting::get(Setting::WA_ADMIN_PHONE);

        if (blank($adminPhone)) {
            $this->warn('Nomor admin WhatsApp belum diatur di Pengaturan.');

            return self::FAILURE;
        }

        $date = $this->option('date') !== null
            ? Carbon::parse($this->option('date'))
            : today();

        $hadir = 0;
        $terlambat = 0;
        $izin = 0;
        $tidakHadir = 0;

        foreach (Employee::where('status', 'Aktif')->get() as $employee) {
            if (! $attendance->scheduleFor($employee, $date)['work_day']) {
                continue;
            }

            $row = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date->toDateString())
                ->first();

            if ($row && $row->status === Attendance::IZIN) {
                $izin++;
            } elseif ($row && $row->time_in !== null) {
                if ($row->status === Attendance::TERLAMBAT) {
                    $terlambat++;
                }
                $hadir++;
            } else {
                $tidakHadir++;
            }
        }

        $text = 'REKAP KEHADIRAN '.$date->format('Y-m-d').PHP_EOL
            .'Hadir : '.$hadir.PHP_EOL
            .'Terlambat : '.$terlambat.PHP_EOL
            .'Izin : '.$izin.PHP_EOL
            .'Tidak Hadir : '.$tidakHadir.PHP_EOL
            .'—'.PHP_EOL
            .'Sistem Absensi RFID';

        SendWhatsAppMessage::dispatch($adminPhone, $text, 'recap-harian');

        $this->info('Rekap harian '.$date->toDateString().' dikirim ke '.$adminPhone.'.');

        return self::SUCCESS;
    }
}