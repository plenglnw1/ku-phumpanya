<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// KU open_basedir: app must live at ~/html/ku-phumpanya-app (not ~/ku-phumpanya)
$appRoot = __DIR__.'/ku-phumpanya-app';

if (! is_readable($appRoot.'/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Laravel not found at: {$appRoot}\n";
    exit(1);
}

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
