#!/usr/bin/env bash
# Recover KU VM after partial/broken deploy. Run ON server:
#   bash ~/ku-vm-recover.sh
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
PUBLIC_DIR="${KU_PUBLIC_DIR:-$HTML_DIR/public}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"

echo "==> paths"
echo "HOME=$HOME"
echo "HTML_DIR=$HTML_DIR"
echo "PUBLIC_DIR=$PUBLIC_DIR (Apache DOCUMENT_ROOT on KU)"
echo "APP_DIR=$APP_DIR"

if [ -L "$APP_DIR" ]; then
  target="$(readlink "$APP_DIR" 2>/dev/null || true)"
  if [ -z "$target" ] || [ "$target" = "$APP_DIR" ] || [ "$target" = "ku-phumpanya-app" ] || ! readlink -f "$APP_DIR" >/dev/null 2>&1; then
    echo "==> remove broken app symlink $APP_DIR"
    rm -f "$APP_DIR"
  fi
fi

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

echo "==> remove Mac AppleDouble junk (._*)"
find "$HTML_DIR" "$APP_DIR" -name '._*' -delete 2>/dev/null || true

echo "==> remove app .htaccess (Require all denied breaks entire vhost on KU)"
rm -f "$APP_DIR/.htaccess"

echo "==> minimal $PUBLIC_DIR/.htaccess"
mkdir -p "$PUBLIC_DIR"
cat > "$PUBLIC_DIR/.htaccess" <<'HTA'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTA

if [ -f "$APP_DIR/deploy/ku-vm/public-index.php" ]; then
  cp "$APP_DIR/deploy/ku-vm/public-index.php" "$PUBLIC_DIR/index.php"
else
  cp "$APP_DIR/deploy/ku-vm/html-index.php" "$PUBLIC_DIR/index.php"
fi

if [ -d "$APP_DIR/public" ]; then
  rsync -a --exclude='index.php' "$APP_DIR/public/" "$PUBLIC_DIR/" 2>/dev/null || true
fi

cp "$APP_DIR/deploy/ku-vm/ku-check.php" "$PUBLIC_DIR/ku-check.php" 2>/dev/null || true
echo 'static-ok' > "$PUBLIC_DIR/test-static.html"

echo "==> diagnostics"
cat > "$PUBLIC_DIR/hello.php" <<'PHP'
<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__.'/../ku-phumpanya-app';

echo 'PHP '.PHP_VERSION."\n";
echo 'document_root='.($_SERVER['DOCUMENT_ROOT'] ?? '')."\n";
echo 'open_basedir='.ini_get('open_basedir')."\n";
echo 'autoload='.(is_readable($root.'/vendor/autoload.php') ? 'OK' : 'FAIL')."\n";

if (! is_readable($root.'/vendor/autoload.php')) {
    exit(1);
}

try {
    require $root.'/vendor/autoload.php';
    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create('/up', 'GET');
    $response = $kernel->handle($request);
    echo 'up_status='.$response->getStatusCode()."\n";
    echo 'up_body='.trim($response->getContent())."\n";
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo 'boot_error='.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
PHP

echo "==> artisan cache"
cd "$APP_DIR"
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:clear --no-interaction
php artisan view:cache --no-interaction
php artisan filament:optimize 2>/dev/null || true

echo ""
echo "==> CLI smoke test"
php artisan about --no-interaction 2>&1 | head -5

echo ""
echo "==> Done. Test these URLs:"
echo "  https://phumpanya.ku.ac.th/hello.php   (autoload + /up via web PHP)"
echo "  https://phumpanya.ku.ac.th/            (Laravel home)"
echo "  https://phumpanya.ku.ac.th/up"
echo ""
echo "Logs: tail -30 $APP_DIR/storage/logs/laravel.log"
echo "Cleanup: rm $PUBLIC_DIR/hello.php $PUBLIC_DIR/ku-check.php"
