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

    public function test_admin_user_is_redirected_to_filament_after_login(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
    }

    public function test_non_admin_can_access_search_instead_of_admin(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $this->actingAs($student)
            ->get('/admin')
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
            ->get('/admin')
            ->assertOk();
    }
}
