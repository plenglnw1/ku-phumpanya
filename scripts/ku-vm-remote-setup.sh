#!/usr/bin/env bash
# Run ON KU VM after uploading ku-phumpanya-deploy.tgz to ~/
# Usage:
#   export KU_MYSQL_PASSWORD='your-mysql-password'
#   bash ku-vm-remote-setup.sh
set -euo pipefail

APP_DIR="${KU_APP_DIR:-$HOME/ku-phumpanya}"
HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
TARBALL="${KU_TARBALL:-$HOME/ku-phumpanya-deploy.tgz}"
ENV_FILE="$APP_DIR/.env"

echo "==> Phase 0: recon"
php -v
php -m | grep -E 'pdo_mysql|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|fileinfo|intl' || {
  echo "ERROR: missing PHP extensions for Laravel (intl required for Filament)"
  exit 1
}
df -h "$HOME"
ls -la "$HTML_DIR" || mkdir -p "$HTML_DIR"

PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 3 ]; }; then
  echo "ERROR: PHP 8.3+ required for Laravel 13 (found $(php -r 'echo PHP_VERSION;'))"
  exit 1
fi

echo "==> extract app to $APP_DIR"
mkdir -p "$APP_DIR"
tar xzf "$TARBALL" -C "$APP_DIR"
find "$APP_DIR" -name '._*' -delete 2>/dev/null || true

# KU open_basedir: keep app under ~/html/ku-phumpanya-app
if [ "$APP_DIR" != "$HTML_DIR/ku-phumpanya-app" ] && [ -f "$APP_DIR/artisan" ]; then
  if [ ! -d "$HTML_DIR/ku-phumpanya-app" ] || [ "$APP_DIR" = "$HOME/ku-phumpanya" ]; then
    mkdir -p "$HTML_DIR"
    rm -rf "$HTML_DIR/ku-phumpanya-app"
    mv "$APP_DIR" "$HTML_DIR/ku-phumpanya-app"
    APP_DIR="$HTML_DIR/ku-phumpanya-app"
    ln -sfn "$APP_DIR" "$HOME/ku-phumpanya"
  fi
fi

echo "==> .env"
if [ ! -f "$ENV_FILE" ]; then
  cp "$APP_DIR/.env.ku.production.example" "$ENV_FILE"
fi

if [ -z "${KU_MYSQL_PASSWORD:-}" ]; then
  echo "ERROR: set KU_MYSQL_PASSWORD before running this script"
  exit 1
fi

if ! grep -q '^APP_KEY=base64:' "$ENV_FILE" 2>/dev/null; then
  cd "$APP_DIR"
  php artisan key:generate --force
fi

# shellcheck disable=SC2016
if [ -n "${KU_MYSQL_PASSWORD:-}" ]; then
  awk -v pw="$KU_MYSQL_PASSWORD" 'BEGIN{found=0} /^DB_PASSWORD=/{print "DB_PASSWORD=" pw; found=1; next} {print} END{if(!found) print "DB_PASSWORD=" pw}' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
fi
perl -pi -e 's|^APP_URL=.*|APP_URL=https://phumpanya.ku.ac.th|' "$ENV_FILE"
perl -pi -e 's/^ELASTICSEARCH_ENABLED=.*/ELASTICSEARCH_ENABLED=false/' "$ENV_FILE"

echo "==> permissions"
cd "$APP_DIR"
chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || chmod -R u+rwX storage bootstrap/cache
if getent group webadmin >/dev/null 2>&1; then
  chgrp -R webadmin storage bootstrap/cache 2>/dev/null || true
fi
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache

echo "==> web-readable app link (open_basedir)"
ln -sfn "$APP_DIR" "$HTML_DIR/ku-phumpanya-app" 2>/dev/null || true

echo "==> migrate + seed"
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

echo "==> web root $HTML_DIR"
mkdir -p "$HTML_DIR"
# backup existing html once
if [ -d "$HTML_DIR" ] && [ "$(ls -A "$HTML_DIR" 2>/dev/null)" ]; then
  BACKUP="$HOME/html-backup-$(date +%Y%m%d%H%M%S)"
  cp -a "$HTML_DIR" "$BACKUP"
  echo "Backed up html to $BACKUP"
fi

# Copy static assets from public/ (keep custom index.php)
rsync -a --exclude='index.php' "$APP_DIR/public/" "$HTML_DIR/" 2>/dev/null || {
  cp -a "$APP_DIR/public/." "$HTML_DIR/"
  rm -f "$HTML_DIR/index.php"
}

# KU entry: index.php — app must be inside html (open_basedir)
cp "$APP_DIR/deploy/ku-vm/html-index.php" "$HTML_DIR/index.php"
cp "$APP_DIR/deploy/ku-vm/htaccess.minimal" "$HTML_DIR/.htaccess" 2>/dev/null || true
rm -f "$APP_DIR/.htaccess"
find "$HTML_DIR" -name '._*' -delete 2>/dev/null || true

echo "==> optimize"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize 2>/dev/null || true

echo ""
echo "==> Deploy complete"
echo "URL: https://phumpanya.ku.ac.th"
echo "Login: student@phumpanya.test / password"
echo "Admin: admin@phumpanya.test / password → /admin"
echo "Logs: $APP_DIR/storage/logs/laravel.log"
