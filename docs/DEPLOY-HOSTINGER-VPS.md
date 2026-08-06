# Deploy Phumpanya on Hostinger VPS

Domain: `plenglnw1.tech` / `www.plenglnw1.tech` → **`187.127.110.209`**

Stack: Traefik (Hostinger) → Nginx gateway → Next static + Laravel + MySQL 8.4 + Elasticsearch 8.19.1

## Architecture

- Frontend: `ghcr.io/chissanupun/ku-bcg-frontend`
- Search-init: `ghcr.io/chissanupun/ku-bcg-search-init`
- Laravel: `ghcr.io/plenglnw1/ku-phumpanya`
- Gateway: `ghcr.io/plenglnw1/ku-phumpanya-gateway`
- Compose: root [`docker-compose.yaml`](../docker-compose.yaml) (Docker Manager) or [`deploy/hostinger/compose.production.yml`](./compose.production.yml) (`/opt/phumpanya`)

Search: Elasticsearch keyword only. `QDRANT_ENABLED=false`. No queue/scheduler containers.

## What you must do (blocked without these)

| # | Action | Why |
|---|--------|-----|
| 1 | Commit + push `ku-phumpanya` gateway/compose fixes to `main` | Builds `ku-phumpanya` + `ku-phumpanya-gateway` images |
| 2 | Make GHCR package **`ku-phumpanya`** (and **`ku-phumpanya-gateway`**) **Public** — or `docker login` on VPS | Frontend packages already public; Laravel currently **403 private** |
| 3 | Fill Hostinger project Environment (see secrets table) | Compose refuses empty `DB_PASSWORD` / `MYSQL_ROOT_PASSWORD` |
| 4 | Google Cloud Console: redirect `https://plenglnw1.tech/auth/google/callback` | OAuth login |
| 5 | Optional CI: GitHub secrets `VPS_HOST=187.127.110.209`, `VPS_USER`, `VPS_SSH_KEY`, `GHCR_PULL_*` | Only needed for SSH auto-deploy after push |

## Secrets (Hostinger Environment panel)

Copy from [`.env.production.example`](./.env.production.example):

| Variable | Notes |
|----------|-------|
| `APP_KEY` | `php artisan key:generate --show` |
| `DB_PASSWORD` / `MYSQL_ROOT_PASSWORD` | Strong unique values |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Redirect above |
| `TRAEFIK_ENABLE` | `true` for live domain |
| `RUN_MIGRATIONS_ON_START` | `true` first Docker Manager boot |
| `SEED_DEMO_DATA` | Keep `false` |

## First deploy via Docker Manager (recommended)

1. Snapshot created before cutover (Hostinger MCP / panel).
2. Stop **sala-panya** (preserve volumes) — frees RAM for ES 2g. Do **not** delete.
3. After images exist on GHCR, create project `phumpanya`:
   - Content: `https://github.com/plenglnw1/ku-phumpanya` (uses root `docker-compose.yaml`)
   - Paste filled Environment from `.env.production.example`
4. Wait healthy; then run search-init once (SSH or Docker Manager exec):

```bash
docker compose --profile init run --rm search-init
```

5. Verify:

```bash
curl -fsSI https://plenglnw1.tech/up
curl -fsSI https://plenglnw1.tech/learn/
curl -fsSI https://plenglnw1.tech/admin
```

## Alternate: SSH bootstrap `/opt/phumpanya`

```bash
sudo mkdir -p /opt/phumpanya/backups
# copy compose.production.yml, deploy-service.sh, bootstrap-vps.sh, .env
cd /opt/phumpanya
bash bootstrap-vps.sh
```

## CI/CD

| Repo | Workflow | Trigger |
|------|----------|---------|
| `KU-BCG` | `deploy-vps.yml` | push `main` |
| `ku-phumpanya` | `deploy-vps.yml` | push `main` |
| `ku-phumpanya` | `deploy-ku.yml` | **manual only** |

## Rollback

- Image: `./deploy-service.sh laravel ghcr.io/plenglnw1/ku-phumpanya:<prev-sha>`
- Full: stop `phumpanya`, start preserved `sala-panya` (if still needed)

## Notes

- `listen2life.plenglnw1.tech` still points at `101.44.57.113` (unchanged).
- Sala-Panya was crash-looping; volumes kept on stop.
