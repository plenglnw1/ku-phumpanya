#!/usr/bin/env bash
# Build production tarball for KU VM deploy (Laravel + Next static SPA, no Node on server).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUTPUT="${1:-/tmp/ku-phumpanya-deploy.tgz}"
NEXT_ROOT="$(cd "$ROOT/../KU-BCG" 2>/dev/null && pwd || true)"
SKIP_NEXT="${KU_SKIP_NEXT_BUILD:-0}"

echo "==> composer install --no-dev ..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> npm ci && npm run build (Laravel Vite) ..."
if command -v npm >/dev/null 2>&1; then
  npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts
  npm run build
else
  echo "WARN: npm not found — skip Laravel Vite build"
fi

# Same-domain Next static export → Laravel public/ (Apache docroot on KU)
if [ "$SKIP_NEXT" = "1" ]; then
  echo "==> skip Next static (KU_SKIP_NEXT_BUILD=1)"
elif [ -z "$NEXT_ROOT" ] || [ ! -f "$NEXT_ROOT/package.json" ]; then
  echo "WARN: KU-BCG not found at $ROOT/../KU-BCG — skip Next static merge"
elif ! command -v npm >/dev/null 2>&1; then
  echo "WARN: npm not found — skip Next static merge"
else
  echo "==> Next static export (same-origin API) from $NEXT_ROOT ..."
  (
    cd "$NEXT_ROOT"
    npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts
    # Empty string = relative URLs (same domain). Do not omit var (would fall back to localhost).
    NEXT_PUBLIC_API_URL= npm run build
  )

  NEXT_OUT="$NEXT_ROOT/out"
  if [ ! -d "$NEXT_OUT" ]; then
    echo "ERROR: Next out/ missing after build ($NEXT_OUT)"
    exit 1
  fi

  echo "==> merge Next out/ → public/ (exclude admin/ + index.php)"
  # Prefer rsync; fall back to find+cp
  if command -v rsync >/dev/null 2>&1; then
    rsync -a \
      --exclude='admin' \
      --exclude='admin/' \
      --exclude='index.php' \
      "$NEXT_OUT/" "$ROOT/public/"
  else
    # Copy everything except admin tree and never overwrite Laravel index.php
    (
      cd "$NEXT_OUT"
      find . -path './admin' -prune -o -path './admin/*' -prune -o -type f -print
    ) | while IFS= read -r rel; do
      rel="${rel#./}"
      [ "$rel" = "index.php" ] && continue
      dest="$ROOT/public/$rel"
      mkdir -p "$(dirname "$dest")"
      cp "$NEXT_OUT/$rel" "$dest"
    done
  fi
fi

echo "==> packaging to $OUTPUT ..."
COPYFILE_DISABLE=1 tar czf "$OUTPUT" \
  --exclude=node_modules \
  --exclude=.git \
  --exclude=.env \
  --exclude='._*' \
  --exclude=.AppleDouble \
  --exclude=.env.local \
  --exclude=.env.neon.local \
  --exclude=.env.neon.production \
  --exclude=.env.production \
  --exclude=storage/logs \
  --exclude=.vercel \
  --exclude=api/php-fpm-bin \
  --exclude=api/laravel \
  -C "$ROOT" .

echo "==> Done: $OUTPUT ($(du -sh "$OUTPUT" | cut -f1))"
