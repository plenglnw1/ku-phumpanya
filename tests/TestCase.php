<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('auth_flow.password_enabled', true);
    }

    protected function frontendRedirect(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
