<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_profile_returns_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_patch_profile_updates_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'faculty' => 'วนศาสตร์',
            'department' => 'ป่าไม้',
            'student_id' => $user->student_id,
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('email', 'updated@example.com');
    }

    public function test_delete_profile_requires_password_and_returns_204(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/profile', [
            'password' => 'password',
        ])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
