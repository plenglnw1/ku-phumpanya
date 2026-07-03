#!/usr/bin/env bash
# Self-testing KU VM repair. Run ON server:
#   bash ~/ku-vm-fix-v3.sh 2>&1 | tee ~/ku-fix-report.txt
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"
LOG="$APP_DIR/storage/logs/debug-1ad0c7.log"
REPORT="$HOME/ku-fix-report.txt"
URL="${KU_SITE_URL:-https://phumpanya.ku.ac.th}"

mkdir -p "$APP_DIR/storage/logs" 2>/dev/null || true

log_json() {
  local hyp="$1" loc="$2" msg="$3" data="${4:-{}}"
  printf '%s\n' "{\"sessionId\":\"1ad0c7\",\"runId\":\"fix-v3\",\"hypothesisId\":\"$hyp\",\"location\":\"$loc\",\"message\":\"$msg\",\"data\":$data,\"timestamp\":$(date +%s000)}" >> "$LOG" 2>/dev/null || true
}

probe() {
  local path="$1"
  local out body code
  body=$(curl -sS -m 15 "${URL}${path}" 2>/dev/null || echo "CURL_ERROR")
  code=$(curl -sS -m 15 -o /dev/null -w "%{http_code}" "${URL}${path}" 2>/dev/null || echo "000")
  echo "PROBE path=${path} code=${code} body=$(echo "$body" | tr '\n' ' ' | head -c 120)"
  log_json "H9" "fix-v3:probe" "$path" "{\"code\":\"$code\",\"body_preview\":\"$(echo "$body" | tr '\n' ' ' | head -c 80 | sed 's/"/\\"/g')\"}"
}

{
  echo "=== KU fix-v3 $(date -Iseconds) ==="
  echo "HOME=$HOME HTML=$HTML_DIR APP=$APP_DIR"
  echo ""

  echo "=== before: all .htaccess under HOME ==="
  find "$HOME" -maxdepth 4 -name '.htaccess' 2>/dev/null | while read -r f; do
    echo "$f ($(wc -c <"$f") bytes)"
  done || echo "(none)"

  echo ""
  echo "=== before probes ==="
  probe "/test-static.html"
  probe "/t.php"
  probe "/robots.txt"
  probe "/"
} | tee "$REPORT"

: > "$LOG" 2>/dev/null || true
log_json "H0" "fix-v3" "start" "{}"

echo "==> remove ALL .htaccess (html, app, home)"
for f in "$HTML_DIR/.htaccess" "$APP_DIR/.htaccess" "$HOME/.htaccess"; do
  [ -f "$f" ] && cp "$f" "${f}.bak-v3" && rm -f "$f" && echo "removed $f"
done
find "$HTML_DIR" "$APP_DIR" -name '._*' -delete 2>/dev/null || true
rm -f "$APP_DIR/ku-phumpanya-app" 2>/dev/null || true

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "WARN: app not at $APP_DIR — searching..."
  if [ -f "$HOME/ku-phumpanya/artisan" ]; then
    APP_DIR="$HOME/ku-phumpanya"
    echo "Found app at $APP_DIR"
  fi
fi

echo "==> write probes + index (NO .htaccess)"
printf '%s\n' 'static-ok-v3' > "$HTML_DIR/test-static.html"
printf '%s\n' '<?php echo "PHP_OK_v3 ".PHP_VERSION;' > "$HTML_DIR/t.php"

if [ -f "$APP_DIR/artisan" ]; then
  rsync -a --exclude='index.php' "$APP_DIR/public/" "$HTML_DIR/" 2>/dev/null \
    || (cd "$APP_DIR/public" && tar cf - --exclude=index.php . 2>/dev/null | tar xf - -C "$HTML_DIR")
  cat > "$HTML_DIR/index.php" <<'PHP'
<?php
$log = __DIR__.'/ku-phumpanya-app/storage/logs/debug-1ad0c7.log';
@mkdir(dirname($log), 0775, true);
file_put_contents($log, json_encode(['sessionId'=>'1ad0c7','runId'=>'fix-v3','hypothesisId'=>'H3','location'=>'index.php','message'=>'hit','data'=>['php'=>PHP_VERSION],'timestamp'=>(int)round(microtime(true)*1000)])."\n", FILE_APPEND);
$appRoot = __DIR__.'/ku-phumpanya-app';
if (!is_readable($appRoot.'/vendor/autoload.php')) { header('Content-Type:text/plain'); http_response_code(500); echo "autoload FAIL\n"; exit(1); }
define('LARAVEL_START', microtime(true));
require $appRoot.'/vendor/autoload.php';
$app = require_once $appRoot.'/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
PHP
  mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache"
  chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true
  cd "$APP_DIR" && php artisan config:clear --no-interaction 2>/dev/null || true
  cd "$APP_DIR" && php artisan config:cache --no-interaction 2>/dev/null || true
fi

echo "" | tee -a "$REPORT"
echo "=== after fix probes ===" | tee -a "$REPORT"
probe "/test-static.html" | tee -a "$REPORT"
probe "/t.php" | tee -a "$REPORT"
probe "/robots.txt" | tee -a "$REPORT"
probe "/" | tee -a "$REPORT"

STATIC_CODE=$(curl -sS -m 15 -o /dev/null -w "%{http_code}" "${URL}/test-static.html" 2>/dev/null || echo "000")

if [ "$STATIC_CODE" != "200" ]; then
  BACKUP=$(find "$HOME" -maxdepth 1 -type d -name 'html-backup-*' 2>/dev/null | sort | tail -1)
  if [ -n "$BACKUP" ] && [ -d "$BACKUP" ]; then
    echo "" | tee -a "$REPORT"
    echo "==> static still $STATIC_CODE — restore html from $BACKUP" | tee -a "$REPORT"
    BROKEN="$HOME/html-broken-$(date +%Y%m%d%H%M%S)"
    cp -a "$HTML_DIR" "$BROKEN"
    rm -rf "$HTML_DIR"
    cp -a "$BACKUP" "$HTML_DIR"
    # re-apply app + index only (no htaccess)
    if [ -d "$BROKEN/ku-phumpanya-app" ]; then
      cp -a "$BROKEN/ku-phumpanya-app" "$HTML_DIR/" 2>/dev/null || true
    fi
    printf '%s\n' 'static-ok-v3' > "$HTML_DIR/test-static.html"
    printf '%s\n' '<?php echo "PHP_OK_v3 ".PHP_VERSION;' > "$HTML_DIR/t.php"
    if [ -f "$HTML_DIR/ku-phumpanya-app/artisan" ]; then
      cp "$BROKEN/index.php" "$HTML_DIR/index.php" 2>/dev/null || cat > "$HTML_DIR/index.php" <<'PHP'
<?php
$appRoot = __DIR__.'/ku-phumpanya-app';
require $appRoot.'/vendor/autoload.php';
$app = require_once $appRoot.'/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
PHP
    fi
    rm -f "$HTML_DIR/.htaccess" "$HTML_DIR/ku-phumpanya-app/.htaccess" 2>/dev/null || true
    echo "=== after backup-restore probes ===" | tee -a "$REPORT"
    probe "/test-static.html" | tee -a "$REPORT"
    probe "/t.php" | tee -a "$REPORT"
    probe "/" | tee -a "$REPORT"
    log_json "H10" "fix-v3" "restored backup" "{\"backup\":\"$BACKUP\"}"
  else
    echo "No html-backup-* found — contact KU IT (Apache vhost issue)" | tee -a "$REPORT"
    log_json "H10" "fix-v3" "no backup" "{}"
  fi
fi

echo "" | tee -a "$REPORT"
echo "=== FILES ===" | tee -a "$REPORT"
ls -la "$HTML_DIR" | head -25 | tee -a "$REPORT"
echo "" | tee -a "$REPORT"
echo "Report: $REPORT" | tee -a "$REPORT"
echo "Debug:  $LOG" | tee -a "$REPORT"
log_json "H0" "fix-v3" "done" "{}"
