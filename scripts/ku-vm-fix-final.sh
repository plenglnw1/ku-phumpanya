#!/usr/bin/env bash
# Apply proven KU VM fixes (open_basedir + static assets + htaccess).
# Run ON server: bash ~/ku-vm-fix-final.sh
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"
LOG="$APP_DIR/storage/logs/debug-1ad0c7.log"

mkdir -p "$APP_DIR/storage/logs"

log_json() {
  local hyp="$1" loc="$2" msg="$3" data="${4:-{}}"
  printf '%s\n' "{\"sessionId\":\"1ad0c7\",\"runId\":\"fix-final\",\"hypothesisId\":\"$hyp\",\"location\":\"$loc\",\"message\":\"$msg\",\"data\":$data,\"timestamp\":$(date +%s000)}" >> "$LOG"
}

echo "==> KU VM fix-final"
: > "$LOG"
log_json "H0" "fix-final:start" "begin" "{\"home\":\"$HOME\",\"html\":\"$HTML_DIR\",\"app\":\"$APP_DIR\"}"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "ERROR: app not found at $APP_DIR"
  exit 1
fi

echo "==> remove Mac AppleDouble junk (._*)"
find "$HTML_DIR" "$APP_DIR" -name '._*' -delete 2>/dev/null || true
DOT_COUNT=$(find "$HTML_DIR" -maxdepth 1 -name '._*' 2>/dev/null | wc -l | tr -d ' ')
log_json "H2" "fix-final:dotclean" "removed AppleDouble" "{\"remaining_in_html\":$DOT_COUNT}"

echo "==> remove invalid app .htaccess (bare Require all denied causes Apache 500)"
rm -f "$APP_DIR/.htaccess"
log_json "H6" "fix-final:app-htaccess" "removed app htaccess" "{}"

echo "==> storage permissions"
mkdir -p "$APP_DIR/storage/logs" \
  "$APP_DIR/storage/framework/cache/data" \
  "$APP_DIR/storage/framework/sessions" \
  "$APP_DIR/storage/framework/views" \
  "$APP_DIR/bootstrap/cache"
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
if getent group webadmin >/dev/null 2>&1; then
  chgrp -R webadmin "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
fi

echo "==> restore public assets into html (robots, build, images)"
if [ -d "$APP_DIR/public" ]; then
  rsync -a --exclude='index.php' "$APP_DIR/public/" "$HTML_DIR/" 2>/dev/null \
    || (cd "$APP_DIR/public" && tar cf - . | tar xf - -C "$HTML_DIR")
  log_json "H1" "fix-final:rsync" "public assets synced" "{}"
fi

echo "==> deploy index.php (app INSIDE html only — open_basedir)"
SRC_INDEX="$APP_DIR/deploy/ku-vm/html-index-debug.php"
[ -f "$SRC_INDEX" ] || SRC_INDEX="$HOME/html-index-debug.php"
if [ ! -f "$SRC_INDEX" ]; then
  cat > "$HTML_DIR/index.php" <<'PHP'
<?php
declare(strict_types=1);
$log = __DIR__.'/ku-phumpanya-app/storage/logs/debug-1ad0c7.log';
@mkdir(dirname($log), 0775, true);
file_put_contents($log, json_encode(['sessionId'=>'1ad0c7','runId'=>'fix-final','hypothesisId'=>'H3','location'=>'index.php','message'=>'entry','data'=>['php'=>PHP_VERSION],'timestamp'=>(int)round(microtime(true)*1000)])."\n", FILE_APPEND);
$appRoot = __DIR__.'/ku-phumpanya-app';
if (!is_readable($appRoot.'/vendor/autoload.php')) { http_response_code(500); header('Content-Type:text/plain'); echo "autoload fail: $appRoot\n"; exit(1); }
define('LARAVEL_START', microtime(true));
require $appRoot.'/vendor/autoload.php';
$app = require_once $appRoot.'/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
PHP
else
  cp "$SRC_INDEX" "$HTML_DIR/index.php"
fi

echo "==> deploy raw-test.php"
SRC_RAW="$APP_DIR/deploy/ku-vm/raw-test.php"
[ -f "$SRC_RAW" ] || SRC_RAW="$HOME/raw-test.php"
[ -f "$SRC_RAW" ] && cp "$SRC_RAW" "$HTML_DIR/raw-test.php"

echo "==> minimal .htaccess"
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

echo 'static-ok' > "$HTML_DIR/test-static.html"

echo "==> convenience symlink"
ln -sfn "$APP_DIR" "$HOME/ku-phumpanya"
rm -f "$APP_DIR/ku-phumpanya-app" 2>/dev/null || true

echo "==> artisan optimize"
cd "$APP_DIR"
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan filament:optimize 2>/dev/null || true

log_json "H0" "fix-final:done" "complete" "{\"index_bytes\":$(wc -c <"$HTML_DIR/index.php"),\"htaccess_bytes\":$(wc -c <"$HTML_DIR/.htaccess"),\"robots_exists\":$(test -f "$HTML_DIR/robots.txt" && echo true || echo false)}"

echo ""
echo "==> Verify:"
echo "  curl -s https://phumpanya.ku.ac.th/test-static.html"
echo "  curl -s https://phumpanya.ku.ac.th/raw-test.php"
echo "  curl -sI https://phumpanya.ku.ac.th/"
echo "  cat $LOG"
