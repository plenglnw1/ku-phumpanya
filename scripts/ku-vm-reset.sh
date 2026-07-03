#!/usr/bin/env bash
# Reset KU VM to pre-deploy state (restore html from backup, remove Laravel deploy).
# Run ON server: bash ~/ku-vm-reset.sh
set -euo pipefail

HOME_DIR="${HOME:?HOME not set}"
HTML_DIR="$HOME_DIR/html"
BACKUP=$(find "$HOME_DIR" -maxdepth 1 -type d -name 'html-backup-*' 2>/dev/null | sort | tail -1)

echo "==> KU VM reset"
echo "HOME=$HOME_DIR"

if [ -z "$BACKUP" ] || [ ! -d "$BACKUP" ]; then
  echo "ERROR: no html-backup-* found in $HOME_DIR"
  echo "       Cannot restore original html automatically."
  exit 1
fi

echo "==> restore html from $BACKUP"
rm -rf "$HTML_DIR"
cp -a "$BACKUP" "$HTML_DIR"
echo "    html restored"

echo "==> remove Laravel app + deploy files"
rm -rf "$HOME_DIR/ku-phumpanya"
rm -rf "$HTML_DIR/ku-phumpanya-app" 2>/dev/null || true
rm -f "$HOME_DIR/ku-phumpanya" 2>/dev/null || true

rm -f "$HOME_DIR/ku-phumpanya-deploy.tgz"
rm -f "$HOME_DIR"/ku-vm-*.sh
rm -f "$HOME_DIR"/{html-index.php,htaccess.minimal,app-deny.htaccess,ku-check.php,raw-test.php,html-index-debug.php}
rm -f "$HOME_DIR/ku-fix-report.txt"

echo "==> remove test/diagnostic files from html"
rm -f "$HTML_DIR"/{test-static.html,t.php,hello.php,raw-test.php,ku-check.php,diag.php} 2>/dev/null || true
rm -f "$HTML_DIR/.htaccess.bak"* 2>/dev/null || true

echo ""
echo "==> Done. Remaining in home:"
ls -la "$HOME_DIR" | head -20

echo ""
echo "Test: curl -sI https://phumpanya.ku.ac.th/"
echo ""
echo "Optional — delete backup after you confirm site works:"
echo "  rm -rf $BACKUP"
