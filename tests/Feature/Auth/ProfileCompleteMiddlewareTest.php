<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompleteMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profile_is_redirected_from_search(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/search');

        $response->assertRedirect(route('register.complete', absolute: false));
    }

    public function test_complete_profile_can_access_search(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/search');

        $response->assertOk();
    }

    public function test_incomplete_profile_can_access_register_complete(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/register/complete');

        $response->assertOk();
    }
}
