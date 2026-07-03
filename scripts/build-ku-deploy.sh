#!/usr/bin/env bash
# Build production tarball for KU VM deploy (Laravel mock, no dev deps).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUTPUT="${1:-/tmp/ku-phumpanya-deploy.tgz}"

echo "==> composer install --no-dev ..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> npm ci && npm run build ..."
if command -v npm >/dev/null 2>&1; then
  npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts
  npm run build
else
  echo "WARN: npm not found — skip frontend build (upload public/build from CI)"
fi

echo "==> packaging to $OUTPUT ..."
tar czf "$OUTPUT" \
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
