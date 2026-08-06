#!/usr/bin/env bash
# Operator checklist for Hostinger cutover (print-only; does not mutate).
set -euo pipefail

cat <<'EOF'
Phumpanya Hostinger cutover checklist
=====================================

[ ] 1. GitHub: push KU-BCG main (static export + Dockerfiles + deploy-vps.yml)
[ ] 2. GitHub: push ku-phumpanya main (Dockerfile + compose + deploy-vps.yml)
[ ] 3. GitHub secrets on both repos: VPS_HOST, VPS_USER, VPS_SSH_KEY, GHCR_PULL_*
[ ] 4. GHCR packages public OR VPS docker login works
[ ] 5. Google OAuth redirect = https://plenglnw1.tech/auth/google/callback
[ ] 6. Backup Sala-Panya (panel snapshot + DB dump) — DO NOT DELETE
[ ] 7. /opt/phumpanya: .env from .env.production.example (TRAEFIK_ENABLE=false)
[ ] 8. bootstrap-vps.sh → curl http://127.0.0.1:18080/up
[ ] 9. search-init profile ran; ES has ku_bcg_documents
[ ] 10. Stop sala-panya in Docker Manager (preserve volumes)
[ ] 11. TRAEFIK_ENABLE=true; recreate gateway
[ ] 12. Verify https://plenglnw1.tech/{up,learn/,admin}
[ ] 13. Observation window; keep sala-panya stopped for rollback

Hostinger MCP note: API may return Unauthenticated — use panel if MCP blocked.
EOF
