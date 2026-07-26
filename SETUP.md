# Jasiri WiFi - Server Setup Guide

Step-by-step guide for deploying the Jasiri WiFi billing system on a fresh Ubuntu server.

---

## Prerequisites

- Fresh Ubuntu 22.04/24.04 server (VPS or local)
- Root or sudo access
- A domain pointing to your server (e.g. `jasiri.stackverify.site`)
- Git installed

---

## Step 1: Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y git curl wget unzip ufw
```

---

## Step 2: Install PHP and Extensions

```bash
sudo apt install -y php php-cli php-sqlite3 php-curl php-mbstring php-xml php-zip php-gd
```

Verify:

```bash
php -v
php -m | grep -E "sqlite3|curl|mbstring"
```

---

## Step 3: Install WireGuard

WireGuard is used for secure management connections between MikroTik routers and this server.

```bash
sudo apt install -y wireguard wireguard-tools
```

Generate the server key pair and configure the interface:

```bash
# Generate server keys (stored securely in /etc/wireguard/keys/)
sudo mkdir -p /etc/wireguard/keys
sudo chmod 700 /etc/wireguard/keys

sudo wg genkey | sudo tee /etc/wireguard/keys/server.key | sudo wg pubkey | sudo tee /etc/wireguard/keys/server.key.pub
sudo chmod 600 /etc/wireguard/keys/server.key
```

Read the public key (you'll need it later):

```bash
cat /etc/wireguard/keys/server.key.pub
```

Create the WireGuard config:

```bash
SERVER_PRIVKEY=$(sudo cat /etc/wireguard/keys/server.key)

sudo tee /etc/wireguard/wg0.conf <<EOF
[Interface]
Address = 10.100.0.1/24
ListenPort = 13231
PrivateKey = $SERVER_PRIVKEY

# NAT: allow WireGuard clients to route through this server
PostUp = iptables -t nat -A POSTROUTING -s 10.100.0.0/24 -o eth0 -j MASQUERADE
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT
PostUp = iptables -A FORWARD -o wg0 -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -s 10.100.0.0/24 -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT
PostDown = iptables -D FORWARD -o wg0 -j ACCEPT
EOF

sudo chmod 600 /etc/wireguard/wg0.conf
```

> **Note:** Replace `eth0` with your main network interface. Check with `ip route | grep default`.

Enable IP forwarding and start WireGuard:

```bash
# Enable IP forwarding
echo "net.ipv4.ip_forward = 1" | sudo tee /etc/sysctl.d/99-wireguard.conf
sudo sysctl -p /etc/sysctl.d/99-wireguard.conf

# Start and enable on boot
sudo systemctl enable wg-quick@wg0
sudo systemctl start wg-quick@wg0
```

Verify:

```bash
sudo wg show
# Should show: interface: wg0, listening port: 13231

ip addr show wg0
# Should show: inet 10.100.0.1/24
```

---

## Step 4: Configure Firewall

```bash
sudo ufw allow 22/tcp comment "SSH"
sudo ufw allow 80/tcp comment "HTTP"
sudo ufw allow 443/tcp comment "HTTPS"
sudo ufw allow 13231/udp comment "WireGuard VPN"
sudo ufw enable
sudo ufw status
```

---

## Step 5: Deploy the Application

```bash
# Clone the repo
cd /home
git clone https://github.com/Frost-bit-star/tendaN301-billing.git
cd tendaN301-billing
```

### Set the sudo password for WireGuard peer management

The application needs to run `wg set` commands to add/remove MikroTik peers. Create a sudoers entry so the web server user can do this without a password prompt:

```bash
# For the default PHP built-in server running as your user:
sudo visudo -f /etc/sudoers.d/jasiri-wireguard

# Add this line (replace 'jackal' with your actual username):
jackal ALL=(ALL) NOPASSWD: /usr/bin/wg set wg0 peer * allowed-ips *, /usr/bin/wg set wg0 peer * remove
```

> **If running under Nginx/Apache** (www-data user), replace `jackal` with `www-data`.

### Save the server public key for the app

```bash
sudo cat /etc/wireguard/keys/server.key.pub > db/server_wg_pubkey.txt
chmod 644 db/server_wg_pubkey.txt
```

### Build the database

```bash
php stack build
```

Or manually:

```bash
php -r "require 'db/schema.php';"
```

### Set file permissions

```bash
chmod 600 db/routers.db
chmod 700 db/
chmod 644 db/server_wg_pubkey.txt
chmod +x stack
chmod +x wireguard-setup.sh
```

### Start the server

```bash
# Development (foreground)
php stack start

# Or run in background
nohup php -S 0.0.0.0:8000 -t . > logs/server.log 2>&1 &
```

The dashboard is now at `http://YOUR_SERVER_IP:8000`.

---

## Step 6: Set Up Nginx Reverse Proxy (Production)

For a production setup with your domain and HTTPS:

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
```

Create the Nginx config:

```bash
sudo tee /etc/nginx/sites-available/jasiri <<'EOF'
server {
    listen 80;
    server_name jasiri.stackverify.site;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Allow large file uploads (vouchers, etc.)
    client_max_body_size 10M;
}
EOF

sudo ln -s /etc/nginx/sites-available/jasiri /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Get the SSL certificate:

```bash
sudo certbot --nginx -d jasiri.stackverify.site
sudo certbot renew --dry-run
```

---

## Step 7: Start the App on Boot (systemd)

Create a systemd service so the PHP server starts automatically:

```bash
sudo tee /etc/systemd/system/jasiri.service <<'EOF'
[Unit]
Description=Jasiri WiFi Billing Server
After=network.target

[Service]
Type=simple
User=jackal
WorkingDirectory=/home/jackal/tendaN301-billing
ExecStart=/usr/bin/php -S 0.0.0.0:8000 -t .
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable jasiri
sudo systemctl start jasiri
sudo systemctl status jasiri
```

---

## Step 8: Open the Dashboard

1. Go to `https://jasiri.stackverify.site`
2. Login with:
   - Username: `admin`
   - Password: `1111`
3. **Change the default password immediately**

---

## Step 9: Add Your First Router

### Tenda Router

1. Go to **Tenda Routers > Add Router**
2. Enter router name, IP, port (80), and admin password
3. Click Add

### MikroTik Router

1. Go to **MikroTik > Connect Device**
2. **Step 1**: Enter device name and location, click Register
3. **Step 2**: Copy and run the two commands on the MikroTik via Winbox/SSH:
   ```
   /system device-mode update mode=advanced fetch=yes
   /tool fetch mode=https url="https://jasiri.stackverify.site/provision/TOKEN" dst-path=jasiri_TIMESTAMP.rsc; :delay 2s; /import jasiri_TIMESTAMP.rsc;
   ```
4. Wait 1-2 minutes for the device to come online (dashboard polls automatically)
5. **Step 3**: Configure services and finish

---

## MikroTik Setup Checklist

For each MikroTik router you connect:

- [ ] Router has internet access (can reach `jasiri.stackverify.site`)
- [ ] RouterOS 7+ is installed
- [ ] Run `/system device-mode update mode=advanced fetch=yes` (once per new router)
- [ ] Run the `/tool fetch` provisioning command from the dashboard
- [ ] Router creates its own WireGuard key and calls back to register it
- [ ] Wait for WireGuard handshake (device appears "online" in dashboard)
- [ ] Configure bridge ports and hotspot services in Step 3

---

## How WireGuard Peers Work

```
MikroTik Router                        Your Server
    |                                      |
    |  1. Runs provisioning script        |
    |  2. Creates own WG key pair         |
    |  3. Fetch GET /api/wireguard_       |
    |     register.php?device=X&pubkey=Y  |
    |------------------------------------->|
    |                                      |
    |  4. Server saves pubkey             |
    |  5. Server runs:                    |
    |     wg set wg0 peer <Y>             |
    |       allowed-ips 10.100.0.x/32     |
    |                                      |
    |<-- WireGuard handshake (UDP 13231) ->|
    |                                      |
    |   MikroTik gets 10.100.0.x/24       |
    |   Server acts as 10.100.0.1         |
    |                                      |
    |<-- API calls over WireGuard --------|
    |   (SSH, REST API, SNMP)             |
```

- The server runs `wg0` on `10.100.0.1/24`, listening on UDP `13231`
- `wg0.conf` contains **only** the `[Interface]` block — no static peers
- Each MikroTik router **generates its own WireGuard key pair** during provisioning
- The router keeps its private key; the server never sees it
- After creating the WireGuard interface, the router calls `/api/wireguard_register.php` with its public key
- The server saves the public key to the database and adds the peer dynamically via `wg set`
- MikroTik management (REST API, SSH) is only accessible over the WireGuard tunnel

---

## Troubleshooting

### WireGuard not connecting

```bash
# Check interface is up
sudo wg show

# Check listening port
ss -ulnp | grep 13231

# Check IP forwarding
cat /proc/sys/net/ipv4/ip_forward
# Should be: 1

# Check iptables NAT
sudo iptables -t nat -L -n | grep MASQUERADE
```

### MikroTik can't reach the server

```bash
# From the MikroTik terminal, test:
/ping jasiri.stackverify.site

# Check if port 13231 is reachable
/tool netwatch add host=jasiri.stackverify.site port=13231 interval=10s
```

### Dashboard shows 502 Bad Gateway

```bash
# Check PHP server is running
sudo systemctl status jasiri

# Check logs
sudo journalctl -u jasiri -f

# Restart
sudo systemctl restart jasiri
```

### Database errors

```bash
# Rebuild schema
php -r "require 'db/schema.php';"

# Check file permissions
ls -la db/routers.db
# Should be: -rw------- (600)
```

### UFW blocking WireGuard

```bash
sudo ufw status
# Ensure 13231/udp is allowed

# If not:
sudo ufw allow 13231/udp
sudo ufw reload
```

---

## File Structure

```
tendaN301-billing/
├── index.php                    # Main router
├── stack                        # CLI launcher
├── wireguard-setup.sh           # WireGuard setup script
│
├── api/
│   ├── mikrotik.php             # MikroTik provisioning + WG peers
│   ├── wireguard_register.php   # Router callback: saves pubkey, adds WG peer
│   ├── vouchers.php             # Voucher CRUD
│   ├── control.php              # Tenda router CRUD
│   ├── billing.php              # Billing operations
│   ├── plans.php                # Plan CRUD
│   └── cron.php                 # Background worker
│
├── pages/
│   ├── dashboard.php            # Main dashboard
│   ├── connect_mikrotik.php     # MikroTik 3-step wizard
│   ├── mikrotik_devices.php     # MikroTik device list
│   ├── vouchers.php             # Voucher management
│   ├── billuser.php             # Tenda user billing
│   ├── billing.php              # Expired users
│   ├── plans.php                # Plan management
│   └── ...
│
├── components/
│   ├── sidebar.php              # Navigation (Tenda + MikroTik)
│   ├── header.php               # Page header
│   └── footer.php               # Page footer
│
├── db/
│   ├── routers.db               # SQLite database
│   ├── schema.php               # Schema migrations
│   └── server_wg_pubkey.txt     # Server WireGuard public key
│
├── auth/
│   ├── v2.php                   # Tenda router communication
│   └── throttle.php             # Bandwidth control
│
└── logs/                        # Application logs
```

---

## Quick Reference

| Task | Command |
|------|---------|
| Start server | `php stack start` |
| Start as service | `sudo systemctl start jasiri` |
| Build database | `php stack build` |
| Check WireGuard | `sudo wg show` |
| Restart WireGuard | `sudo systemctl restart wg-quick@wg0` |
| Check server logs | `sudo journalctl -u jasiri -f` |
| Check firewall | `sudo ufw status` |
| Revoke SSL | `sudo certbot revoke --cert-name jasiri.stackverify.site` |
