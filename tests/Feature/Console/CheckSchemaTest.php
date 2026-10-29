<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_on_a_fully_migrated_schema(): void
    {
        $this->artisan('app:check-schema')->assertExitCode(0);
    }

    public function test_fails_and_names_the_missing_column(): void
    {
        Schema::table('users', fn ($table) => $table->dropColumn('avatar_url'));

        $this->artisan('app:check-schema')
            ->expectsOutputToContain('avatar_url')
            ->assertExitCode(1);
    }
}
