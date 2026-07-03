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

ini_set('display_errors', '1');
error_reporting(E_ALL);

agent_log('H1', 'index.php:entry', 'request started', [
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'php' => PHP_VERSION,
    'sapi' => PHP_SAPI,
]);

$appRoot = __DIR__.'/ku-phumpanya-app';
$autoload = $appRoot.'/vendor/autoload.php';

agent_log('H3', 'index.php:paths', 'resolved paths', [
    'appRoot' => $appRoot,
    'autoload' => $autoload,
    'autoload_readable' => is_readable($autoload),
    'open_basedir' => ini_get('open_basedir'),
]);

if (! is_readable($autoload)) {
    agent_log('H3', 'index.php:autoload', 'autoload missing', ['path' => $autoload]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Laravel autoload not readable at: {$autoload}\n";
    exit(1);
}

define('LARAVEL_START', microtime(true));

try {
    if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require $autoload;
    agent_log('H4', 'index.php:autoload', 'autoload ok', []);

    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once $appRoot.'/bootstrap/app.php';
    agent_log('H4', 'index.php:bootstrap', 'bootstrap ok', []);

    $app->handleRequest(\Illuminate\Http\Request::capture());
    agent_log('H4', 'index.php:done', 'request handled', []);
} catch (Throwable $e) {
    agent_log('H4', 'index.php:exception', 'bootstrap failed', [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'LARAVEL ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    exit(1);
}
