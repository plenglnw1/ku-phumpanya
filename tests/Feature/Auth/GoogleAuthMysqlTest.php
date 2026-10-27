<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Re-runs the Google callback tests on MySQL/MariaDB, the engine KU production uses.
 * Skipped on the default SQLite run; see docs/RUNBOOK-google-login.md.
 */
class GoogleAuthMysqlTest extends GoogleAuthTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL only');
        }
    }
}
