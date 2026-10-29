<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeDemoUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_only_demo_accounts(): void
    {
        User::factory()->create(['email' => 'admin@phumpanya.test']);
        User::factory()->create(['email' => 'student@phumpanya.test']);
        $real = User::factory()->create(['email' => 'someone@ku.th']);

        $this->artisan('app:purge-demo-users')
            ->expectsOutputToContain('2 demo account(s) deleted')
            ->assertExitCode(0);

        $this->assertSame([$real->id], User::query()->pluck('id')->all());
    }

    public function test_keeps_the_admin_account(): void
    {
        $admin = User::factory()->create(['email' => 'admin@phumpanya.test', 'role' => UserRole::Admin]);

        $this->artisan('app:purge-demo-users')->assertExitCode(0);

        $this->assertModelExists($admin);
    }

    public function test_dry_run_keeps_the_accounts(): void
    {
        User::factory()->create(['email' => 'admin@phumpanya.test']);

        $this->artisan('app:purge-demo-users', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] admin@phumpanya.test')
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
    }
}
