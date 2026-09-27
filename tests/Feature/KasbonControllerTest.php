<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Kasbon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasbonControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@absensi.local')->firstOrFail();
    }

    private function employee(): Employee
    {
        return Employee::firstOrFail();
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin())->get('/kasbon')->assertOk()->assertSee('Tambah Kasbon');
    }

    public function test_store_creates_pending_kasbon(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin())
            ->post('/kasbon', [
                'employee_id' => $employee->id,
                'amount' => 250_000,
                'deduct_period' => now()->format('Y-m'),
                'note' => 'Uang muka',
            ])
            ->assertRedirect();

        $kasbon = Kasbon::firstOrFail();
        $this->assertSame($employee->id, $kasbon->employee_id);
        $this->assertSame('250000.00', (string) $kasbon->amount);
        $this->assertSame(Kasbon::PENDING, $kasbon->status);
    }

    public function test_store_validates_amount_and_period(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin())
            ->post('/kasbon', [
                'employee_id' => $employee->id,
                'amount' => 0,
                'deduct_period' => 'not-a-period',
            ])
            ->assertSessionHasErrors(['amount', 'deduct_period']);

        $this->assertSame(0, Kasbon::count());
    }

    public function test_approve_and_cancel_flow(): void
    {
        $employee = $this->employee();
        $kasbon = Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 100_000,
            'deduct_period' => now()->format('Y-m'),
            'status' => Kasbon::PENDING,
        ]);

        $this->actingAs($this->admin())->post("/kasbon/{$kasbon->id}/approve")->assertRedirect();

        $this->assertSame(Kasbon::APPROVED, $kasbon->refresh()->status);
        $this->assertNotNull($kasbon->approved_at);

        $this->actingAs($this->admin())->post("/kasbon/{$kasbon->id}/cancel")->assertRedirect();
        $this->assertSame(Kasbon::CANCELLED, $kasbon->refresh()->status);
    }

    public function test_paid_kasbon_cannot_be_cancelled_or_deleted(): void
    {
        $second = $this->employee();
        $kasbon = Kasbon::create([
            'employee_id' => $second->id,
            'amount' => 100_000,
            'deduct_period' => now()->format('Y-m'),
            'status' => Kasbon::PAID,
            'paid_in_period' => now()->format('Y-m'),
        ]);

        $this->actingAs($this->admin())
            ->post("/kasbon/{$kasbon->id}/cancel")
            ->assertSessionHasErrors('kasbon');

        $this->actingAs($this->admin())
            ->delete("/kasbon/{$kasbon->id}")
            ->assertSessionHasErrors('kasbon');

        $this->assertDatabaseHas('kasbons', ['id' => $kasbon->id]);
    }

    public function test_pending_kasbon_can_be_deleted(): void
    {
        $employee = $this->employee();
        $kasbon = Kasbon::create([
            'employee_id' => $employee->id,
            'amount' => 100_000,
            'deduct_period' => now()->format('Y-m'),
            'status' => Kasbon::PENDING,
        ]);

        $this->actingAs($this->admin())->delete("/kasbon/{$kasbon->id}")->assertRedirect();

        $this->assertDatabaseMissing('kasbons', ['id' => $kasbon->id]);
    }

    public function test_non_admin_blocked(): void
    {
        $user = User::create([
            'name' => 'Bukan Admin',
            'email' => 'bukan.admin.kasbon@example.com',
            'password' => 'rahasia123',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get('/kasbon')->assertForbidden();
    }
}