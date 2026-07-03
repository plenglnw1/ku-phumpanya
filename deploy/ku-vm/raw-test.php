<?php

declare(strict_types=1);

// #region agent log
function agent_log(string $hypothesisId, string $location, string $message, array $data = []): void
{
    $path = __DIR__.'/ku-phumpanya-app/storage/logs/debug-1ad0c7.log';
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, json_encode([
        'sessionId' => '1ad0c7',
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
}
// #endregion

header('Content-Type: text/plain; charset=utf-8');

agent_log('H2', 'raw-test.php', 'PHP executed', [
    'php' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'open_basedir' => ini_get('open_basedir'),
    'dir' => __DIR__,
]);

echo "PHP_OK\n";
echo 'VERSION='.PHP_VERSION."\n";
echo 'SAPI='.PHP_SAPI."\n";
echo 'open_basedir='.ini_get('open_basedir')."\n";
echo 'autoload='.(is_readable(__DIR__.'/ku-phumpanya-app/vendor/autoload.php') ? 'OK' : 'FAIL')."\n";
