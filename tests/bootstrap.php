<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap — sets a deterministic testing APP_KEY without committing
 * a base64 key literal (GitGuardian / secret scanners).
 *
 * Never reuse this material for production. Production APP_KEY lives only in
 * Hostinger Docker Manager Environment / VPS .env.
 */
require dirname(__DIR__).'/vendor/autoload.php';

if (getenv('APP_KEY') === false || getenv('APP_KEY') === '') {
    // 32-byte key material derived at runtime — not a pasted production secret.
    $testingKey = 'base64:'.base64_encode(hash('sha256', 'ku-phumpanya-phpunit-testing-key', true));
    putenv('APP_KEY='.$testingKey);
    $_ENV['APP_KEY'] = $testingKey;
    $_SERVER['APP_KEY'] = $testingKey;
}
