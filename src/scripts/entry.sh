#!/bin/bash
# src/scripts/entry.sh - Container bootstrap
set -euo pipefail

echo "========================================"
echo "      LONE APEX: LINUX DUNGEON          "
echo "========================================"
echo "[*] Web application server listening on port 8080."
echo "[*] Active Tier: ${APEX_TIER:-free}"

# Ensure player user exists
if ! id player >/dev/null 2>&1; then
    useradd -m -s /bin/bash player
    echo "player:player123" | chpasswd
fi

# Execute Apache in foreground as PID 1
exec apache2-foreground
