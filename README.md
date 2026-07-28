# StackWISP - Userspace WireGuard ISP Billing Platform

![experimental](https://img.shields.io/badge/Status-Experimental-red) ![Go](https://img.shields.io/badge/Go-1.24+-00ADD8?logo=go) ![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4) ![License](https://img.shields.io/badge/License-MIT-green)

> **WireGuard without WireGuard.** Run a full ISP billing platform on any server
> where installing the WireGuard kernel module is impossible, forbidden, or just
> not worth the hassle. No `apt install wireguard`. No kernel modules. No drama.

StackWISP is an open-source micro-ISP management platform. It connects to
MikroTik (and Tenda) routers, runs a WireGuard VPN tunnel entirely in
userspace via a Go service, and provides hotspot billing, voucher management,
and a web dashboard for operators.

---

## Why StackWISP?

| Problem | StackWISP Solution |
|---|---|
| VPS provider doesn't allow WireGuard kernel module | WireGuard runs in userspace (Go binary) |
| Container environment blocks `CAP_NET_ADMIN` for `wg` | No kernel WireGuard needed at all |
| Shared hosting / limited root access | Single binary, no system packages required |
| `wg-quick` and `wg set` are fragile in production | HTTP API replaces all shell commands |
| Managing WireGuard configs across multiple servers | Peers persisted as JSON, no config files to juggle |

---

## What This Is

- A **Go binary** (`stackwg`) that implements the WireGuard protocol in userspace
  using [wireguard-go](https://git.zx2c4.com/wireguard-go/) and
  [gVisor netstack](https://gvisor.dev/). MikroTik routers connect as WireGuard
  peers. The Go service handles encryption, decryption, handshakes, and key
  management -- all without touching the kernel's WireGuard module.
- A **PHP web dashboard** for managing routers, billing plans, vouchers,
  and hotspot users.
- An **HTTP API** that replaces every `wg set` / `wg show` shell call with a
  clean REST endpoint.

## What This Is Not

- Not production-ready. This is **experimental software**. Things will break.
- Not a replacement for a full WISP platform. It is intentionally minimal.
- Not affiliated with WireGuard or MikroTik.

---

## Architecture

```
                            Internet
                               |
                    ┌──────────┴──────────┐
                    │                     │
              MikroTik Router       MikroTik Router
              (hotspot + WG)        (hotspot + WG)
                    |                     |
                    | WireGuard (UDP)     | WireGuard (UDP)
                    | 10.100.0.2          | 10.100.0.3
                    |                     |
            ┌───────┴─────────────────────┴───────┐
            │         stackwg (Go binary)          │
            │                                      │
            │  wireguard-go  ←→  gVisor netstack   │
            │  (Noise IK, ChaCha20)  (TCP/IP)      │
            │                                      │
            │  HTTP API :9000  ←→  PHP Dashboard   │
            │  Peers: peers.json                   │
            │  Keys: server_private.key             │
            │  NAT:  iptables MASQUERADE            │
            └──────────────────────────────────────┘
                               |
                    ┌──────────┴──────────┐
                    │   PHP Dashboard     │
                    │   :8000             │
                    │                     │
                    │  Routers, Plans,    │
                    │  Vouchers, Users    │
                    │  SQLite DB          │
                    └─────────────────────┘
```

---

## Quick Start

### 1. Build the Go service

```bash
cd stackwg
go build -o stackwg .
```

### 2. Run it

```bash
sudo ./stackwg
```

The server starts:
- **WireGuard** on UDP `13231` (no kernel module)
- **HTTP API** on port `9000` (peer management)

### 3. Start the PHP dashboard

```bash
cd ..
php stack start
```

Dashboard at `http://localhost:8000`.

### 4. Connect a MikroTik router

Register the router from the dashboard, run the provisioning script on the
MikroTik, and it will automatically create a WireGuard tunnel back to your
server. See [SETUP.md](./SETUP.md) for step-by-step details.

---

## Features

**WireGuard (userspace)**
- Full WireGuard protocol in Go -- no kernel module required
- Automatic key generation and persistence
- HTTP API for peer add/remove/status
- Handshake monitoring and transfer stats
- Auto-NAT via iptables for internet access

**Billing Dashboard**
- MikroTik router provisioning and management
- Tenda router support (via HTTP API)
- Time-based billing plans (hours/days/months)
- Voucher generation and redemption
- Hotspot user management
- Device online/offline tracking
- Revenue tracking and reporting

**Operations**
- Single Go binary, no WireGuard packages to install
- systemd service file included
- Structured JSON logging
- Peer state survives restarts
- Works on any Linux server with a TUN device

---

## Requirements

**Server:**
- Linux with `/dev/net/tun` (present on virtually all distributions)
- Go 1.24+ (for building)
- PHP 7.4+ with `pdo_sqlite` and `curl`
- Root or `CAP_NET_ADMIN` (for TUN device creation)
- No WireGuard kernel module or packages required

**Router:**
- MikroTik RouterOS 7+ with WireGuard support
- Internet access to reach the server

---

## Configuration

Environment variables (or `.env` file in `stackwg/`):

| Variable | Default | Description |
|---|---|---|
| `WG_LISTEN_PORT` | `13231` | WireGuard UDP port |
| `WG_ADDRESS` | `10.100.0.1/24` | Server WireGuard IP |
| `WG_SUBNET` | `10.100.0.0/24` | Client subnet |
| `API_LISTEN` | `:9000` | HTTP API address |
| `DATA_DIR` | `.` | Keys and peer data directory |
| `EXTERNAL_NIC` | auto-detected | NIC for NAT/masquerade |

---

## API Reference

```bash
# Health check
curl http://localhost:9000/api/health

# Server status (public key, port, peers)
curl http://localhost:9000/api/status

# Add a peer
curl -X POST http://localhost:9000/api/peer/add \
  -H "Content-Type: application/json" \
  -d '{"public_key":"...","allowed_ip":"10.100.0.2/32","device_id":"uuid"}'

# Remove a peer
curl -X POST http://localhost:9000/api/peer/remove \
  -d '{"public_key":"..."}'

# List peers with handshake status
curl http://localhost:9000/api/peers
```

Full API docs in [stackwg/INTEGRATION.md](./stackwg/INTEGRATION.md).

---

## Project Status

**This is experimental software.** It is actively developed and not yet
considered stable. Expect breaking changes, incomplete features, and bugs.

Current state:
- [x] Userspace WireGuard server (Go binary)
- [x] HTTP API for peer management
- [x] Peer persistence across restarts
- [x] MikroTik auto-provisioning
- [x] Voucher-based billing
- [x] Web dashboard
- [ ] RADIUS server (planned)
- [ ] IPv6 support
- [ ] Prometheus metrics
- [ ] API authentication
- [ ] Rate limiting

---

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for how to get started.

---

## File Structure

```
.
├── stackwg/                      # Go userspace WireGuard server
│   ├── main.go                   # Entry point
│   ├── wg.go                     # WireGuard device management
│   ├── api.go                    # HTTP API handlers
│   ├── peer_store.go             # Peer persistence (JSON)
│   ├── crypto.go                 # Key generation (Curve25519)
│   ├── nat.go                    # iptables NAT setup
│   ├── config.go                 # Configuration
│   ├── stackwg_api.php           # PHP drop-in helper
│   ├── stackwg.service           # systemd unit
│   └── INTEGRATION.md            # Full integration guide
│
├── api/                          # PHP REST APIs
├── pages/                        # PHP web pages
├── auth/                         # Authentication & billing
├── components/                   # UI components
├── db/                           # SQLite database
│
├── index.php                     # PHP entry point
├── stack                         # PHP CLI launcher
├── SETUP.md                      # Server setup guide
├── CONTRIBUTING.md               # Contribution guide
└── README.md                     # This file
```

---

## License

MIT
