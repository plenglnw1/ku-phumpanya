#!/usr/bin/env bash
# Fix common KU VM 500 errors after deploy. Run ON the server:
#   bash ~/ku-vm-fix-500.sh
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
PUBLIC_DIR="${KU_PUBLIC_DIR:-$HTML_DIR/public}"
HTML_APP="$HTML_DIR/ku-phumpanya-app"

# App may live under ~/html/ku-phumpanya-app (open_basedir) or ~/ku-phumpanya
if [ -f "$HTML_APP/artisan" ]; then
  APP_DIR="$HTML_APP"
elif [ -f "${KU_APP_DIR:-$HOME/ku-phumpanya}/artisan" ]; then
  APP_DIR="${KU_APP_DIR:-$HOME/ku-phumpanya}"
else
  echo "ERROR: Laravel app not found"
  exit 1
fi

echo "==> PHP (CLI)"
php -v
php -m | grep -E 'pdo_mysql|mbstring|intl|fileinfo' || true

echo "==> open_basedir (CLI)"
php -r 'echo "open_basedir=".ini_get("open_basedir").PHP_EOL;'

echo "==> ensure app readable from web PHP"
if [ ! -d "$HTML_APP" ] && [ -d "$APP_DIR/vendor" ]; then
  echo "Linking $APP_DIR -> $HTML_APP"
  ln -sfn "$APP_DIR" "$HTML_APP"
fi

# open_basedir on KU often limits PHP to ~/html — move app inside if symlink unreadable
if ! php -r "exit(is_readable('${HTML_APP}/vendor/autoload.php') ? 0 : 1);" 2>/dev/null; then
  if [ -d "$APP_DIR" ] && [ "$APP_DIR" != "$HTML_APP" ]; then
    echo "Symlink not readable from PHP; moving app into $HTML_APP"
    rm -f "$HTML_APP"
    mv "$APP_DIR" "$HTML_APP"
    APP_DIR="$HTML_APP"
    ln -sfn "$HTML_APP" "$HOME/ku-phumpanya"
  fi
fi

if [ -f "$APP_DIR/deploy/ku-vm/app-deny.htaccess" ]; then
  rm -f "$APP_DIR/.htaccess"
fi

echo "==> permissions (webadmin group)"
cd "$APP_DIR"
chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || true
if getent group webadmin >/dev/null 2>&1; then
  chgrp -R webadmin storage bootstrap/cache 2>/dev/null || true
fi
# Apache must read vendor + app (do not chmod vendor/bin — breaks artisan)
chmod -R u+rwX,g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

echo "==> minimal .htaccess in $PUBLIC_DIR (Apache DOCUMENT_ROOT)"
mkdir -p "$PUBLIC_DIR"
if [ -f "$APP_DIR/deploy/ku-vm/htaccess.minimal" ]; then
  cp "$APP_DIR/deploy/ku-vm/htaccess.minimal" "$PUBLIC_DIR/.htaccess"
else
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
fi

echo "==> index.php + diagnostic"
if [ -f "$APP_DIR/deploy/ku-vm/public-index.php" ]; then
  cp "$APP_DIR/deploy/ku-vm/public-index.php" "$PUBLIC_DIR/index.php"
else
  cp "$APP_DIR/deploy/ku-vm/html-index.php" "$PUBLIC_DIR/index.php"
fi
if [ -d "$APP_DIR/public" ]; then
  rsync -a --exclude='index.php' "$APP_DIR/public/" "$PUBLIC_DIR/" 2>/dev/null || true
fi
cp "$APP_DIR/deploy/ku-vm/ku-check.php" "$PUBLIC_DIR/ku-check.php"

echo "==> clear caches (avoid stale broken config)"
cd "$APP_DIR"
php artisan config:clear --no-interaction 2>/dev/null || true
php artisan route:clear --no-interaction 2>/dev/null || true
php artisan view:clear --no-interaction 2>/dev/null || true

echo "==> re-cache"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan filament:optimize 2>/dev/null || true

echo ""
echo "==> Test in browser:"
echo "  https://phumpanya.ku.ac.th/ku-check.php"
echo "  https://phumpanya.ku.ac.th/"
echo ""
echo "If ku-check.php works but / is 500, check:"
echo "  tail -30 $APP_DIR/storage/logs/laravel.log"
echo ""
echo "Delete ku-check.php when done:"
echo "  rm $PUBLIC_DIR/ku-check.php"
