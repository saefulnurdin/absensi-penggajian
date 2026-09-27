<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@absensi.local')->firstOrFail();
    }

    public function test_shift_index_renders_and_stores_shift(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('Pengaturan Shift');

        $this->actingAs($admin)
            ->post(route('shifts.store'), [
                'name' => 'Pagi',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'grace_minutes' => 10,
            ])
            ->assertRedirect(route('shifts.index'));

        $this->assertDatabaseHas('shifts', [
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'grace_minutes' => 10,
            'is_active' => true,
        ]);
    }

    public function test_shift_store_allows_overnight_shift(): void
    {
        $this->actingAs($this->admin())
            ->post(route('shifts.store'), [
                'name' => 'Malam',
                'start_time' => '23:00',
                'end_time' => '07:00',
                'grace_minutes' => 15,
            ])
            ->assertRedirect(route('shifts.index'));

        $this->assertDatabaseHas('shifts', [
            'name' => 'Malam',
            'start_time' => '23:00',
            'end_time' => '07:00',
            'is_active' => true,
        ]);
    }

    public function test_shift_store_rejects_equal_times(): void
    {
        $this->actingAs($this->admin())
            ->post(route('shifts.store'), [
                'name' => 'Salah',
                'start_time' => '08:00',
                'end_time' => '08:00',
                'grace_minutes' => 10,
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_workday_override_store_and_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('work-days.overrides.store'), [
                'work_date' => '2026-08-17',
                'is_work_day' => '0',
                'label' => 'HUT RI',
            ])
            ->assertRedirect(route('work-days.index'));

        $this->assertDatabaseHas('work_date_overrides', [
            'work_date' => '2026-08-17',
            'is_work_day' => false,
            'label' => 'HUT RI',
        ]);

        $override = WorkDateOverride::where('work_date', '2026-08-17')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('work-days.overrides.destroy', $override))
            ->assertRedirect(route('work-days.index'));

        $this->assertDatabaseMissing('work_date_overrides', ['work_date' => '2026-08-17']);
    }

    public function test_employee_contract_range_required_for_kontrak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'employee_id' => 'PGW-K1',
                'name' => 'Pegawai Kontrak',
                'employment_type' => 'KONTRAK',
                'base_salary' => 5_000_000,
                'status' => 'Aktif',
            ])
            ->assertSessionHasErrors(['contract_start', 'contract_end']);
    }

    public function test_employee_kartap_without_contract_is_valid(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'employee_id' => 'PGW-K2',
                'name' => 'Pegawai Tetap',
                'employment_type' => 'KARTAP',
                'base_salary' => 5_000_000,
                'status' => 'Aktif',
                'hire_date' => '2024-01-01',
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'employee_id' => 'PGW-K2',
            'employment_type' => 'KARTAP',
        ]);
    }

    public function test_employee_can_be_assigned_shift(): void
    {
        $shift = Shift::create([
            'name' => 'Pagi',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 30,
        ]);

        $this->actingAs($this->admin())
            ->post(route('employees.store'), [
                'employee_id' => 'PGW-S1',
                'name' => 'Pegawai Shift',
                'employment_type' => 'MAGANG',
                'contract_start' => '2026-01-01',
                'contract_end' => '2026-12-31',
                'shift_id' => $shift->id,
                'base_salary' => 5_000_000,
                'status' => 'Aktif',
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'employee_id' => 'PGW-S1',
            'shift_id' => $shift->id,
            'employment_type' => 'MAGANG',
        ]);
    }

    public function test_shift_label_helper(): void
    {
        $shift = Shift::create([
            'name' => 'Pagi',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 30,
        ]);

        Employee::where('employee_id', 'PGW001')->update(['shift_id' => $shift->id]);

        $employee = Employee::where('employee_id', 'PGW001')->with('shift')->firstOrFail();

        $this->assertStringContainsString('Pagi', $employee->shiftLabel());
    }
}
