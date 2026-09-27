<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\WorkDateOverride;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Employee $employee;

    protected function setUp(): void
    {
        Carbon::setTestNow('2026-09-07 08:00:00');
        parent::setUp();

        $this->employee = Employee::create([
            'employee_id' => 'PGW-TS1',
            'name' => 'Pegawai Test',
            'position' => 'Staff',
            'base_salary' => 5_000_000,
            'status' => 'Aktif',
            'hire_date' => now()->subYear(),
            'email' => 'test.pegawai@example.com',
        ]);
    }

    public function test_checkin_on_time_results_hadir(): void
    {
        $att = app(AttendanceService::class)->checkIn($this->employee, Carbon::now()->setTime(7, 58), 'RFID');

        $this->assertSame(Attendance::HADIR, $att->status);
        $this->assertSame('07:58:00', $att->time_in);
        $this->assertNull($att->late_minutes);
    }

    public function test_checkin_within_tolerance_is_hadir(): void
    {
        $att = app(AttendanceService::class)->checkIn($this->employee, Carbon::now()->setTime(8, 15), 'RFID');

        $this->assertSame(Attendance::HADIR, $att->status);
        $this->assertNull($att->late_minutes);
    }

    public function test_checkin_after_tolerance_is_terlambat(): void
    {
        $att = app(AttendanceService::class)->checkIn($this->employee, Carbon::now()->setTime(8, 20), 'RFID');

        $this->assertSame(Attendance::TERLAMBAT, $att->status);
        $this->assertSame(5, $att->late_minutes);
    }

    public function test_duplicate_checkin_rejected(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::now()->setTime(7, 58), 'RFID');

        $this->expectException(\DomainException::class);

        $service->checkIn($this->employee, Carbon::now()->setTime(8, 30), 'RFID');
    }

    public function test_checkout_requires_checkin(): void
    {
        $this->expectException(\DomainException::class);

        app(AttendanceService::class)->checkOut($this->employee, Carbon::now()->setTime(17, 0), 'RFID');
    }

    public function test_duplicate_checkout_rejected(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::now()->setTime(7, 58), 'RFID');
        $service->checkOut($this->employee, Carbon::now()->setTime(17, 0), 'RFID');

        $this->expectException(\DomainException::class);

        $service->checkOut($this->employee, Carbon::now()->setTime(17, 30), 'RFID');
    }

    public function test_take_leave_and_duplicate_blocked(): void
    {
        $service = app(AttendanceService::class);
        $att = $service->takeLeave($this->employee, Carbon::now(), 'RFID');

        $this->assertSame(Attendance::IZIN, $att->status);

        $this->expectException(\DomainException::class);

        $service->takeLeave($this->employee, Carbon::now(), 'RFID');
    }

    public function test_izin_blocks_checkin_same_day(): void
    {
        $service = app(AttendanceService::class);
        $service->takeLeave($this->employee, Carbon::now(), 'RFID');

        $this->expectException(\DomainException::class);

        $service->checkIn($this->employee, Carbon::now()->setTime(8, 30), 'RFID');
    }

    public function test_minutes_late_boundaries(): void
    {
        $service = app(AttendanceService::class);

        $this->assertSame(0, $service->minutesLate('07:59:00', '08:00', 15));
        $this->assertSame(0, $service->minutesLate('08:15:00', '08:00', 15));
        $this->assertSame(5, $service->minutesLate('08:20:00', '08:00', 15));
        $this->assertSame(0, $service->minutesLate('08:00:00', '08:00', 0));
    }

    public function test_minutes_late_overnight_shift(): void
    {
        $service = app(AttendanceService::class);

        // Shift 23:00-07:00, toleransi 15 -> batas tap 23:15 di hari shift mulai.
        $this->assertSame(0, $service->minutesLate('22:00:00', '23:00', 15, '07:00')); // awal sekali -> tidak terhitung
        $this->assertSame(0, $service->minutesLate('23:05:00', '23:00', 15, '07:00')); // dalam toleransi
        $this->assertSame(1, $service->minutesLate('23:16:00', '23:00', 15, '07:00')); // lewat 1 menit
        $this->assertSame(15, $service->minutesLate('23:30:00', '23:00', 15, '07:00')); // lewat 15 menit
        $this->assertSame(55, $service->minutesLate('00:10:00', '23:00', 15, '07:00')); // tengah malam -> 55 menit telat
        $this->assertSame(75, $service->minutesLate('00:30:00', '23:00', 15, '07:00')); // tengah malam -> 75 menit telat
    }

    public function test_work_dates_only_count_configured_workdays(): void
    {
        $dates = app(AttendanceService::class)->workDates(2026, 9);

        $this->assertCount(22, $dates);
        foreach ($dates as $date) {
            $day = (int) Carbon::parse($date)->format('w');
            $this->assertContains($day, [1, 2, 3, 4, 5]);
        }
    }

    public function test_monthly_recap_counts_hadir_terlambat_izin(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::create(2026, 9, 1, 7, 58), 'RFID');
        $service->checkOut($this->employee, Carbon::create(2026, 9, 1, 17, 0), 'RFID');
        $service->checkIn($this->employee, Carbon::create(2026, 9, 2, 8, 30), 'RFID');
        $service->checkOut($this->employee, Carbon::create(2026, 9, 2, 17, 0), 'RFID');
        $service->takeLeave($this->employee, Carbon::create(2026, 9, 3, 8, 0), 'RFID');

        $recap = $service->monthlyRecap($this->employee, 2026, 9);

        $this->assertCount(22, $recap['workDates']);
        $this->assertSame(2, $recap['hadir']);
        $this->assertSame(1, $recap['terlambat']);
        $this->assertSame(1, $recap['izin']);
        $this->assertSame(0, $recap['incomplete']);
        $this->assertSame(22 - 3, $recap['tidakHadir']);
    }

    public function test_monthly_recap_marks_incomplete_for_missing_checkout_on_past_date(): void
    {
        $service = app(AttendanceService::class);
        $service->checkIn($this->employee, Carbon::create(2026, 9, 1, 8, 0), 'RFID');

        $recap = $service->monthlyRecap($this->employee, 2026, 9);

        $this->assertSame(1, $recap['incomplete']);
        $this->assertSame(0, $recap['hadir']);

        $detail = collect($recap['details'])->firstWhere('date', '2026-09-01');
        $this->assertSame(Attendance::ABSENSI_TIDAK_LENGKAP, $detail['status']);
    }

    public function test_monthly_recap_excludes_weekend(): void
    {
        $recap = app(AttendanceService::class)->monthlyRecap($this->employee, 2026, 9);

        $this->assertCount(22, $recap['workDates']);
        $this->assertContains('2026-09-04', $recap['workDates']);
        $this->assertNotContains('2026-09-05', $recap['workDates']);
        $this->assertNotContains('2026-09-06', $recap['workDates']);
    }

    public function test_monthly_recap_applies_date_override(): void
    {
        WorkDateOverride::create([
            'work_date' => '2026-09-11',
            'is_work_day' => false,
            'label' => 'Libur nasional',
        ]);
        WorkDateOverride::create([
            'work_date' => '2026-09-12',
            'is_work_day' => true,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'label' => 'Kerja Sabtu',
        ]);

        $recap = app(AttendanceService::class)->monthlyRecap($this->employee, 2026, 9);

        $this->assertNotContains('2026-09-11', $recap['workDates']);
        $this->assertContains('2026-09-12', $recap['workDates']);
        $this->assertCount(22, $recap['workDates']);
    }

    public function test_schedule_precedence_override_shift_global(): void
    {
        $shift = Shift::create([
            'name' => 'Pagi',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 30,
        ]);
        $this->employee->update(['shift_id' => $shift->id]);

        WorkDateOverride::create([
            'work_date' => '2026-09-12',
            'is_work_day' => true,
            'start_time' => '08:00',
            'end_time' => '13:00',
            'label' => 'Kerja Sabtu',
        ]);
        WorkDateOverride::create([
            'work_date' => '2026-09-11',
            'is_work_day' => false,
            'label' => 'Libur nasional',
        ]);

        $service = app(AttendanceService::class);

        $withOverride = $service->scheduleFor($this->employee, Carbon::create(2026, 9, 12));
        $this->assertTrue($withOverride['work_day']);

        $holiday = $service->scheduleFor($this->employee, Carbon::create(2026, 9, 11));
        $this->assertFalse($holiday['work_day']);

        $this->employee->update(['shift_id' => null]);
        $this->employee->refresh();
        $default = $service->scheduleFor($this->employee, Carbon::create(2026, 9, 7));
        $this->assertTrue($default['work_day']);
        $this->assertSame('08:00', $default['start_time']);
        $this->assertSame(15, $default['tolerance']);
    }
}
