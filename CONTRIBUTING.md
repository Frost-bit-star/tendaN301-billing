# Contributing to StackWISP

StackWISP is experimental and early-stage. Contributions are welcome -- bug
fixes, features, documentation, tests, or just ideas.

---

## Ground Rules

1. **Be kind.** This is a small project maintained by a small team.
2. **Open an issue first** for anything non-trivial. Saves everyone time.
3. **Keep PRs focused.** One change per PR. If you're fixing a bug and
   refactoring, split them.
4. **Test your changes.** At minimum, run the binary and verify it starts.
5. **No secrets in code.** Never commit API keys, passwords, or private keys.

---

## Development Setup

### Prerequisites

- Go 1.24+
- PHP 7.4+ with `pdo_sqlite` and `curl`
- Linux with `/dev/net/tun`
- Root access (for TUN device)

### Building the Go service

```bash
cd stackwg
go build -o stackwg .
```

### Running locally

```bash
# Terminal 1: WireGuard server (needs root)
sudo ./stackwg

# Terminal 2: PHP dashboard
cd ..
php stack start
```

The API is at `http://localhost:9000`, dashboard at `http://localhost:8000`.

---

## Project Layout

### Go service (`stackwg/`)

| File | What it does |
|---|---|
| `main.go` | Entry point, startup sequence |
| `wg.go` | WireGuard device: create, configure, add/remove peers, status |
| `api.go` | HTTP API handlers (JSON) |
| `peer_store.go` | Persist peers to `peers.json` |
| `crypto.go` | Curve25519 key generation |
| `nat.go` | iptables NAT/masquerade setup |
| `config.go` | Environment variable config |
| `stackwg_api.php` | PHP helper that calls the Go API |

### PHP dashboard

Standard PHP app. Entry point is `index.php`. APIs are in `api/`. Pages in
`pages/`. No framework -- just plain PHP with a custom router.

---

## How to Contribute

### Bug Reports

Open an issue with:
- What you expected to happen
- What actually happened
- Steps to reproduce
- OS, Go version, PHP version

### Code Changes

1. Fork the repo
2. Create a branch: `git checkout -b my-fix`
3. Make your changes
4. Build and test:
   ```bash
   cd stackwg && go build -o stackwg . && go vet ./...
   ```
5. Commit with a clear message
6. Push and open a PR

### What to Work On

Things that need help (check issues for specifics):

**High priority:**
- RADIUS server implementation (Go)
- Proper TUN cleanup on shutdown
- Graceful handling of missing `/dev/net/tun`
- Unit tests for the Go service

**Medium priority:**
- Prometheus metrics endpoint
- API authentication (token or mTLS)
- IPv6 support
- Dockerfile for the Go service

**Low priority:**
- Web dashboard improvements
- Documentation
- CI/CD setup

---

## Code Style

### Go

- Follow standard Go conventions (`gofmt`, `go vet`)
- Keep functions short and focused
- Log with `log.Printf` (no external logging library)
- Errors return `(value, error)` -- never panic in library code
- Use `context.Context` for anything that might need cancellation

### PHP

- No framework, keep it plain PHP
- Functions prefixed with `stackwg_` for the WireGuard integration
- JSON responses with `Content-Type: application/json`
- SQL via PDO with prepared statements (no string interpolation)

---

## Testing

### Go

```bash
cd stackwg
go vet ./...           # static analysis
go build -o stackwg .  # compilation check
```

Manual testing:
1. Start the binary with `sudo ./stackwg`
2. Test the API:
   ```bash
   curl localhost:9000/api/health
   curl localhost:9000/api/status
   curl -X POST localhost:9000/api/peer/add \
     -d '{"public_key":"<test-key>","allowed_ip":"10.100.0.99/32"}'
   curl localhost:9000/api/peers
   curl -X POST localhost:9000/api/peer/remove \
     -d '{"public_key":"<test-key>"}'
   ```
3. Verify the binary shuts down cleanly on Ctrl+C

### PHP

```bash
php stack start
# Open http://localhost:8000 in a browser
# Test login, dashboard, router management
```

---

## Questions?

Open a discussion or an issue. No question is too basic.
