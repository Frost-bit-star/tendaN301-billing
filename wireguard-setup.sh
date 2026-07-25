#!/bin/bash
# ==========================================
# Jasiri WiFi - WireGuard Server Setup
# Run as root: sudo bash wireguard-setup.sh
# ==========================================

set -e

WG_INTERFACE="wg0"
WG_ADDRESS="10.100.0.1/24"
WG_PORT="13231"
WG_SUBNET="10.100.0.0/24"
WG_DIR="/etc/wireguard"
WG_CONF="$WG_DIR/$WG_INTERFACE.conf"
WG_KEYS_DIR="$WG_DIR/keys"

echo "=== Jasiri WiFi WireGuard Setup ==="

# Generate server keys if not present
mkdir -p "$WG_KEYS_DIR"
chmod 700 "$WG_KEYS_DIR"

if [ ! -f "$WG_KEYS_DIR/server.key" ]; then
    echo "[1/6] Generating server WireGuard keys..."
    wg genkey | tee "$WG_KEYS_DIR/server.key" | wg pubkey > "$WG_KEYS_DIR/server.key.pub"
    chmod 600 "$WG_KEYS_DIR/server.key"
    chmod 644 "$WG_KEYS_DIR/server.key.pub"
    echo "  Keys generated and saved to $WG_KEYS_DIR/"
else
    echo "[1/6] Server keys already exist, skipping generation."
fi

SERVER_PRIVKEY=$(cat "$WG_KEYS_DIR/server.key")
SERVER_PUBKEY=$(cat "$WG_KEYS_DIR/server.key.pub")

echo "  Server public key: $SERVER_PUBKEY"

# Save public key for the PHP app to read
echo "$SERVER_PUBKEY" > /home/jackal/tendaN301-billing/db/server_wg_pubkey.txt
chmod 644 /home/jackal/tendaN301-billing/db/server_wg_pubkey.txt
echo "  Public key saved to db/server_wg_pubkey.txt"

# Create wg0.conf
echo "[2/6] Creating $WG_CONF..."
cat > "$WG_CONF" <<EOF
[Interface]
Address = $WG_ADDRESS
ListenPort = $WG_PORT
PrivateKey = $SERVER_PRIVKEY

# NAT: allow WireGuard clients to reach the internet through this server
PostUp = iptables -t nat -A POSTROUTING -s $WG_SUBNET -o wlp3s0 -j MASQUERADE
PostUp = iptables -A FORWARD -i $WG_INTERFACE -j ACCEPT
PostUp = iptables -A FORWARD -o $WG_INTERFACE -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -s $WG_SUBNET -o wlp3s0 -j MASQUERADE
PostDown = iptables -D FORWARD -i $WG_INTERFACE -j ACCEPT
PostDown = iptables -D FORWARD -o $WG_INTERFACE -j ACCEPT
EOF

chmod 600 "$WG_CONF"
echo "  Config created."

# Enable IP forwarding
echo "[3/6] Enabling IP forwarding..."
cat > /etc/sysctl.d/99-wireguard.conf <<EOF
net.ipv4.ip_forward = 1
EOF
sysctl -p /etc/sysctl.d/99-wireguard.conf
echo "  IP forwarding enabled."

# Allow UDP port 13231 through UFW if active
echo "[4/6] Configuring firewall..."
if command -v ufw &> /dev/null && ufw status | grep -q "active"; then
    ufw allow $WG_PORT/udp comment "WireGuard VPN"
    ufw reload
    echo "  UFW: allowed port $WG_PORT/udp"
else
    echo "  UFW not active, skipping. Ensure port $WG_PORT/udp is reachable."
fi

# Start WireGuard
echo "[5/6] Starting $WG_INTERFACE..."
systemctl enable wg-quick@$WG_INTERFACE
systemctl restart wg-quick@$WG_INTERFACE
echo "  $WG_INTERFACE started and enabled on boot."

# Verify
echo "[6/6] Verifying..."
echo ""
echo "=== Status ==="
wg show $WG_INTERFACE
echo ""
ip addr show $WG_INTERFACE
echo ""
echo "=== Done ==="
echo "Server WireGuard public key: $SERVER_PUBKEY"
echo "Listen port: $WG_PORT/UDP"
echo "Client subnet: $WG_SUBNET"
echo ""
echo "MikroTik routers will connect as peers on $WG_SUBNET."
echo "Peers are added automatically by the Jasiri API when devices register."
