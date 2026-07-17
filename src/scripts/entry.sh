#!/bin/bash
# src/scripts/entry.sh - Container bootstrap
set -euo pipefail

# Start apache in the background
apache2-foreground &

echo "========================================"
echo "      LONE APEX: LINUX DUNGEON          "
echo "========================================"
echo "[*] Web application server listening on port 8080."
echo "[*] Active Tier: ${APEX_TIER:-free}"

# Ensure player user exists
if ! id -u player >/dev/null 2>&1; then
    useradd -m -s /bin/bash player
    echo "player:player123" | chpasswd
fi

# Keep container open
exec sleep infinity
