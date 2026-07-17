#!/bin/bash
# scripts/0.sh - Level 1 Challenge Setup
# Arg 1: Container ID
# Arg 2: Flag
set -euo pipefail

CONTAINER_ID="${1:-unknown}"
FLAG="${2:-FLAG{dungeon_basics_unlocked}}"

echo "[*] Setting up Level 1 (Base) inside container..."
echo "[*] Container ID: ${CONTAINER_ID}"

# Create player user if it doesn't exist
if ! id -u player >/dev/null 2>&1; then
    useradd -m -s /bin/bash player
    echo "player:player123" | chpasswd
fi

# 1. Store flag in /root/0 with root permissions
mkdir -p /root/0
chmod 700 /root/0
echo "${FLAG}" > /root/0/flag.txt
chown root:root /root/0/flag.txt
chmod 600 /root/0/flag.txt

# 2. Create the dungeon/base directory
mkdir -p /home/player/dungeon/base
chown -R player:player /home/player/dungeon

# 3. Place SUID cat_helper in base directory
cp /bin/cat /home/player/dungeon/base/cat_helper
chown root:root /home/player/dungeon/base/cat_helper
chmod 4755 /home/player/dungeon/base/cat_helper

echo "[+] Level 1 Setup complete."
