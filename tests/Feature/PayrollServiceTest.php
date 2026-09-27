<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kasbon;
use App\Models\Payroll;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        parent::setUp();
    }

    private function seededEmployee(): Employee
    {
        $employee = Employee::create([
            'employee_id' => 'PGW-PAY',
            'name' => 'Pegawai Payroll',
            'position' => 'Staff',
            'base_salary' => 5_000_000,
            'status' => 'Aktif',
            'hire_date' => now()->subYear(),
            'email' => 'payroll.test@example.com',
        ]);

        // Februari 2026: 20 hari kerja (Senin-Jumat).
        // 2 hadir lengkap, 1 izin -> 17 tidak hadir.
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-02-02',
            'time_in' => '08:00:11',
            'time_out' => '17:05:00',
            'status' => Attendance::HADIR,
            'late_minutes' => null,
            'source' => 'RFID',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-02-03',
            'time_in' => '08:00:31',
            'time_out' => '17:02:00',
            'status' => Attendance::HADIR,
            'late_minutes' => null,
            'source' => 'RFID',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-02-04',
            'status' => Attendance::IZIN,
            'source' => 'RFID',
        ]);

        return $employee;
    }

    public function test_payroll_calculation_for_february_2026(): void
    {
        $employee = $this->seededEmployee();

        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 2);

        $this->assertSame(20, $payroll->work_days);
        $this->assertSame(2, $payroll->hadir_count);
        $this->assertSame(0, $payroll->late_count);
        $this->assertSame(1, $payroll->izin_count);
        $this->assertSame(17, $payroll->absent_count);
        $this->assertSame(0, $payroll->incomplete_count);
        // 5.000.000 / 20 = 250.000
        $this->assertSame('250000.00', $payroll->daily_salary);
        // potongan = 17 x 250.000 = 4.250.000
        $this->assertSame('4250000.00', $payroll->deduction);
        $this->assertSame('750000.00', $payroll->net_salary);
        $this->assertSame('DRAFT', $payroll->status);
    }

    public function test_payroll_regeneration_does_not_duplicate(): void
    {
        $employee = $this->seededEmployee();
        $service = app(PayrollService::class);

        $service->generateForPeriod('2026-02');
        $service->generateForPeriod('2026-02');

        $this->assertSame(1, Payroll::where('employee_id', $employee->id)->where('period', '2026-02')->count());
    }

    public function test_payroll_regeneration_updates_totals(): void
    {
        $employee = $this->seededEmployee();
        $service = app(PayrollService::class);

        $first = $service->generateForEmployee($employee, 2026, 2);
        $this->assertSame(17, $first->absent_count);

        // Tambah kehadiran -> absen berkurang setelah regenerate.
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-02-05',
            'time_in' => '08:01:00',
            'time_out' => '17:00:00',
            'status' => Attendance::HADIR,
            'source' => 'RFID',
        ]);

        $second = $service->generateForEmployee($employee, 2026, 2);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(3, $second->hadir_count);
        $this->assertSame(16, $second->absent_count);
        $this->assertSame('1000000.00', $second->net_salary);
    }

    public function test_processed_payroll_keeps_status_after_regeneration(): void
    {
        $employee = $this->seededEmployee();
        $service = app(PayrollService::class);

        $payroll = $service->generateForEmployee($employee, 2026, 2);
        $payroll->update(['status' => 'DIPROSES']);

        $again = $service->generateForEmployee($employee, 2026, 2);

        $this->assertSame('DIPROSES', $again->status);
    }

    public function test_payroll_pro_rata_as_of_date(): void
    {
        $employee = $this->seededEmployee();

        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 2, '2026-02-03');

        // Pro-rata s.d. 3 Feb: hanya hari kerja 02 & 03 Feb yang dihitung.
        $this->assertSame(2, $payroll->work_days);
        $this->assertSame(2, $payroll->hadir_count);
        $this->assertSame(0, $payroll->absent_count);
        // 5.000.000 / 2 hari kerja = 2.500.000 per hari.
        $this->assertSame('2500000.00', $payroll->daily_salary);
        $this->assertSame('5000000.00', $payroll->net_salary);
        $this->assertSame('2026-02-03', $payroll->as_of_date);
    }

    public function test_payroll_regeneration_clears_as_of_date_when_full_month(): void
    {
        $employee = $this->seededEmployee();
        $service = app(PayrollService::class);

        $service->generateForEmployee($employee, 2026, 2, '2026-02-03');
        $fresh = $service->generateForEmployee($employee, 2026, 2, null);

        $this->assertNull($fresh->as_of_date);
        $this->assertSame(20, $fresh->work_days);
        $this->assertSame(17, $fresh->absent_count);
    }

    public function test_inactive_employees_excluded_from_generation(): void
    {
        $employee = $this->seededEmployee();
        $employee->update(['status' => 'Nonaktif']);

        $created = app(PayrollService::class)->generateForPeriod('2026-02');

        $this->assertFalse(collect($created)->contains(fn (Payroll $p) => $p->employee_id === $employee->id));
    }

    public function test_kasbon_is_deducted_and_marked_paid(): void
    {
        $employee = $this->seededEmployee();

        $kasbon = Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 300_000,
            'deduct_period' => '2026-02',
            'status' => Kasbon::APPROVED,
        ]);

        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 2);

        // net tanpa kasbon 750.000, dikurangi kasbon 300.000.
        $this->assertSame('300000.00', $payroll->kasbon_deduction);
        $this->assertSame('450000.00', $payroll->net_salary);

        $kasbon->refresh();
        $this->assertSame(Kasbon::PAID, $kasbon->status);
        $this->assertSame('2026-02', $kasbon->paid_in_period);
    }

    public function test_kasbon_regeneration_stays_consistent(): void
    {
        $employee = $this->seededEmployee();
        $service = app(PayrollService::class);

        Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 300_000,
            'deduct_period' => '2026-02',
            'status' => Kasbon::APPROVED,
        ]);

        $first = $service->generateForEmployee($employee, 2026, 2);
        $second = $service->generateForEmployee($employee, 2026, 2);

        $this->assertSame('450000.00', $first->net_salary);
        $this->assertSame('450000.00', $second->net_salary);
        $this->assertSame(1, Kasbon::count());
        $this->assertSame(Kasbon::PAID, Kasbon::first()->status);
    }

    public function test_only_approved_kasbon_for_target_period_is_deducted(): void
    {
        $employee = $this->seededEmployee();

        Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 500_000,
            'deduct_period' => '2026-02',
            'status' => Kasbon::PENDING,
        ]);
        Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 700_000,
            'deduct_period' => '2026-03',
            'status' => Kasbon::APPROVED,
        ]);

        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 2);

        $this->assertSame('0.00', $payroll->kasbon_deduction);
        $this->assertSame('750000.00', $payroll->net_salary);
    }
}
