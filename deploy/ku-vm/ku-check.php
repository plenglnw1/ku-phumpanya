<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "KU Laravel diagnostic\n";
echo str_repeat('=', 40)."\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'SAPI: '.PHP_SAPI."\n";
echo 'open_basedir: '.(ini_get('open_basedir') ?: '(none)')."\n";
echo '__DIR__: '.__DIR__."\n";
echo 'HOME guess: '.(getenv('HOME') ?: '(unknown)')."\n\n";

$required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'intl'];
echo "Extensions:\n";
foreach ($required as $ext) {
    echo '  '.($ext.': '.(extension_loaded($ext) ? 'OK' : 'MISSING'))."\n";
}

echo "\nApp root candidates:\n";
$candidates = [
    'sibling ~/ku-phumpanya' => dirname(__DIR__).'/ku-phumpanya',
    'html/ku-phumpanya-app' => __DIR__.'/ku-phumpanya-app',
];
foreach ($candidates as $label => $path) {
    $autoload = $path.'/vendor/autoload.php';
    $readable = is_readable($autoload);
    echo "  [$label] $path\n";
    echo '    autoload: '.($readable ? 'readable' : 'NOT readable')."\n";
}

echo "\nDelete this file after debugging.\n";
