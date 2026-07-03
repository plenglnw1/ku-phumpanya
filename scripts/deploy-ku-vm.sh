#!/usr/bin/env bash
# Local orchestrator: build tarball + upload + remote setup on KU VM.
#
# Prerequisites:
#   ssh ppyku@phumpanya.ku.ac.th   (password or ssh key)
#
# Usage:
#   export KU_MYSQL_PASSWORD='your-mysql-password'
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

if [ -z "${KU_MYSQL_PASSWORD:-}" ]; then
  echo "ERROR: export KU_MYSQL_PASSWORD before deploy"
  exit 1
fi

bash "$ROOT/scripts/build-ku-deploy.sh" "$TARBALL"

echo "==> upload tarball + remote setup script"
echo "    (enter SSH password when prompted)"
scp "$TARBALL" "$ROOT/scripts/ku-vm-remote-setup.sh" "$KU_SSH_HOST:~/"

echo "==> run remote setup"
ssh "$KU_SSH_HOST" bash -s <<REMOTE
export KU_MYSQL_PASSWORD='$(printf '%s' "$KU_MYSQL_PASSWORD" | sed "s/'/'\\\\''/g")'
bash ~/ku-vm-remote-setup.sh
REMOTE

echo "==> smoke test"
curl -sS -o /dev/null -w "GET / → HTTP %{http_code}\n" "https://phumpanya.ku.ac.th/" || true
curl -sS -o /dev/null -w "GET /login → HTTP %{http_code}\n" "https://phumpanya.ku.ac.th/login" || true

echo "Done."
