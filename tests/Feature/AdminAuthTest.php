<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $response = $this->postWithCsrf('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'ValidPassword123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull(session('admin_id'));
        $this->assertNotNull(session('admin_login_at'));
    }

    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $response = $this->postWithCsrf('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull(session('admin_id'));
    }

    public function test_admin_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postWithCsrf('/admin/login', [
            'email' => 'nobody@test.com',
            'password' => 'AnyPassword',
        ]);

        $response->assertRedirect();
        $this->assertNull(session('admin_id'));
    }

    public function test_admin_can_logout(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->postWithCsrf('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'ValidPassword123!',
        ]);

        $this->assertNotNull(session('admin_id'));

        $response = $this->postWithCsrf('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertNull(session('admin_id'));
    }

    public function test_admin_session_expires_after_eight_hours(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->withSession([
            'admin_id' => 1,
            'admin_login_at' => now()->subHours(9),
        ])->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_session_remains_valid_within_eight_hours(): void
    {
        Admin::create([
            'email' => 'admin@test.com',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->withSession([
            'admin_id' => 1,
            'admin_login_at' => now()->subHours(7),
        ])->get('/admin')
            ->assertOk();
    }

    public function test_unauthenticated_admin_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}