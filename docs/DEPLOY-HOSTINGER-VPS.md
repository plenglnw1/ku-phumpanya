# Deploy Phumpanya on Hostinger VPS

Domain: `plenglnw1.tech` / `www.plenglnw1.tech` → `101.44.57.113`

Stack: Traefik (Hostinger) → Nginx gateway → Next static + Laravel + MySQL 8.4 + Elasticsearch 8.19.1

## Architecture

- Frontend image: `ghcr.io/chissanupun/ku-bcg-frontend`
- Search-init image: `ghcr.io/chissanupun/ku-bcg-search-init`
- Laravel image: `ghcr.io/plenglnw1/ku-phumpanya`
- Compose: [`docker-compose.yaml`](../docker-compose.yaml) (Hostinger Docker Manager) and [`deploy/hostinger/compose.production.yml`](./compose.production.yml) (server copy under `/opt/phumpanya`)

Search: Elasticsearch keyword (`multi_match`). `QDRANT_ENABLED=false`. No queue/scheduler containers.

## Secrets checklist (Hostinger Environment / `/opt/phumpanya/.env`)

Copy from [`.env.production.example`](./.env.production.example):

| Variable | Notes |
|----------|-------|
| `APP_KEY` | `php artisan key:generate --show` |
| `DB_PASSWORD` / `MYSQL_ROOT_PASSWORD` | Strong unique values |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Redirect: `https://plenglnw1.tech/auth/google/callback` |
| `FRONTEND_IMAGE` / `LARAVEL_IMAGE` / `SEARCH_INIT_IMAGE` | Prefer SHA tags after CI |
| `TRAEFIK_ENABLE` | `false` for parallel smoke; `true` after cutover |
| `SEED_DEMO_DATA` | Keep `false` in production |

## GitHub secrets (both repos)

| Secret | Value |
|--------|-------|
| `VPS_HOST` | `101.44.57.113` |
| `VPS_USER` | deploy user with Docker |
| `VPS_SSH_KEY` | private key |
| `VPS_APP_DIR` | `/opt/phumpanya` (optional) |
| `GHCR_PULL_USER` / `GHCR_PULL_TOKEN` | if GHCR packages private |

## First deploy (parallel, Sala-Panya still up)

1. Reauthenticate Hostinger MCP; list VM + projects; note Sala-Panya project name.
2. Backup Sala-Panya DB/volumes; **do not delete**.
3. On VPS:

```bash
sudo mkdir -p /opt/phumpanya/backups
# copy compose.production.yml, gateway.conf, deploy-service.sh, bootstrap-vps.sh, .env
cd /opt/phumpanya
cp .env.production.example .env   # fill secrets
# parallel smoke: TRAEFIK_ENABLE=false and uncomment ports 127.0.0.1:18080:80 in compose
bash bootstrap-vps.sh
curl -fsS http://127.0.0.1:18080/up
curl -fsS http://127.0.0.1:18080/learn/
```

4. Stop Sala-Panya via Hostinger MCP (`VPS_stopProjectV1`) — preserve volumes.
5. Set `TRAEFIK_ENABLE=true`, recreate gateway, remove temporary 18080 bind.
6. Verify:

```bash
curl -fsSI https://plenglnw1.tech/up
curl -fsSI https://plenglnw1.tech/learn/
curl -fsSI https://plenglnw1.tech/admin
```

7. Update Google OAuth console redirect URI before login tests.

## CI/CD

| Repo | Workflow | Trigger |
|------|----------|---------|
| `KU-BCG` | `.github/workflows/deploy-vps.yml` | push `main` |
| `ku-phumpanya` | `.github/workflows/deploy-vps.yml` | push `main` |
| `ku-phumpanya` | `.github/workflows/deploy-ku.yml` | **manual only** (KU shared host) |

Each workflow builds immutable `:sha` images to GHCR, SSH-deploys via `deploy-service.sh` (flock + health check + rollback tag).

## Routine deploy

Push to `main` in either repo. Or on VPS:

```bash
cd /opt/phumpanya
./deploy-service.sh laravel ghcr.io/plenglnw1/ku-phumpanya:<sha>
./deploy-service.sh frontend ghcr.io/chissanupun/ku-bcg-frontend:<sha>
```

## Rollback

```bash
# App image rollback (deploy-service auto-rolls on health fail)
./deploy-service.sh laravel ghcr.io/plenglnw1/ku-phumpanya:<previous-sha>

# Full site rollback to Sala-Panya
# Traefik: disable phumpanya labels / stop phumpanya gateway
# Hostinger MCP: VPS_startProjectV1 for preserved sala-panya project
```

## Elasticsearch reindex

```bash
cd /opt/phumpanya
docker compose --profile init run --rm search-init
```

Idempotent: create missing indices only; upsert snapshot docs by stable id.

## Backups

- `deploy-service.sh` dumps MySQL to `./backups/` before Laravel recreate
- Hostinger weekly VPS backup remains second layer
- Elasticsearch data volume: `elasticsearch_data`

## Hostinger MCP cutover status

Live Hostinger API calls currently return `Unauthenticated` even after `mcp_auth` succeeds. Until MCP works:

1. Use Hostinger panel Docker Manager for list/stop/start projects.
2. Follow **First deploy** steps above via SSH.
3. Do **not** delete Sala-Panya — only Stop.

When MCP works again:

```text
VPS_getVirtualMachinesV1 → note virtualMachineId
VPS_getProjectListV1 → confirm sala-panya project name
VPS_getProjectContentsV1 → backup compose
# parallel deploy phumpanya with TRAEFIK_ENABLE=false
VPS_stopProjectV1(sala-panya)  # preserve volumes
# enable Traefik labels on phumpanya; update/start project
DNS_getDNSRecordsV1(plenglnw1.tech)  # verify A/CNAME
```

## Verify after cutover

| Check | Expect |
|-------|--------|
| `https://plenglnw1.tech/up` | 200 |
| `https://plenglnw1.tech/learn/` | Next SPA HTML |
| `https://plenglnw1.tech/admin` | Filament login |
| Google OAuth | callback on same host |
| `POST /api/search` | ES keyword results (Qdrant off) |
