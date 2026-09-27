<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayrollWaTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['password' => 'admin1234', 'is_admin' => true]);
    }

    private function employeeWithWa(): Employee
    {
        $employee = Employee::create([
            'employee_id' => 'PGW-WA-SLIP',
            'name' => 'Pegawai Slip WA',
            'base_salary' => 5_000_000,
            'status' => 'Aktif',
            'email' => 'wa.slip@example.com',
            'phone_wa' => '085691406905',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-07',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'status' => Attendance::HADIR,
            'source' => 'RFID',
        ]);

        return $employee;
    }

    public function test_process_payroll_dispatches_whatsapp_payslip(): void
    {
        $user = $this->admin();
        $employee = $this->employeeWithWa();
        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 9, '2026-09-07');

        Queue::fake();

        $this->post('/login', ['email' => $user->email, 'password' => 'admin1234']);
        $this->post('/payroll/'.$payroll->id.'/process')->assertRedirect();

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) use ($payroll, $employee) {
            return $job->recipient === $employee->phone_wa
                && $job->type === 'slip-gaji'
                && $job->relatedType === Payroll::class
                && $job->relatedId === $payroll->id
                && str_contains($job->text, 'SLIP GAJI')
                && str_contains($job->text, 'Dihitung s.d. 2026-09-07');
        });
    }

    public function test_process_skips_whatsapp_when_phone_blank(): void
    {
        $user = $this->admin();
        $employee = Employee::create([
            'employee_id' => 'PGW-NO-WA',
            'name' => 'Tanpa Nomor',
            'base_salary' => 4_000_000,
            'status' => 'Aktif',
            'email' => 'nowa@example.com',
        ]);
        $payroll = app(PayrollService::class)->generateForEmployee($employee, 2026, 9);

        Queue::fake();

        $this->post('/login', ['email' => $user->email, 'password' => 'admin1234']);
        $this->post('/payroll/'.$payroll->id.'/process')->assertRedirect();

        Queue::assertPushed(SendWhatsAppMessage::class, function (SendWhatsAppMessage $job) {
            $this->assertNull($job->recipient);

            return true;
        });
    }

    public function test_send_many_dispatches_whatsapp_for_selected_payrolls(): void
    {
        $user = $this->admin();
        $a = $this->employeeWithWa();
        $b = Employee::create([
            'employee_id' => 'PGW-WA-SLIP2',
            'name' => 'Pegawai Dua',
            'base_salary' => 4_000_000,
            'status' => 'Aktif',
            'email' => 'b@example.com',
            'phone_wa' => '085691406906',
        ]);
        Attendance::create([
            'employee_id' => $b->id,
            'attendance_date' => '2026-09-07',
            'time_in' => '08:01:00',
            'time_out' => '17:00:00',
            'status' => Attendance::HADIR,
            'source' => 'RFID',
        ]);

        $payrollA = app(PayrollService::class)->generateForEmployee($a, 2026, 9);
        $payrollB = app(PayrollService::class)->generateForEmployee($b, 2026, 9);

        Queue::fake();

        $this->post('/login', ['email' => $user->email, 'password' => 'admin1234']);
        $this->post('/payroll/send-many', [
            'payroll_ids' => [$payrollA->id, $payrollB->id],
        ])->assertRedirect();

        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        $recipients = collect(Queue::pushed(SendWhatsAppMessage::class))->pluck('recipient')->sort()->values();
        $this->assertEquals(['085691406905', '085691406906'], $recipients->all());
    }
}