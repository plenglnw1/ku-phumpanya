#!/usr/bin/env bash
# Deploy one service image SHA on the VPS. Run from deploy/hostinger directory.
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-compose.production.yml}"
LOCK_FILE="${LOCK_FILE:-/tmp/phumpanya-deploy.lock}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
SERVICE="${1:-}"
IMAGE_REF="${2:-}"

if [ -z "$SERVICE" ] || [ -z "$IMAGE_REF" ]; then
  echo "Usage: $0 <frontend|laravel|search-init> <image:tag>"
  exit 1
fi

mkdir -p "$BACKUP_DIR"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "ERROR: another deploy is running ($LOCK_FILE)"
  exit 1
fi

echo "==> deploy $SERVICE → $IMAGE_REF"

PREV_TAG_FILE=".current-${SERVICE}-image"
PREV_IMAGE=""
if [ -f "$PREV_TAG_FILE" ]; then
  PREV_IMAGE="$(cat "$PREV_TAG_FILE")"
fi

case "$SERVICE" in
  frontend)
    export FRONTEND_IMAGE="$IMAGE_REF"
    ;;
  laravel)
    export LARAVEL_IMAGE="$IMAGE_REF"
    if command -v docker >/dev/null 2>&1; then
      stamp="$(date +%Y%m%d%H%M%S)"
      echo "==> MySQL dump $BACKUP_DIR/mysql-$stamp.sql.gz"
      docker compose -f "$COMPOSE_FILE" exec -T mysql \
        sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
        | gzip -c > "$BACKUP_DIR/mysql-$stamp.sql.gz" || echo "WARN: dump failed (first deploy?)"
    fi
    ;;
  search-init)
    export SEARCH_INIT_IMAGE="$IMAGE_REF"
    ;;
  *)
    echo "Unknown service: $SERVICE"
    exit 1
    ;;
esac

rollback() {
  if [ -n "$PREV_IMAGE" ]; then
    echo "==> rollback $SERVICE → $PREV_IMAGE"
    case "$SERVICE" in
      frontend) export FRONTEND_IMAGE="$PREV_IMAGE" ;;
      laravel) export LARAVEL_IMAGE="$PREV_IMAGE" ;;
      search-init) export SEARCH_INIT_IMAGE="$PREV_IMAGE" ;;
    esac
    docker compose -f "$COMPOSE_FILE" pull "$SERVICE" || true
    docker compose -f "$COMPOSE_FILE" up -d --no-deps "$SERVICE" || true
  fi
}

trap 'echo ERROR; rollback; exit 1' ERR

docker compose -f "$COMPOSE_FILE" pull "$SERVICE"
docker compose -f "$COMPOSE_FILE" up -d --no-deps --force-recreate "$SERVICE"

if [ "$SERVICE" = "laravel" ]; then
  echo "==> migrate"
  docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan migrate --force --no-interaction
  docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan config:cache
  docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan route:clear
  docker compose -f "$COMPOSE_FILE" exec -T laravel php artisan view:cache
fi

if [ "$SERVICE" = "search-init" ]; then
  docker compose -f "$COMPOSE_FILE" --profile init run --rm search-init
fi

echo "==> health checks"
sleep 3
curl -fsS "http://127.0.0.1:18080/up" >/dev/null 2>&1 \
  || curl -fsS "https://${APP_HOST:-plenglnw1.tech}/up" >/dev/null
curl -fsS "http://127.0.0.1:18080/healthz" >/dev/null 2>&1 \
  || curl -fsS "https://${APP_HOST:-plenglnw1.tech}/" >/dev/null || true

echo "$IMAGE_REF" > "$PREV_TAG_FILE"
echo "==> deploy ok: $SERVICE"
