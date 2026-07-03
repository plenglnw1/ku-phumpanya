#!/usr/bin/env bash
# SSH recon only — run on KU VM: bash ku-vm-recon.sh
set -euo pipefail

echo "==> PHP"
php -v
php -m | grep -E 'pdo_mysql|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|fileinfo' || echo "WARN: missing extensions"

echo "==> paths"
ls -la ~/html 2>/dev/null || echo "no ~/html"
ls -la ~/ku-phumpanya 2>/dev/null || echo "no ~/ku-phumpanya"

echo "==> disk"
df -h ~

echo "==> mysql"
if command -v mysql >/dev/null 2>&1; then
  mysql -u ppyku -p -e "SELECT VERSION(); SHOW TABLES;" ppyku || true
else
  echo "mysql CLI not in PATH"
fi

echo "==> composer"
which composer 2>/dev/null || echo "no composer — vendor uploaded in tarball"
