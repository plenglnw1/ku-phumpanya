# KU VM Deploy — Laravel Mock on phumpanya.ku.ac.th

Deploy package: [`dist/ku-phumpanya-deploy.tgz`](dist/ku-phumpanya-deploy.tgz) (~27–54MB)

## Quick deploy (from your Mac)

```bash
cd ku-phumpanya

# 1) Set MySQL password (from KU hosting panel — rotate if leaked)
export KU_MYSQL_PASSWORD='your-mysql-password'

# 2) One command (will prompt SSH password twice)
bash scripts/deploy-ku-vm.sh
```

## Manual deploy (FileZilla + SSH)

### Upload via SFTP (port 22)

Upload to `~/`:

- `dist/ku-phumpanya-deploy.tgz`
- `scripts/ku-vm-remote-setup.sh`

### SSH then run

```bash
ssh ppyku@phumpanya.ku.ac.th

export KU_MYSQL_PASSWORD='your-mysql-password'
bash ~/ku-vm-remote-setup.sh
```

## What the remote script does

1. PHP 8.3+ + extension check
2. Extract app → `~/ku-phumpanya/`
3. Create `.env` from `.env.ku.production.example`
4. `php artisan key:generate` (if needed)
5. `migrate --force` + `db:seed --force`
6. Copy `public/*` → `~/html/` + custom `index.php` (points to `~/ku-phumpanya`)
7. `config:cache` + `route:cache` + `view:cache`

## Demo logins

| Email | Password |
|-------|----------|
| `student@phumpanya.test` | `password` |
| `admin@phumpanya.test` | `password` → `/admin` |

## Server layout

```
~/ku-phumpanya/     # Laravel app (not web-visible)
~/html/             # Web root (index.php + assets)
~/html/index.php    # Entry → ../ku-phumpanya
```

## Config highlights

- `ELASTICSEARCH_ENABLED=false` — GraphRAG uses config fallback
- `DB_HOST=127.0.0.1` — MySQL on VM
- `SESSION_DRIVER=database` — no Redis needed

## 500 Internal Server Error (Apache generic page)

**Root cause on KU:** `open_basedir` limits PHP to `~/html` — app must be at `~/html/ku-phumpanya-app`, not `~/ku-phumpanya` alone.

**After a broken/partial fix, run recovery:**

```bash
# From Mac:
scp scripts/ku-vm-recover.sh ppyku@phumpanya.ku.ac.th:~/
ssh ppyku@phumpanya.ku.ac.th 'bash ~/ku-vm-recover.sh'
```

Do **not** paste multi-line shell blocks into an active SSH session — run the script only.

**Test order:**
1. https://phumpanya.ku.ac.th/hello.php → must show `PHP 8.4.x` and `autoload=OK`
2. https://phumpanya.ku.ac.th/ → Laravel home
3. `rm ~/html/hello.php` when done

```bash
tail -50 ~/html/ku-phumpanya-app/storage/logs/laravel.log
php ~/html/ku-phumpanya-app/artisan config:clear
```

| Issue | Fix |
|-------|-----|
| hello.php 500 | `.htaccess` still broken — re-run `ku-vm-recover.sh` |
| hello.php OK, / 500 | Laravel error — check `storage/logs/laravel.log` |
| 404 routes | Ensure `~/html/.htaccess` exists (minimal version) |
| DB error | `DB_HOST=127.0.0.1`, verify MySQL password in `.env` |

## SSH key (optional — skip password prompt)

```bash
ssh-copy-id ppyku@phumpanya.ku.ac.th
```

## Rebuild package only

```bash
bash scripts/build-ku-deploy.sh dist/ku-phumpanya-deploy.tgz
```

## Security

- Rotate MySQL password after deploy if shared in chat
- Never commit `.env` to git
