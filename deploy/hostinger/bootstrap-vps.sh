#!/usr/bin/env bash
# First-time VPS bootstrap for Phumpanya (run on server as root or deploy user).
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/phumpanya}"
COMPOSE_FILE="$APP_DIR/compose.production.yml"

echo "==> preflight"
command -v docker >/dev/null || { echo "ERROR: docker missing"; exit 1; }
docker compose version >/dev/null || { echo "ERROR: docker compose missing"; exit 1; }

mkdir -p "$APP_DIR/backups"
cd "$APP_DIR"

if [ ! -f .env ]; then
  echo "ERROR: $APP_DIR/.env missing — copy from .env.production.example and fill secrets"
  exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "ERROR: $COMPOSE_FILE missing"
  exit 1
fi

# Ensure docker is available.
# Traefik on Hostinger uses network_mode:host + docker provider labels (no shared network required).
TRAEFIK_NETWORK="$(grep -E '^TRAEFIK_NETWORK=' .env | cut -d= -f2- || true)"
TRAEFIK_NETWORK="${TRAEFIK_NETWORK:-traefik}"
# legacy: create network only if compose still references it
if grep -q 'external: true' "$COMPOSE_FILE" 2>/dev/null; then
  if ! docker network inspect "$TRAEFIK_NETWORK" >/dev/null 2>&1; then
    echo "==> create docker network $TRAEFIK_NETWORK"
    docker network create "$TRAEFIK_NETWORK" || true
  fi
fi
if [ -n "${GHCR_PULL_TOKEN:-}" ]; then
  echo "==> docker login ghcr.io"
  echo "$GHCR_PULL_TOKEN" | docker login ghcr.io -u "${GHCR_PULL_USER:-$USER}" --password-stdin
fi

echo "==> pull images"
docker compose -f "$COMPOSE_FILE" pull

echo "==> start core services (Traefik off for parallel smoke — set TRAEFIK_ENABLE=false in .env)"
docker compose -f "$COMPOSE_FILE" up -d mysql elasticsearch
docker compose -f "$COMPOSE_FILE" up -d laravel frontend gateway

echo "==> wait for health"
for i in $(seq 1 60); do
  if docker compose -f "$COMPOSE_FILE" exec -T laravel curl -fsS http://127.0.0.1/up >/dev/null 2>&1; then
    break
  fi
  sleep 3
done

echo "==> migrate (no auto seed)"
docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan migrate --force --no-interaction
docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan config:cache

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
  echo "==> seed demo (SEED_DEMO_DATA=true)"
  docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan db:seed --force --no-interaction
fi

echo "==> elasticsearch snapshot restore"
docker compose -f "$COMPOSE_FILE" --profile init run --rm search-init

echo "==> bootstrap complete"
echo "Internal smoke: curl -fsS http://127.0.0.1:18080/up  (if ports mapped)"
echo "Then enable TRAEFIK_ENABLE=true and recreate gateway; stop sala-panya project."
