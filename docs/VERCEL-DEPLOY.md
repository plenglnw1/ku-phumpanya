# Deploy Phumpanya on Vercel

## Neon (done locally)

```bash
export MIGRATE_DB_URL='<unpooled Neon URL>'
export BCRYPT_ROUNDS=12
bash scripts/migrate-neon.sh
```

## Vercel environment (Production)

| Variable | Value |
|----------|--------|
| `APP_KEY` | from `php artisan key:generate --show` |
| `APP_URL` | `https://ku-phumpanya.vercel.app` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | Neon **pooler** URL (`...-pooler....neon.tech`) |
| `BCRYPT_ROUNDS` | `12` |
| `ELASTICSEARCH_ENABLED` | `false` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `MAIL_MAILER` | `log` |

Do **not** set `REDIS_HOST=redis` unless you add Upstash + `predis/predis`.

`vercel.json` also sets session/cache to `database` (sessions live in Neon).

## Optional: Redis for heavy Filament (later)

1. Create [Upstash Redis](https://upstash.com)
2. `composer require predis/predis`
3. `bash scripts/pack.sh` and commit `api/vendor.tar.gz`
4. Set `REDIS_URL`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis` on Vercel

## Project settings

- Framework: **Other** (not Vite)
- Output Directory: empty or `public` — **not** `dist`
- Build: from `vercel.json` (`npm run build && bash scripts/vercel-prepare.sh`)

## HTTP 500 checklist

1. `DB_URL` is pooler URL and password is current
2. No `SESSION_DRIVER=redis` without `REDIS_URL`
3. `BCRYPT_ROUNDS=12` (not empty)
4. Redeploy after `vercel.json` changes
5. Temporarily `APP_DEBUG=true` to see error (turn off after)

## Demo logins

| Email | Password |
|-------|----------|
| `student@phumpanya.test` | `password` |
| `admin@phumpanya.test` | `password` |
