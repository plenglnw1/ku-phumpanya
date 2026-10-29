<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_does_not_request_profile_scope(): void
    {
        $location = $this->get('/auth/google')->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('openid email', $query['scope']);
        $this->assertStringNotContainsString('profile', $query['scope']);
        $this->assertSame('select_account', $query['prompt']);
    }

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

        $response->assertRedirect($this->frontendRedirect('/register/'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'google_id' => 'google-123',
        ]);

        $user = User::query()->where('email', 'google@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->profile_completed_at);
        $this->assertNotNull($user->email_verified_at);
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

        $response->assertRedirect($this->frontendRedirect('/'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_state_redirects_with_session_reason_instead_of_500(): void
    {
        Log::spy();
        $this->providerThrows(new InvalidStateException);

        $this->get('/auth/google/callback')->assertRedirect($this->frontendRedirect('/?login_error=session'));

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->with('google.callback.invalid_state', Mockery::type('array'))->once();
    }

    public function test_provider_failure_redirects_with_google_reason(): void
    {
        Log::spy();
        $this->providerThrows(new \RuntimeException('boom'));

        $this->get('/auth/google/callback')->assertRedirect($this->frontendRedirect('/?login_error=google'));

        $this->assertGuest();
        Log::shouldHaveReceived('error')->with('google.callback.provider', Mockery::type('array'))->once();
    }

    public function test_email_owned_by_a_different_google_id_is_refused_and_left_untouched(): void
    {
        Log::spy();
        $existing = User::factory()->create(['email' => 'shared@example.com', 'google_id' => 'google-OLD']);
        $this->providerReturns('google-NEW', 'shared@example.com');

        $this->get('/auth/google/callback')->assertRedirect($this->frontendRedirect('/?login_error=account'));

        $this->assertGuest();
        $this->assertSame('google-OLD', $existing->fresh()->google_id);
        Log::shouldHaveReceived('warning')->with('google.callback.google_id_conflict', ['user_id' => $existing->id])->once();
    }

    public function test_google_id_match_wins_over_a_separate_email_match(): void
    {
        $byGoogleId = User::factory()->create(['email' => 'old@example.com', 'google_id' => 'google-1']);
        $byEmail = User::factory()->create(['email' => 'new@example.com', 'google_id' => null]);
        $this->providerReturns('google-1', 'new@example.com');

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertAuthenticatedAs($byGoogleId);
        $this->assertNull($byEmail->fresh()->google_id);
    }

    public function test_unexpected_upsert_failure_redirects_with_account_reason(): void
    {
        Log::spy();
        $this->providerReturns('google-1', 'boom@example.com');
        User::creating(fn () => throw new \RuntimeException('db down'));

        $this->get('/auth/google/callback')->assertRedirect($this->frontendRedirect('/?login_error=account'));

        $this->assertGuest();
        Log::shouldHaveReceived('error')->with('google.callback.user_upsert', Mockery::on(
            fn (array $context) => $context['email_domain'] === 'example.com'
        ))->once();
    }

    private function providerReturns(string $id, string $email): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn($id);
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function providerThrows(\Throwable $e): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow($e);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
