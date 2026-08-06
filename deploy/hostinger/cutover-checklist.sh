#!/usr/bin/env bash
# Operator checklist for Hostinger cutover (print-only; does not mutate).
set -euo pipefail

cat <<'EOF'
Phumpanya Hostinger cutover checklist
=====================================

DNS: plenglnw1.tech → 187.127.110.209  (done if dig shows that IP)

YOU must do:
[ ] 1. Commit + push ku-phumpanya (gateway image + compose) to main
[ ] 2. GHCR: make ku-phumpanya + ku-phumpanya-gateway Public (or VPS docker login)
[ ] 3. Fill .env secrets: APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, Google OAuth
[ ] 4. Google OAuth redirect = https://plenglnw1.tech/auth/google/callback
[ ] 5. Optional CI secrets: VPS_HOST=187.127.110.209, VPS_USER, VPS_SSH_KEY

Agent / panel:
[ ] 6. VPS snapshot (overwrite existing) before cutover
[ ] 7. Stop sala-panya (preserve volumes) — free RAM
[ ] 8. Docker Manager: create project phumpanya from github.com/plenglnw1/ku-phumpanya
[ ] 9. search-init once; verify /up /learn/ /admin
[ ] 10. Observation window; keep sala-panya stopped for rollback

EOF
