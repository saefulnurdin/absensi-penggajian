<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\RfidUnknown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidCardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@absensi.local')->firstOrFail();
    }

    public function test_store_card_with_uppercased_uid(): void
    {
        $employee = Employee::create([
            'employee_id' => 'PGW-RF',
            'name' => 'Pegawai Kartu',
            'position' => 'Staff',
            'base_salary' => 5_000_000,
            'status' => 'Aktif',
            'hire_date' => now()->subYear(),
            'email' => 'kartu.test@example.com',
        ]);

        $this->actingAs($this->admin())
            ->post('/rfid-cards', [
                'employee_id' => $employee->id,
                'uid' => 'ff11aa22',
                'note' => 'kartu baru',
            ])
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseHas('rfid_cards', [
            'employee_id' => $employee->id,
            'uid' => 'FF11AA22',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_uid_rejected(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'employee_id' => 'PGW-RF2',
            'name' => 'Pegawai Kartu Dua',
            'position' => 'Staff',
            'base_salary' => 5_000_000,
            'status' => 'Aktif',
            'hire_date' => now()->subYear(),
            'email' => 'kartu2.test@example.com',
        ]);

        $this->actingAs($admin)
            ->from(route('rfid-cards.index'))
            ->post('/rfid-cards', [
                'employee_id' => $employee->id,
                'uid' => 'A1B2C3D4',
            ])
            ->assertSessionHasErrors('uid');
    }

    public function test_assign_moves_card_and_deactivates_previous(): void
    {
        $admin = $this->admin();
        $target = Employee::where('employee_id', 'PGW005')->firstOrFail();
        $card = RfidCard::where('uid', 'A1B2C3D4')->firstOrFail();

        $this->actingAs($admin)
            ->post("/rfid-cards/{$card->id}/assign", ['employee_id' => $target->id])
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseHas('rfid_cards', [
            'id' => $card->id,
            'employee_id' => $target->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('rfid_cards', [
            'employee_id' => $target->id,
            'uid' => '7E8F9A0B',
            'is_active' => true,
        ]);
    }

    public function test_toggle_deactivates_card(): void
    {
        $admin = $this->admin();
        $card = RfidCard::where('uid', 'A1B2C3D4')->firstOrFail();

        $this->actingAs($admin)
            ->post("/rfid-cards/{$card->id}/toggle")
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseHas('rfid_cards', ['id' => $card->id, 'is_active' => false]);
    }

    public function test_destroy_removes_card(): void
    {
        $admin = $this->admin();
        $card = RfidCard::where('uid', 'A1B2C3D4')->firstOrFail();

        $this->actingAs($admin)
            ->delete("/rfid-cards/{$card->id}")
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseMissing('rfid_cards', ['id' => $card->id]);
    }

    public function test_pending_unknown_card_shown_on_index_page(): void
    {
        $admin = $this->admin();

        RfidUnknown::create([
            'uid' => 'EE4455',
            'ip_address' => '192.168.1.10',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'hits' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('rfid-cards.index'))
            ->assertOk()
            ->assertSee('Kartu Baru Terdeteksi dari Perangkat')
            ->assertSee('EE4455')
            ->assertSee('3×');
    }

    public function test_registering_pending_card_removes_pending_row(): void
    {
        $admin = $this->admin();
        $employee = Employee::where('employee_id', 'PGW005')->firstOrFail();

        RfidUnknown::create([
            'uid' => 'EE4455',
            'ip_address' => '192.168.1.10',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'hits' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/rfid-cards', [
                'employee_id' => $employee->id,
                'uid' => 'ee4455',
                'note' => 'daftar dari perangkat',
            ])
            ->assertRedirect(route('rfid-cards.index'));

        $this->assertDatabaseHas('rfid_cards', [
            'employee_id' => $employee->id,
            'uid' => 'EE4455',
        ]);
        $this->assertDatabaseMissing('rfid_unknowns', ['uid' => 'EE4455']);
    }
}
