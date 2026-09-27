<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\NotificationService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        Carbon::setTestNow('2026-09-07 08:00:00');
        parent::setUp();
        cache()->flush();
        config(['app.api_key' => 'test-api-key']);
        config(['mail.to_admin' => 'admin@test.local']);
    }

    public function test_attendance_checkin_logs_email(): void
    {
        $employee = Employee::first();
        $attendance = app(AttendanceService::class)->checkIn($employee, Carbon::now(), 'RFID');
        app(NotificationService::class)->attendanceStatus($employee, 'hadir', [
            'time' => $attendance->time_in,
            'late_minutes' => $attendance->late_minutes,
            'date' => $attendance->attendance_date->toDateString(),
        ]);

        $this->assertDatabaseHas('email_logs', [
            'type' => 'attendance:hadir',
            'recipient' => $employee->email,
            'status' => 'SENT',
        ]);
    }

    public function test_attendance_late_logs_email(): void
    {
        $employee = Employee::first();
        $attendance = app(AttendanceService::class)->checkIn($employee, Carbon::now()->setTime(8, 30), 'RFID');
        app(NotificationService::class)->attendanceStatus($employee, 'terlambat', [
            'time' => $attendance->time_in,
            'late_minutes' => $attendance->late_minutes,
            'date' => $attendance->attendance_date->toDateString(),
        ]);

        $this->assertDatabaseHas('email_logs', [
            'type' => 'attendance:terlambat',
            'recipient' => $employee->email,
        ]);
    }

    public function test_payslip_and_processed_email_logged(): void
    {
        $employee = Employee::first();

        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 9);
        app(NotificationService::class)->payslip($payroll, app(AttendanceService::class)->monthlyRecap($employee, 2026, 9));
        app(NotificationService::class)->payrollProcessed($payroll);

        $this->assertDatabaseHas('email_logs', [
            'type' => 'payslip',
            'recipient' => $employee->email,
            'related_type' => Payroll::class,
            'related_id' => $payroll->id,
            'status' => 'SENT',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'type' => 'payroll-processed',
            'recipient' => $employee->email,
            'related_id' => $payroll->id,
        ]);
    }

    public function test_unknown_card_admin_alert_is_throttled_once_per_day(): void
    {
        // Dua kartu asing pada hari yang sama -> hanya satu email alert.
        $this->postJson('/api/attendance', ['uid' => 'XX0001', 'mode' => 'auto'], ['X-API-KEY' => 'test-api-key']);
        $this->postJson('/api/attendance', ['uid' => 'XX0002', 'mode' => 'auto'], ['X-API-KEY' => 'test-api-key']);

        $this->assertSame(1, EmailLog::where('type', 'admin-alert')->count());
    }

    public function test_email_logs_page_shows_records(): void
    {
        $employee = Employee::first();
        $attendance = app(AttendanceService::class)->checkIn($employee, Carbon::now(), 'RFID');
        app(NotificationService::class)->attendanceStatus($employee, 'hadir', [
            'time' => $attendance->time_in,
            'late_minutes' => $attendance->late_minutes,
            'date' => $attendance->attendance_date->toDateString(),
        ]);

        $admin = User::where('email', 'admin@absensi.local')->firstOrFail();

        $this->actingAs($admin)->get('/email-logs')
            ->assertOk()
            ->assertSee('attendance:hadir', false);
    }
}
