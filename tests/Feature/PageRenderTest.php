<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@absensi.local')->firstOrFail();
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_admin_can_access_all_pages(): void
    {
        $admin = $this->admin();
        [$year, $month] = explode('-', now()->format('Y-m'));

        $urls = [
            '/' => 200,
            '/employees' => 200,
            '/employees/create' => 200,
            '/rfid-cards' => 200,
            '/work-days' => 200,
            '/shifts' => 200,
            '/settings' => 200,
            '/attendance' => 200,
            '/attendance/create' => 200,
            "/rekap?month={$month}&year={$year}" => 200,
            "/kalender?month={$month}&year={$year}" => 200,
            '/payroll' => 200,
            '/kasbon' => 200,
            '/devices' => 200,
            '/email-logs' => 200,
            '/whatsapp-logs' => 200,
        ];

        foreach ($urls as $url => $expected) {
            $this->actingAs($admin)->get($url)->assertStatus($expected);
        }
    }

    public function test_non_admin_blocked_from_admin_pages(): void
    {
        $user = User::create([
            'name' => 'Bukan Admin',
            'email' => 'bukan.admin@example.com',
            'password' => 'rahasia123',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get('/')->assertForbidden();
        $this->actingAs($user)->get('/employees')->assertForbidden();
        $this->actingAs($user)->get('/settings')->assertForbidden();
    }

    public function test_non_admin_cannot_login(): void
    {
        User::create([
            'name' => 'Bukan Admin',
            'email' => 'bukan.admin@example.com',
            'password' => 'rahasia123',
            'is_admin' => false,
        ]);

        $this->post('/login', [
            'email' => 'bukan.admin@example.com',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_employee_crud_pages_render(): void
    {
        $admin = $this->admin();
        $employee = Employee::firstOrFail();

        $this->actingAs($admin)->get("/employees/{$employee->id}/edit")->assertOk();
    }

    public function test_attendance_edit_page_renders(): void
    {
        $admin = $this->admin();
        $employee = Employee::firstOrFail();
        $attendance = Attendance::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($admin)->get("/attendance/{$attendance->id}/edit")->assertOk();
    }

    public function test_payroll_show_and_slip_page_render(): void
    {
        $admin = $this->admin();

        $payroll = app(PayrollService::class)->generateForEmployee(
            Employee::firstOrFail(),
            (int) now()->year,
            (int) now()->month,
        );

        $this->actingAs($admin)->get("/payroll/{$payroll->id}")->assertOk();
        $this->actingAs($admin)->get("/payroll/{$payroll->id}/slip")->assertOk();
    }
}
