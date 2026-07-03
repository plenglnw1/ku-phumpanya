#!/usr/bin/env bash
# Server-side diagnostics for KU VM 500 errors.
# Run ON server: bash ~/ku-vm-diagnose.sh
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"
LOG="$APP_DIR/storage/logs/debug-1ad0c7.log"

mkdir -p "$APP_DIR/storage/logs"

log_json() {
  local hyp="$1" loc="$2" msg="$3" data="${4:-{}}"
  printf '%s\n' "{\"sessionId\":\"1ad0c7\",\"hypothesisId\":\"$hyp\",\"location\":\"$loc\",\"message\":\"$msg\",\"data\":$data,\"timestamp\":$(date +%s000)}" >> "$LOG"
}

echo "==> writing CLI diagnostics to $LOG"
: > "$LOG"

log_json "H1" "diagnose.sh" "start" "{\"home\":\"$HOME\",\"html\":\"$HTML_DIR\",\"app\":\"$APP_DIR\"}"

echo "==> static files in html"
for f in robots.txt favicon.ico index.php raw-test.php hello.php .htaccess; do
  if [ -e "$HTML_DIR/$f" ]; then
    stat_out=$(stat -c '%a %U %G %s' "$HTML_DIR/$f" 2>/dev/null || stat -f '%OLp %Su %Sg %z' "$HTML_DIR/$f")
    log_json "H1" "diagnose.sh:file" "exists $f" "{\"path\":\"$HTML_DIR/$f\",\"stat\":\"$stat_out\"}"
    echo "  OK  $f ($stat_out)"
  else
    log_json "H1" "diagnose.sh:file" "missing $f" "{\"path\":\"$HTML_DIR/$f\"}"
    echo "  MISS $f"
  fi
done

echo "==> app autoload"
if [ -r "$APP_DIR/vendor/autoload.php" ]; then
  log_json "H3" "diagnose.sh:autoload" "cli readable" "{\"path\":\"$APP_DIR/vendor/autoload.php\"}"
  echo "  autoload OK (CLI)"
else
  log_json "H3" "diagnose.sh:autoload" "cli missing" "{\"path\":\"$APP_DIR/vendor/autoload.php\"}"
  echo "  autoload FAIL (CLI)"
fi

echo "==> deploy instrumented PHP entrypoints"
cp "$APP_DIR/deploy/ku-vm/raw-test.php" "$HTML_DIR/raw-test.php" 2>/dev/null || cp "$HOME/raw-test.php" "$HTML_DIR/raw-test.php"
cp "$APP_DIR/deploy/ku-vm/html-index-debug.php" "$HTML_DIR/index.php" 2>/dev/null || cp "$HOME/html-index-debug.php" "$HTML_DIR/index.php"

echo "==> restore public static assets"
if [ -d "$APP_DIR/public" ]; then
  rsync -a "$APP_DIR/public/" "$HTML_DIR/" 2>/dev/null || cp -a "$APP_DIR/public/." "$HTML_DIR/"
  cp "$APP_DIR/deploy/ku-vm/html-index-debug.php" "$HTML_DIR/index.php"
  cp "$APP_DIR/deploy/ku-vm/raw-test.php" "$HTML_DIR/raw-test.php"
fi

echo "==> htaccess test (disable rewrite temporarily)"
if [ -f "$HTML_DIR/.htaccess" ]; then
  cp "$HTML_DIR/.htaccess" "$HTML_DIR/.htaccess.bak"
  log_json "H5" "diagnose.sh:htaccess" "backed up" "{}"
fi
echo '# diagnose: rewrite disabled' > "$HTML_DIR/.htaccess"

echo "==> CLI php raw-test"
php "$HTML_DIR/raw-test.php" 2>&1 | tee /tmp/ku-raw-cli.txt
log_json "H2" "diagnose.sh:cli" "raw-test cli" "{\"output\":\"$(tr '\n' ' ' </tmp/ku-raw-cli.txt | sed 's/"/\\"/g')\"}"

echo ""
echo "==> NEXT: from your Mac browser/curl hit:"
echo "  https://phumpanya.ku.ac.th/raw-test.php"
echo "  https://phumpanya.ku.ac.th/robots.txt"
echo ""
echo "Then on server run:"
echo "  cat $LOG"
echo ""
echo "Restore htaccess after diagnose:"
echo "  mv $HTML_DIR/.htaccess.bak $HTML_DIR/.htaccess"
