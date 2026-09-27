<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendDailyRecapTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Bersihkan pegawai contoh dari suite berbagi DB agar rekap deterministik.
        Attendance::query()->delete();
        Employee::query()->delete();
    }

    private function configureRecap(string $phone = '085694112233', string $time = '17:00'): void
    {
        Setting::updateOrCreate(['key' => Setting::WA_ADMIN_PHONE], ['value' => $phone]);
        Setting::updateOrCreate(['key' => Setting::WA_RECAP_TIME], ['value' => $time]);
        Setting::forgetCache();
    }

    public function test_recap_command_dispatches_to_admin_phone(): void
    {
        Carbon::setTestNow('2026-09-07 17:00:00'); // Senin, jam rekap.
        $this->configureRecap();

        $employee = Employee::create([
            'employee_id' => 'PGW-WA',
            'name' => 'Pegawai WA',
            'base_salary' => 4_000_000,
            'status' => 'Aktif',
            'email' => 'wa.test@example.com',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-07',
            'time_in' => '08:02:00',
            'time_out' => '17:00:00',
            'status' => Attendance::HADIR,
            'source' => 'RFID',
        ]);

        Queue::fake();

        $this->artisan('wa:recap')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            return str_contains($job->recipient, '085694112233')
                && str_contains($job->text, 'REKAP KEHADIRAN 2026-09-07')
                && str_contains($job->text, 'Hadir : 1');
        });
    }

    public function test_recap_command_counts_absent_and_izin(): void
    {
        Carbon::setTestNow('2026-09-07 17:00:00');
        $this->configureRecap();

        $hadir = Employee::create([
            'employee_id' => 'PGW-A', 'name' => 'A', 'base_salary' => 1, 'status' => 'Aktif', 'email' => 'a@example.com',
        ]);
        Employee::create([
            'employee_id' => 'PGW-B', 'name' => 'B', 'base_salary' => 1, 'status' => 'Aktif', 'email' => 'b@example.com',
        ]);
        $izin = Employee::create([
            'employee_id' => 'PGW-C', 'name' => 'C', 'base_salary' => 1, 'status' => 'Aktif', 'email' => 'c@example.com',
        ]);

        Attendance::create([
            'employee_id' => $hadir->id, 'attendance_date' => '2026-09-07',
            'time_in' => '08:05:00', 'status' => Attendance::TERLAMBAT, 'source' => 'RFID',
        ]);
        Attendance::create([
            'employee_id' => $izin->id, 'attendance_date' => '2026-09-07',
            'status' => Attendance::IZIN, 'source' => 'RFID',
        ]);

        Queue::fake();

        $this->artisan('wa:recap')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            return str_contains($job->text, 'Hadir : 1')
                && str_contains($job->text, 'Terlambat : 1')
                && str_contains($job->text, 'Izin : 1')
                && str_contains($job->text, 'Tidak Hadir : 1');
        });
    }

    public function test_recap_command_skips_outside_recap_time(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00');
        $this->configureRecap();

        Queue::fake();

        $this->artisan('wa:recap')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}