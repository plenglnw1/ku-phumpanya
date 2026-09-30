#!/usr/bin/env bash
# Local orchestrator: build tarball + upload + remote setup on KU VM.
#
# Prerequisites:
#   ssh ppyku@phumpanya.ku.ac.th   (password or ssh key)
#
# Usage:
#   export KU_MYSQL_PASSWORD='your-mysql-password'   # optional when DB_PASSWORD already exists on KU
#   bash scripts/deploy-ku-vm.sh
#
# Optional:
#   KU_SSH_HOST=ppyku@phumpanya.ku.ac.th
#   KU_TARBALL=/tmp/ku-phumpanya-deploy.tgz
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

KU_SSH_HOST="${KU_SSH_HOST:-ppyku@phumpanya.ku.ac.th}"
TARBALL="${KU_TARBALL:-/tmp/ku-phumpanya-deploy.tgz}"

bash "$ROOT/scripts/build-ku-deploy.sh" "$TARBALL"

echo "==> upload tarball + remote setup script"
echo "    (enter SSH password when prompted)"
scp "$TARBALL" "$ROOT/scripts/ku-vm-remote-setup.sh" "$KU_SSH_HOST:~/"

echo "==> run remote setup"
# Do not export empty OAuth vars. Laravel dotenv will not override them, so
# config:cache would freeze a blank client id over the server .env.
ssh "$KU_SSH_HOST" bash -s <<REMOTE
export KU_MYSQL_PASSWORD='$(printf '%s' "${KU_MYSQL_PASSWORD:-}" | sed "s/'/'\\\\''/g")'
export KU_FRONTEND_URL='$(printf '%s' "${KU_FRONTEND_URL:-https://phumpanya.ku.ac.th}" | sed "s/'/'\\\\''/g")'
if [ -n '${GOOGLE_CLIENT_ID:-}' ]; then
  export GOOGLE_CLIENT_ID='$(printf '%s' "${GOOGLE_CLIENT_ID:-}" | sed "s/'/'\\\\''/g")'
fi
if [ -n '${GOOGLE_CLIENT_SECRET:-}' ]; then
  export GOOGLE_CLIENT_SECRET='$(printf '%s' "${GOOGLE_CLIENT_SECRET:-}" | sed "s/'/'\\\\''/g")'
fi
export GOOGLE_REDIRECT_URI='$(printf '%s' "${GOOGLE_REDIRECT_URI:-https://phumpanya.ku.ac.th/auth/google/callback}" | sed "s/'/'\\\\''/g")'
bash ~/ku-vm-remote-setup.sh
REMOTE

echo "==> smoke test"
curl -sS -o /dev/null -w "GET / → HTTP %{http_code}\n" "https://phumpanya.ku.ac.th/" || true
curl -sS -o /dev/null -w "GET /login → HTTP %{http_code}\n" "https://phumpanya.ku.ac.th/login" || true
curl -sS -o /dev/null -w "GET /learn/ → HTTP %{http_code}\n" "https://phumpanya.ku.ac.th/learn/" || true

echo "Done."
