<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_complete_returns_user_json(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/register/complete', [
            'role' => 'student',
            'faculty' => 'เกษตรศาสตร์',
            'department' => 'วนศาสตร์',
            'student_id' => '1234567890',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'role',
                'faculty',
                'department',
                'student_id',
                'profile_completed_at',
            ])
            ->assertJsonPath('role', 'student')
            ->assertJsonPath('student_id', '1234567890');

        $this->assertNotNull($user->fresh()->profile_completed_at);
    }

    public function test_register_complete_idempotent_when_already_complete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/register/complete', [
            'role' => 'student',
            'faculty' => 'เกษตรศาสตร์',
            'department' => 'วนศาสตร์',
            'student_id' => '1234567890',
        ])->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_logout_returns_204(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/logout')
            ->assertNoContent();
    }

    public function test_incomplete_profile_can_access_register_complete(): void
    {
        Sanctum::actingAs(User::factory()->profileIncomplete()->withGoogle()->create());

        $this->postJson('/api/auth/register/complete', [
            'role' => 'researcher',
            'faculty' => 'วนศาสตร์',
            'department' => 'ป่าไม้',
            'employee_id' => 'EMP001',
            'research_affiliation' => 'KU Forest',
        ])->assertOk();
    }
}
