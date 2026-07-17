#!/bin/bash
# scripts/1.sh - Level 2 Challenge Setup
# Arg 1: Container ID
# Arg 2: Flag
set -euo pipefail

CONTAINER_ID="${1:-unknown}"
FLAG="${2:-FLAG{dungeon_intermediate_pass}}"

echo "[*] Setting up Level 2 (Intermediate) inside container..."
echo "[*] Container ID: ${CONTAINER_ID}"

# Create player user if it doesn't exist
if ! id -u player >/dev/null 2>&1; then
    useradd -m -s /bin/bash player
    echo "player:player123" | chpasswd
fi

# 1. Store flag in /root/1 with root permissions
mkdir -p /root/1
chmod 700 /root/1
echo "${FLAG}" > /root/1/flag.txt
chown root:root /root/1/flag.txt
chmod 600 /root/1/flag.txt

# 2. Create the dungeon/intermediate directory
mkdir -p /home/player/dungeon/intermediate
chown -R player:player /home/player/dungeon

# 3. Place SUID find_helper in intermediate directory
cp /usr/bin/find /home/player/dungeon/intermediate/find_helper
chown root:root /home/player/dungeon/intermediate/find_helper
chmod 4755 /home/player/dungeon/intermediate/find_helper

echo "[+] Level 2 Setup complete."
