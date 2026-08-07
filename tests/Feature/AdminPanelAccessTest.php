<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_is_redirected_to_frontend_admin_after_login(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect($this->frontendRedirect('/admin/'));
    }

    public function test_non_admin_cannot_access_filament_panel(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $this->actingAs($student)
            ->get('/filament')
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('search.index'))
            ->assertOk();
    }

    public function test_admin_can_access_filament_panel(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->get('/filament')
            ->assertOk();
    }
}
