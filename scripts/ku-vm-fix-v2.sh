#!/usr/bin/env bash
# Aggressive KU VM repair + evidence collection.
# Run ON server: bash ~/ku-vm-fix-v2.sh 2>&1 | tee ~/ku-fix-report.txt
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"
LOG="$APP_DIR/storage/logs/debug-1ad0c7.log"
REPORT="$HOME/ku-fix-report.txt"

mkdir -p "$APP_DIR/storage/logs"

log_json() {
  local hyp="$1" loc="$2" msg="$3" data="${4:-{}}"
  printf '%s\n' "{\"sessionId\":\"1ad0c7\",\"runId\":\"fix-v2\",\"hypothesisId\":\"$hyp\",\"location\":\"$loc\",\"message\":\"$msg\",\"data\":$data,\"timestamp\":$(date +%s000)}" >> "$LOG"
}

{
  echo "=== KU fix-v2 $(date -Iseconds) ==="
  echo "HOME=$HOME"
  echo "HTML_DIR=$HTML_DIR"
  echo "APP_DIR=$APP_DIR"
  echo ""

  echo "=== H7/H8: all .htaccess files under HOME ==="
  find "$HOME" -maxdepth 4 -name '.htaccess' 2>/dev/null | while read -r f; do
    echo "--- $f ($(wc -c <"$f") bytes) ---"
    cat "$f"
    echo ""
  done

  echo "=== H8: .user.ini / php.ini in html ==="
  find "$HTML_DIR" "$APP_DIR" -maxdepth 2 \( -name '.user.ini' -o -name 'php.ini' \) 2>/dev/null -exec echo "FOUND: {}" \; -exec cat {} \; || echo "(none)"

  echo "=== H1: files in html ==="
  ls -la "$HTML_DIR" || true

  echo "=== H3: CLI autoload ==="
  php -r "echo 'CLI '.PHP_VERSION.' open_basedir='.ini_get('open_basedir').' autoload='.(is_readable('$APP_DIR/vendor/autoload.php')?'OK':'FAIL').PHP_EOL;"

} | tee "$REPORT"

: > "$LOG"
log_json "H0" "fix-v2" "start" "{\"home\":\"$HOME\"}"

echo "==> REMOVE all .htaccess (html, app, home) — test Apache without overrides"
for f in "$HTML_DIR/.htaccess" "$APP_DIR/.htaccess" "$HOME/.htaccess"; do
  if [ -f "$f" ]; then
    cp "$f" "${f}.bak-fixv2"
    rm -f "$f"
    log_json "H7" "fix-v2" "removed htaccess" "{\"path\":\"$f\"}"
    echo "removed $f"
  fi
done

echo "==> remove AppleDouble"
find "$HTML_DIR" "$APP_DIR" -name '._*' -delete 2>/dev/null || true

echo "==> ultra-minimal web probes (no strict_types)"
printf '%s\n' 'static-ok-v2' > "$HTML_DIR/test-static.html"
printf '%s\n' '<?php echo "PHP_OK_v2 ".PHP_VERSION;' > "$HTML_DIR/t.php"
log_json "H2" "fix-v2" "wrote probes" "{}"

echo "==> restore public assets (exclude index.php)"
if [ -d "$APP_DIR/public" ]; then
  rsync -a --exclude='index.php' "$APP_DIR/public/" "$HTML_DIR/" 2>/dev/null \
    || (cd "$APP_DIR/public" && tar cf - --exclude=index.php . | tar xf - -C "$HTML_DIR")
fi

echo "==> instrumented index.php (open_basedir-safe path only)"
cat > "$HTML_DIR/index.php" <<'PHP'
<?php
// #region agent log
$log = __DIR__.'/ku-phumpanya-app/storage/logs/debug-1ad0c7.log';
@mkdir(dirname($log), 0775, true);
file_put_contents($log, json_encode(['sessionId'=>'1ad0c7','runId'=>'fix-v2','hypothesisId'=>'H3','location'=>'index.php:entry','message'=>'web hit','data'=>['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'uri'=>$_SERVER['REQUEST_URI']??''],'timestamp'=>(int)round(microtime(true)*1000)])."\n", FILE_APPEND);
// #endregion
$appRoot = __DIR__.'/ku-phumpanya-app';
if (!is_readable($appRoot.'/vendor/autoload.php')) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo "autoload FAIL at $appRoot\n";
    exit(1);
}
define('LARAVEL_START', microtime(true));
require $appRoot.'/vendor/autoload.php';
$app = require_once $appRoot.'/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
PHP

echo "==> storage permissions"
mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/framework/"{cache/data,sessions,views} "$APP_DIR/bootstrap/cache"
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

echo "==> artisan cache"
cd "$APP_DIR"
php artisan config:clear --no-interaction 2>/dev/null || true
php artisan config:cache --no-interaction 2>/dev/null || true

echo "==> CLI test t.php"
php "$HTML_DIR/t.php" 2>&1 | tee -a "$REPORT"

log_json "H0" "fix-v2" "done" "{\"htaccess_removed\":true,\"robots\":$(test -f "$HTML_DIR/robots.txt" && echo true || echo false)}"

echo ""
echo "=== DONE ==="
echo "Report: $REPORT"
echo "Debug log: $LOG"
echo ""
echo "Test (NO .htaccess now — static must work if Apache OK):"
echo "  curl -s https://phumpanya.ku.ac.th/test-static.html"
echo "  curl -s https://phumpanya.ku.ac.th/t.php"
echo ""
echo "If test-static.html STILL 500 → Apache/vhost issue (contact KU IT)."
echo "If test-static OK but / fails → Laravel issue, check debug log."
