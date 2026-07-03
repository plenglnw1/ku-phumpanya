<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_user_and_redirects_to_profile_completion(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-123');
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('getEmail')->andReturn('google@example.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('register.complete', absolute: false));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'google_id' => 'google-123',
        ]);

        $user = User::query()->where('email', 'google@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->profile_completed_at);
    }

    public function test_google_callback_redirects_completed_user_to_search(): void
    {
        $user = User::factory()->create([
            'email' => 'returning@example.com',
            'google_id' => 'google-999',
            'profile_completed_at' => now(),
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-999');
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('search.index', absolute: false));
        $this->assertAuthenticatedAs($user);
    }
}
