#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -L public/storage ]; then
  php artisan storage:link --force 2>/dev/null || true
fi

# Wait for MySQL when DB_HOST is set (compose network).
if [ -n "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
  echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
  for i in $(seq 1 60); do
    if php -r '
      try {
        new PDO(
          sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "mysql", getenv("DB_PORT") ?: "3306"),
          getenv("DB_USERNAME") ?: "root",
          getenv("DB_PASSWORD") ?: ""
        );
        exit(0);
      } catch (Throwable $e) {
        exit(1);
      }
    '; then
      echo "MySQL ready"
      break
    fi
    sleep 2
    if [ "$i" -eq 60 ]; then
      echo "ERROR: MySQL not ready"
      exit 1
    fi
  done
fi

php artisan config:clear --no-interaction 2>/dev/null || true

# Optional first-boot migrate (Hostinger Docker Manager). Prefer deploy-service.sh normally.
if [ "${RUN_MIGRATIONS_ON_START:-false}" = "true" ]; then
  echo "Running migrations (RUN_MIGRATIONS_ON_START=true)..."
  php artisan migrate --force --no-interaction
fi

exec "$@"
