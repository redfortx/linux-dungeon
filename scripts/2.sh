#!/bin/bash
# scripts/2.sh - Level 3 Challenge Setup
# Arg 1: Container ID
# Arg 2: Flag
set -euo pipefail

CONTAINER_ID="${1:-unknown}"
FLAG="${2:-FLAG{dungeon_advanced_master}}"

echo "[*] Setting up Level 3 (Advanced) inside container..."
echo "[*] Container ID: ${CONTAINER_ID}"

# Create player user if it doesn't exist
if ! id -u player >/dev/null 2>&1; then
    useradd -m -s /bin/bash player
    echo "player:player123" | chpasswd
fi

# 1. Store flag in /root/2 with root permissions
mkdir -p /root/2
chmod 700 /root/2
echo "${FLAG}" > /root/2/flag.txt
chown root:root /root/2/flag.txt
chmod 600 /root/2/flag.txt

# 2. Kill any old python services running on port 9999
pkill -f "python3 /root/2/service.py" || true

# 3. Create the background socket service script
cat << 'EOF' > /root/2/service.py
import socket
import sys
import time

try:
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind(('127.0.0.1', 9999))
    server.listen(5)

    flag_val = "FLAG{dungeon_advanced_master}"
    if len(sys.argv) > 1:
        flag_val = sys.argv[1]

    while True:
        conn, addr = server.accept()
        conn.sendall(f"--- DOCKER REACTOR CORE ACCESS ---\nFLAG: {flag_val}\n".encode())
        conn.close()
except Exception as e:
    with open('/root/2/error.log', 'w') as f:
        f.write(str(e))
EOF

chown root:root /root/2/service.py
chmod 700 /root/2/service.py

# 4. Run the python background service as root in the background
nohup python3 /root/2/service.py "${FLAG}" >/dev/null 2>&1 &

echo "[+] Level 3 Setup complete. Background service listening on port 9999."
