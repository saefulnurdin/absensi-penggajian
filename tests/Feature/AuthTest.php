<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->withSession([])->get('/login')->assertOk()->assertSee('Login');
    }

    public function test_admin_can_login_and_reach_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'admin1234', 'is_admin' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'admin1234',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => 'admin1234']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpass',
        ])->assertSessionHasErrors('email');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
