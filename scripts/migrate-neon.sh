#!/usr/bin/env bash
# Run Laravel migrations + seeders against Neon (via Vercel env).
#
# Usage:
#   vercel link
#   bash scripts/migrate-neon.sh
#
# Optional: pass env file from `vercel env pull`
#   bash scripts/migrate-neon.sh .env.neon.production
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

ENV_FILE="${1:-.env.neon.production}"

if ! command -v vercel >/dev/null 2>&1; then
  echo "ERROR: Vercel CLI not found. Install: npm i -g vercel"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "==> Pulling production env from Vercel into $ENV_FILE ..."
  vercel env pull "$ENV_FILE" --environment=production --yes
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: Missing $ENV_FILE"
  exit 1
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"

# Migrations must use a direct (non-pooler) connection. PgBouncer breaks DDL in transactions.
# Override: MIGRATE_DB_URL='postgresql://...' bash scripts/migrate-neon.sh
if [[ -n "${MIGRATE_DB_URL:-}" ]]; then
  DB_URL="$MIGRATE_DB_URL"
elif [[ -n "${ku_phumpanya_POSTGRES_URL_NON_POOLING:-}" ]]; then
  DB_URL="$ku_phumpanya_POSTGRES_URL_NON_POOLING"
elif [[ -n "${ku_phumpanya_DATABASE_URL_UNPOOLED:-}" ]]; then
  DB_URL="$ku_phumpanya_DATABASE_URL_UNPOOLED"
elif [[ -n "${DB_URL:-}" ]] && [[ "$DB_URL" != *"pooler"* ]]; then
  : # DB_URL already direct
elif [[ -n "${DB_URL:-}" ]] && [[ "$DB_URL" == *"pooler"* ]]; then
  echo "WARN: DB_URL uses Neon pooler — migrations often fail (transaction aborted)."
  echo "      Set MIGRATE_DB_URL or ku_phumpanya_POSTGRES_URL_NON_POOLING (unpooled host, no -pooler)."
  if [[ -n "${ku_phumpanya_POSTGRES_URL:-}" ]] && [[ "$ku_phumpanya_POSTGRES_URL" != *"pooler"* ]]; then
    DB_URL="$ku_phumpanya_POSTGRES_URL"
  fi
fi

if [[ -z "${DB_URL:-}" ]]; then
  cat <<'EOF'
ERROR: No database URL for migrations.

Vercel "Sensitive" and Neon integration vars are often empty in `vercel env pull`.
Add on Vercel (Production) OR export before running:

  DB_CONNECTION=pgsql
  DB_URL=<pooler URL for the running app>
  MIGRATE_DB_URL=<unpooled URL — host WITHOUT "-pooler" in the name>

Unpooled URL is in Neon Console → Connection details → "Direct connection"
or ku_phumpanya_POSTGRES_URL_NON_POOLING from the Neon integration.

Then:
  vercel env pull .env.neon.production --environment=production --yes
  bash scripts/migrate-neon.sh
EOF
  exit 1
fi

if [[ "$DB_URL" == *"pooler"* ]]; then
  echo "ERROR: Still using pooler URL. Migrations require unpooled/direct connection."
  echo "       export MIGRATE_DB_URL='postgresql://...ep-....aws.neon.tech/neondb?sslmode=require'"
  exit 1
fi

if [[ -z "${APP_KEY:-}" ]]; then
  echo "ERROR: APP_KEY missing in $ENV_FILE (set on Vercel or run php artisan key:generate locally)."
  exit 1
fi

export DB_URL

# Empty BCRYPT_ROUNDS from vercel env pull breaks Hash::make() (cost becomes 0).
if [[ -z "${BCRYPT_ROUNDS:-}" ]]; then
  export BCRYPT_ROUNDS=12
fi

echo "==> Running migrations on Neon (pgsql)..."
php artisan migrate --force --no-interaction

echo "==> Seeding demo users + mock analytics..."
# Seeders must not rely on fakerphp/faker (not installed with --no-dev for Vercel).
if ! php -r 'exit(function_exists("fake") ? 0 : 1);' 2>/dev/null; then
  echo "WARN: fake() unavailable (dev deps missing). Seeders use static data only."
fi
php artisan db:seed --force --no-interaction

cat <<'EOF'

==> Done.

Demo accounts (password: password):
  admin@phumpanya.test      → /admin
  student@phumpanya.test    → /search
  researcher@phumpanya.test
  teacher@phumpanya.test
EOF
