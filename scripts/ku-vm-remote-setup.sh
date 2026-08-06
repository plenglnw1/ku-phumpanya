#!/usr/bin/env bash
# Run ON KU VM after uploading ku-phumpanya-deploy.tgz to ~/
# Usage:
#   export KU_MYSQL_PASSWORD='your-mysql-password'
#   bash ku-vm-remote-setup.sh
#
# Optional:
#   KU_DEPLOY_MODE=demo   — force QDRANT/GEMINI off (Mode A)
#   KU_DEPLOY_MODE=ske    — leave Qdrant/Gemini env as-is (Mode B)
#   (unset)               — disable AI only when cloud keys are absent
set -euo pipefail

HTML_DIR="${KU_HTML_DIR:-$HOME/html}"
APP_DIR="${KU_APP_DIR:-$HTML_DIR/ku-phumpanya-app}"
TARBALL="${KU_TARBALL:-$HOME/ku-phumpanya-deploy.tgz}"
ENV_FILE="$APP_DIR/.env"

fix_broken_app_symlink() {
  if [ ! -L "$APP_DIR" ]; then
    return 0
  fi

  local target
  target="$(readlink "$APP_DIR" 2>/dev/null || true)"
  if [ -z "$target" ] || [ "$target" = "$APP_DIR" ] || [ "$target" = "ku-phumpanya-app" ]; then
    echo "WARN: removing broken symlink $APP_DIR"
    rm -f "$APP_DIR"
    return 0
  fi

  if ! readlink -f "$APP_DIR" >/dev/null 2>&1; then
    echo "WARN: removing dangling symlink $APP_DIR -> $target"
    rm -f "$APP_DIR"
  fi
}

backup_existing_env() {
  ENV_BACKUP=""
  fix_broken_app_symlink

  if [ ! -f "$APP_DIR/.env" ]; then
    return 0
  fi

  ENV_BACKUP="$(mktemp)"
  if ! cp "$APP_DIR/.env" "$ENV_BACKUP" 2>/dev/null; then
    rm -f "$ENV_BACKUP"
    ENV_BACKUP=""
    echo "WARN: could not backup existing .env (skipped)"
  fi
}

env_get() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n 1 || true)"
  if [ -z "$line" ]; then
    echo ""
    return 0
  fi
  echo "${line#*=}" | sed -e 's/^["'\'' ]*//' -e 's/["'\'' ]*$//'
}

env_set() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
    awk -v k="$key" -v v="$value" 'BEGIN{found=0} $0 ~ "^" k "=" {print k "=" v; found=1; next} {print} END{if(!found) print k "=" v}' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

apply_ku_env_baseline() {
  local deploy_mode="${KU_DEPLOY_MODE:-}"
  local qdrant_host qdrant_key qdrant_embed gemini_key

  env_set "ELASTICSEARCH_ENABLED" "false"
  env_set "APP_URL" "https://phumpanya.ku.ac.th"

  # Same-domain Next static: FRONTEND_URL defaults to APP_URL (OAuth → /learn/)
  # Skip overwrite when already set to an external origin (optional Vercel FE).
  frontend_url="$(env_get FRONTEND_URL)"
  if [ -z "$frontend_url" ] || [ "$frontend_url" = "http://localhost:3000" ]; then
    env_set "FRONTEND_URL" "https://phumpanya.ku.ac.th"
  fi

  if ! grep -q '^SANCTUM_STATEFUL_DOMAINS=' "$ENV_FILE" 2>/dev/null; then
    env_set "SANCTUM_STATEFUL_DOMAINS" "phumpanya.ku.ac.th,www.phumpanya.ku.ac.th"
  fi

  if [ "$deploy_mode" = "demo" ]; then
    env_set "QDRANT_ENABLED" "false"
    env_set "GEMINI_ENABLED" "false"
    return 0
  fi

  if [ "$deploy_mode" = "ske" ]; then
    return 0
  fi

  qdrant_host="$(env_get QDRANT_HOST)"
  qdrant_key="$(env_get QDRANT_API_KEY)"
  qdrant_embed="$(env_get QDRANT_EMBEDDING_URL)"
  gemini_key="$(env_get GEMINI_API_KEY)"

  if [ -z "$qdrant_host" ] || [ -z "$qdrant_embed" ] || [[ "$qdrant_host" == http://localhost* ]] || [[ "$qdrant_host" == http://127.0.0.1* ]]; then
    env_set "QDRANT_ENABLED" "false"
  fi

  if [ -z "$gemini_key" ]; then
    env_set "GEMINI_ENABLED" "false"
  fi
}

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
backup_existing_env
mkdir -p "$HTML_DIR"
if [ -d "$APP_DIR" ] && [ ! -L "$APP_DIR" ]; then
  rm -rf "$APP_DIR"
fi
fix_broken_app_symlink
mkdir -p "$APP_DIR"
tar xzf "$TARBALL" -C "$APP_DIR"
find "$APP_DIR" -name '._*' -delete 2>/dev/null || true

ln -sfn "$APP_DIR" "$HOME/ku-phumpanya"

ENV_FILE="$APP_DIR/.env"
if [ -n "$ENV_BACKUP" ] && [ -f "$ENV_BACKUP" ]; then
  cp "$ENV_BACKUP" "$ENV_FILE"
  rm -f "$ENV_BACKUP"
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
apply_ku_env_baseline

echo "==> permissions"
cd "$APP_DIR"
chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || chmod -R u+rwX storage bootstrap/cache
if getent group webadmin >/dev/null 2>&1; then
  chgrp -R webadmin storage bootstrap/cache 2>/dev/null || true
fi
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache

echo "==> migrate"
php artisan migrate --force --no-interaction

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
  echo "==> seed demo data (SEED_DEMO_DATA=true)"
  php artisan db:seed --force --no-interaction
else
  echo "==> skip db:seed (set SEED_DEMO_DATA=true for first demo bootstrap only)"
fi

echo "==> web root $HTML_DIR/public (KU Apache DOCUMENT_ROOT)"
PUBLIC_DIR="$HTML_DIR/public"
mkdir -p "$PUBLIC_DIR"

# Copy static assets from Laravel public/
rsync -a --exclude='index.php' "$APP_DIR/public/" "$PUBLIC_DIR/" 2>/dev/null || {
  cp -a "$APP_DIR/public/." "$PUBLIC_DIR/"
  rm -f "$PUBLIC_DIR/index.php"
}

cp "$APP_DIR/deploy/ku-vm/public-index.php" "$PUBLIC_DIR/index.php"
cp "$APP_DIR/deploy/ku-vm/htaccess.minimal" "$PUBLIC_DIR/.htaccess" 2>/dev/null || true
rm -f "$APP_DIR/.htaccess"
find "$HTML_DIR" "$APP_DIR" -name '._*' -delete 2>/dev/null || true

echo "==> optimize"
php artisan config:cache
php artisan route:clear
php artisan view:cache
php artisan filament:optimize 2>/dev/null || true

echo ""
echo "==> Deploy complete"
echo "URL: https://phumpanya.ku.ac.th"
echo "Login: student@phumpanya.test / password"
echo "Admin: admin@phumpanya.test / password → /admin"
echo "Logs: $APP_DIR/storage/logs/laravel.log"
