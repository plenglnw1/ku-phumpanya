<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only deploy sanity check for Google login: pending migrations, the users
 * columns the callback writes, and the session/OAuth config. Never prints secrets.
 */
final class CheckSchema extends Command
{
    protected $signature = 'app:check-schema';

    protected $description = 'Check migrations, users schema and session/OAuth config used by Google login';

    private const USER_COLUMNS = [
        'google_id', 'avatar_url', 'profile_completed_at', 'faculty', 'department',
        'student_id', 'employee_id', 'research_affiliation', 'role',
    ];

    public function handle(Migrator $migrator): int
    {
        $problems = [];

        $pending = array_diff(
            array_keys($migrator->getMigrationFiles(database_path('migrations'))),
            $migrator->getRepository()->getRan(),
        );
        foreach ($pending as $name) {
            $problems[] = "pending migration: {$name}";
        }

        $columns = collect(Schema::getColumns('users'));
        foreach (array_diff(self::USER_COLUMNS, $columns->pluck('name')->all()) as $missing) {
            $problems[] = "users.{$missing} is missing";
        }
        if (! ($columns->firstWhere('name', 'password')['nullable'] ?? false)) {
            $problems[] = 'users.password is NOT NULL (Google users have no password)';
        }

        if (config('session.driver') === 'database' && ! Schema::hasTable(config('session.table', 'sessions'))) {
            $problems[] = 'sessions table is missing but SESSION_DRIVER=database';
        }

        $this->table(['config', 'value'], [
            ['session.driver', (string) config('session.driver')],
            ['session.domain', (string) config('session.domain')],
            ['session.secure', var_export(config('session.secure'), true)],
            ['session.same_site', (string) config('session.same_site')],
            ['app.url', (string) config('app.url')],
            ['app.frontend_url', (string) config('app.frontend_url')],
            ['services.google.redirect', (string) config('services.google.redirect')],
            ['services.google.client_id set', var_export(filled(config('services.google.client_id')), true)],
        ]);

        foreach ($problems as $problem) {
            $this->error($problem);
        }
        $this->line($problems === [] ? 'Schema OK' : count($problems).' problem(s) found');

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
