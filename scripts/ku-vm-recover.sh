#!/usr/bin/env bash
# Recover KU VM after partial/broken deploy. Run ON server:
#   bash ~/ku-vm-recover.sh
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"

echo "==> paths"
echo "HOME=$HOME"
echo "HTML_DIR=$HTML_DIR"
echo "APP_DIR=$APP_DIR"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "ERROR: Laravel app not found at $APP_DIR"
  echo "       Expected app at ~/html/ku-phumpanya-app"
  exit 1
fi

echo "==> remove broken symlinks inside app"
rm -f "$APP_DIR/ku-phumpanya-app" 2>/dev/null || true

echo "==> convenience symlink ~/ku-phumpanya"
ln -sfn "$APP_DIR" "$HOME/ku-phumpanya"

echo "==> storage + bootstrap/cache"
mkdir -p "$APP_DIR/storage/logs" \
  "$APP_DIR/storage/framework/cache/data" \
  "$APP_DIR/storage/framework/sessions" \
  "$APP_DIR/storage/framework/views" \
  "$APP_DIR/bootstrap/cache"
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
if getent group webadmin >/dev/null 2>&1; then
  chgrp -R webadmin "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
fi

echo "==> block direct web access to app folder"
cat > "$APP_DIR/.htaccess" <<'HTA'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
HTA

echo "==> minimal ~/html/.htaccess"
cat > "$HTML_DIR/.htaccess" <<'HTA'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTA

echo "==> ~/html/index.php"
cat > "$HTML_DIR/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

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
PHP

echo "==> diagnostics"
cat > "$HTML_DIR/hello.php" <<'PHP'
<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP ".PHP_VERSION."\n";
echo "open_basedir=".ini_get('open_basedir')."\n";
echo "autoload=".(is_readable(__DIR__.'/ku-phumpanya-app/vendor/autoload.php') ? 'OK' : 'FAIL')."\n";
PHP

echo "==> artisan cache"
cd "$APP_DIR"
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan filament:optimize 2>/dev/null || true

echo ""
echo "==> CLI smoke test"
php artisan about --no-interaction 2>&1 | head -5

echo ""
echo "==> Done. Test these URLs:"
echo "  https://phumpanya.ku.ac.th/hello.php   (must show PHP version)"
echo "  https://phumpanya.ku.ac.th/            (Laravel home)"
echo "  https://phumpanya.ku.ac.th/login"
echo ""
echo "Logs: tail -30 $APP_DIR/storage/logs/laravel.log"
echo "Cleanup: rm $HTML_DIR/hello.php"
