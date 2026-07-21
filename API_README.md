# WiFiBilling App API

REST API for mobile/web app to manage Tenda N301 routers, whitelist devices, and billing.

**Base URL:** `https://your-domain.com/api/v1/`

All responses are JSON. Token expires after 7 days.

---

## How It Works

```
┌─────────────┐      local/direct       ┌─────────────┐
│             │ ──────────────────────>  │   Tenda     │
│  Mobile App │ <──────────────────────  │   N301      │
│             │   reads whitelist,       │   Router    │
│             │   online devices         │             │
└──────┬──────┘                          └─────────────┘
       │
       │  POST /sync.php (push data to website)
       │
       ▼
┌─────────────┐
│   Website   │  stores data in DB
│   (remote)  │  tracks billing, history
└─────────────┘
```

**The app talks to the Tenda N301 router directly** (same network or public IP), reads the whitelist and online devices, then **pushes that data to the website** for billing and history tracking.

---

## Tenda N301 Protocol (How the Website Talks to the Router)

This is the reverse-engineered protocol used by `auth/v2.php` to communicate with Tenda N301 routers. All communication is HTTP, uses cookie-based sessions, and returns JSON.

### Step 1 — Login to Router

```
POST http://<router_ip>:<port>/login/Auth
Content-Type: application/x-www-form-urlencoded

password=<base64(admin_password)>
```

- Password is **base64-encoded** before sending
- Router sets a session cookie (persisted via cookie jar file)
- User-Agent must be `"Mozilla/5.0"`

**Example (password = `1111`):**
```
password=MTExMQ==
```

### Step 2 — Fetch Whitelist (MAC Filter)

```
GET http://<router_ip>:<port>/goform/getNAT?modules=macFilter&random=<microtime>
Cookie: <session_cookie>
```

**Response:**
```json
{
  "macFilter": {
    "curFilterMode": "pass",
    "macFilterList": [
      {
        "filterMode": "pass",
        "mac": "AA:BB:CC:DD:EE:FF",
        "hostname": "iPhone",
        "remark": ""
      }
    ]
  }
}
```

- `curFilterMode`: `"pass"` = whitelist mode, `"deny"` = blacklist mode
- Only entries with `filterMode === "pass"` are whitelisted
- MAC format: uppercase, colon-separated (`AA:BB:CC:DD:EE:FF`)

### Step 3 — Fetch Online Devices + Blacklisted

```
GET http://<router_ip>:<port>/goform/getQos?random=<microtime>&modules=onlineList,blackList
Cookie: <session_cookie>
```

**Response:**
```json
{
  "onlineList": [
    {
      "qosListMac": "AA:BB:CC:DD:EE:FF",
      "qosListHostname": "iPhone",
      "qosListIP": "192.168.100.105",
      "qosListConnectType": "wifi",
      "qosListUpLimit": "0",
      "qosListDownLimit": "0",
      "qosListAccess": "true"
    }
  ],
  "blackList": [
    {
      "qosListMac": "11:22:33:44:55:66",
      "qosListHostname": "BlockedDevice",
      "qosListIP": "192.168.100.106",
      "qosListConnectType": "wifi",
      "qosListUpLimit": "0",
      "qosListDownLimit": "0"
    }
  ]
}
```

| Field | Meaning |
|---|---|
| `qosListMac` | MAC address |
| `qosListHostname` | Device name |
| `qosListIP` | Local IP |
| `qosListConnectType` | `"wifi"` or `"wired"` |
| `qosListUpLimit` | Upload limit in kbps (0 = unlimited) |
| `qosListDownLimit` | Download limit in kbps (0 = unlimited) |
| `qosListAccess` | `"true"` = has internet, `"false"` = blocked |

### Step 4 — Push Whitelist Changes

**Two-step process:**

**4a. Set the new MAC filter list:**
```
POST http://<router_ip>:<port>/goform/setNAT
Content-Type: application/x-www-form-urlencoded

module6=macFilter&filterMode=pass&macFilterList=<tab_delimited_list>
```

**MAC list format** — each line is `hostname\thostname\tMAC`:
```
iPhone\tiPhone\tAA:BB:CC:DD:EE:FF
Laptop\tLaptop\t11:22:33:44:55:66
```

**4b. Save to router flash:**
```
POST http://<router_ip>:<port>/goform/save
Content-Type: application/x-www-form-urlencoded

random=<unix_timestamp>
```

**Always call `/goform/save` after `/goform/setNAT`** or changes won't persist.

### Step 5 — Toggle Filter Mode (Whitelist ↔ Blacklist)

```
POST http://<router_ip>:<port>/goform/setNAT
Content-Type: application/x-www-form-urlencoded

module6=macFilter&filterMode=pass
```

Then call `/goform/save` to persist.

### Cookie Management

- Create a temp file: `tempnam(sys_get_temp_dir(), 'tenda_')`
- Use same file for `CURLOPT_COOKIEJAR` (write) and `CURLOPT_COOKIEFILE` (read)
- Delete file with `unlink()` when done
- Each request creates a fresh cookie file

### cURL Settings

```php
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data),
    CURLOPT_COOKIEJAR      => $cookie,
    CURLOPT_COOKIEFILE     => $cookie,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_USERAGENT      => "Mozilla/5.0"
]);
```

### MAC Address Format

Always normalize to uppercase, colon-separated:
```php
$mac = strtoupper(str_replace('-', ':', trim($mac)));
// "aa-bb-cc-dd-ee-ff" → "AA:BB:CC:DD:EE:FF"
```

---

## API Endpoints

### 1. Register

```
POST /api/v1/auth.php
```

| Field | Type | Required |
|---|---|---|
| action | string | `"register"` |
| name | string | yes |
| email | string | yes |
| password | string | yes (min 4 chars) |

**Response 200:**
```json
{
  "success": true,
  "message": "Account created successfully",
  "token": "a1b2c3d4...",
  "account_id": 1,
  "name": "John Doe",
  "email": "john@example.com"
}
```

---

### 2. Login

```
POST /api/v1/auth.php
```

| Field | Type | Required |
|---|---|---|
| action | string | `"login"` |
| email | string | yes |
| password | string | yes |

**Response 200:**
```json
{
  "success": true,
  "token": "a1b2c3d4...",
  "account_id": 1,
  "name": "John Doe",
  "email": "john@example.com"
}
```

---

### 3. Get Current User

```
GET /api/v1/auth.php?action=me&token=TOKEN
```

**Response 200:**
```json
{
  "success": true,
  "account": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-07-20 10:00:00"
  }
}
```

---

### 4. Logout

```
POST /api/v1/auth.php
```

| Field | Type | Required |
|---|---|---|
| action | string | `"logout"` |
| token | string | yes |

---

### 5. List My Routers

```
GET /api/v1/routers.php?token=TOKEN
```

**Response 200:**
```json
{
  "success": true,
  "routers": [
    {
      "id": 1,
      "name": "Office Router",
      "ip": "192.168.0.1",
      "port": 80,
      "online": true,
      "last_run": "2026-07-21 14:30:00",
      "last_mode": null,
      "last_sync": null
    }
  ]
}
```

---

### 6. Add Router

```
POST /api/v1/routers.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| name | string | yes |
| ip | string | yes (router IP, local or public) |
| port | int | no (default 80) |
| password | string | yes (Tenda N301 admin password) |

**Response 200:**
```json
{ "success": true, "message": "Router 'Office Router' added", "id": 1 }
```

---

### 7. Get Single Router

```
GET /api/v1/router.php?token=TOKEN&id=1
```

**Response 200:**
```json
{
  "success": true,
  "router": {
    "id": 1,
    "name": "Office Router",
    "ip": "192.168.0.1",
    "port": 80,
    "online": true
  }
}
```

---

### 8. Update Router

```
PUT /api/v1/router.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| id | int | yes |
| name | string | no |
| ip | string | no |
| port | int | no |
| password | string | no (empty = keep current) |

---

### 9. Delete Router

```
DELETE /api/v1/router.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| id | int | yes |

---

### 10. Connect to Router (App → Tenda N301 Direct)

**This is the main endpoint the app uses to talk to the Tenda N301 directly.**

```
GET /api/v1/router_connect.php?token=TOKEN&router_id=1
```

The website logs into the router using stored credentials and returns the full state. The app uses this to display whitelist, online devices, and blocked devices.

**Response 200:**
```json
{
  "success": true,
  "router_id": 1,
  "router_ip": "192.168.0.1",
  "filter_mode": "pass",
  "whitelist": {
    "AA:BB:CC:DD:EE:FF": "iPhone",
    "11:22:33:44:55:66": "Laptop"
  },
  "online": [
    {
      "mac": "AA:BB:CC:DD:EE:FF",
      "hostname": "iPhone",
      "ip": "192.168.100.105",
      "type": "wifi",
      "upload": 0,
      "download": 0,
      "access": true
    }
  ],
  "blacklisted": [
    {
      "mac": "22:33:44:55:66:77",
      "hostname": "BlockedDevice",
      "ip": "192.168.100.106",
      "type": "wifi"
    }
  ],
  "fetched_at": "2026-07-21 15:30:00"
}
```

| Field | Description |
|---|---|
| `whitelist` | Object: MAC → hostname. All devices allowed internet |
| `online` | Array: currently connected devices |
| `blacklisted` | Array: currently blocked devices |
| `filter_mode` | `"pass"` = whitelist mode, `"deny"` = blacklist mode |

---

### 11. Sync Router Data to Website (App → Website)

**After reading data from the router, the app pushes it here to keep the website in sync.**

```
POST /api/v1/sync.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| router_id | int | yes |
| whitelist | object | no (MAC → hostname map) |
| online | array | no (devices from router_connect) |
| blacklisted | array | no (devices from router_connect) |

**Request body:**
```json
{
  "token": "TOKEN",
  "router_id": 1,
  "whitelist": {
    "AA:BB:CC:DD:EE:FF": "iPhone",
    "11:22:33:44:55:66": "Laptop"
  },
  "online": [
    {
      "mac": "AA:BB:CC:DD:EE:FF",
      "hostname": "iPhone",
      "ip": "192.168.100.105",
      "type": "wifi"
    }
  ],
  "blacklisted": [
    {
      "mac": "22:33:44:55:66:77",
      "hostname": "BlockedDevice",
      "ip": "192.168.100.106"
    }
  ]
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Data synced successfully",
  "router_id": 1,
  "stats": {
    "online": 3,
    "blacklisted": 1,
    "whitelist": 5
  },
  "synced_at": "2026-07-21 15:35:00"
}
```

---

### 12. List Whitelisted Devices (Direct from Router)

```
GET /api/v1/whitelist.php?token=TOKEN&router_id=1
```

**Response 200:**
```json
{
  "success": true,
  "filter_mode": "pass",
  "whitelist": [
    { "mac": "AA:BB:CC:DD:EE:FF", "hostname": "iPhone" },
    { "mac": "11:22:33:44:55:66", "hostname": "Laptop" }
  ]
}
```

---

### 13. Add Device to Whitelist (Pushes to Router)

```
POST /api/v1/whitelist.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| router_id | int | yes |
| mac | string | yes |
| hostname | string | yes |
| plan_id | int | no (creates billing if provided) |
| name | string | no |
| phone_number | string | no (required if plan_id) |

**Response 200:**
```json
{
  "success": true,
  "message": "Device AA:BB:CC:DD:EE:FF added to whitelist",
  "whitelist_count": 3
}
```

---

### 14. Remove Device from Whitelist (Pushes to Router)

```
DELETE /api/v1/whitelist.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| router_id | int | yes |
| mac | string | yes |

---

### 15. List Billing Users

```
GET /api/v1/billing.php?token=TOKEN&router_id=1
```

**Response 200:**
```json
{
  "success": true,
  "users": [
    {
      "id": 1,
      "router_id": 1,
      "mac": "AA:BB:CC:DD:EE:FF",
      "plan_id": 1,
      "name": "John",
      "phone_number": "+254700000000",
      "remaining_time": 2592000,
      "end_at": "2026-08-20 12:00:00",
      "created_at": "2026-07-20 12:00:00",
      "plan_name": "Monthly Basic",
      "days": 30,
      "hours": 0,
      "minutes": 0,
      "remaining_seconds": 2592000,
      "expired": false
    }
  ]
}
```

---

### 16. Add Billing User

```
POST /api/v1/billing.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| router_id | int | yes |
| mac | string | yes |
| plan_id | int | yes |
| name | string | yes |
| phone_number | string | no |

---

### 17. Delete Billing User

```
DELETE /api/v1/billing.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| id | int | yes (billing record id) |

---

### 18. List Plans

```
GET /api/v1/plans.php?token=TOKEN
```

---

### 19. Create Plan

```
POST /api/v1/plans.php
```

| Field | Type | Required |
|---|---|---|
| token | string | yes |
| name | string | yes |
| days | int | no |
| hours | int | no |
| minutes | int | no |

---

## App Flow

```
1. Open app → Register / Login
   POST /api/v1/auth.php {action:"register"|"login", email, password}
   → save token

2. Home screen → List routers
   GET /api/v1/routers.php?token=X

3. Add router (enter Tenda N301 IP + admin password)
   POST /api/v1/routers.php {token, name, ip, port, password}

4. Tap router → Connect to Tenda N301 directly
   GET /api/v1/router_connect.php?token=X&router_id=Y
   → shows whitelist, online devices, blacklisted devices

5. Add device to whitelist (pushes to Tenda N301)
   POST /api/v1/whitelist.php {token, router_id, mac, hostname}
   Optional: add billing in same call with {plan_id, name, phone_number}

6. Remove device from whitelist (pushes to Tenda N301)
   DELETE /api/v1/whitelist.php {token, router_id, mac}

7. Sync all data to website
   POST /api/v1/sync.php {token, router_id, whitelist, online, blacklisted}

8. View billing
   GET /api/v1/billing.php?token=X&router_id=Y

9. View plans
   GET /api/v1/plans.php?token=X

10. Edit / Delete router
    PUT    /api/v1/router.php {token, id, ...}
    DELETE /api/v1/router.php {token, id}
```

---

## Error Format

```json
{ "success": false, "error": "Error message" }
```

| HTTP | Meaning |
|---|---|
| 400 | Missing/invalid fields |
| 401 | Invalid or expired token |
| 404 | Not found |
| 405 | Wrong HTTP method |
| 409 | Duplicate (email already registered) |
| 500 | Server/router error |

---

## Token

- Returned on register and login
- Valid for 7 days
- Pass as `?token=TOKEN` on GET or `"token": "TOKEN"` in POST/PUT/DELETE body
- Each user sees only their own routers and data

---

## Notes

- The app talks to the Tenda N301 router directly (same network or public IP)
- The website acts as a central database for billing and history
- `router_connect.php` is the main endpoint — returns whitelist + online + blacklisted in one call
- `sync.php` pushes the app's local data to the website database
- `whitelist.php` POST/DELETE pushes changes directly to the Tenda N301 router
- Plans are shared across all users (created from the web admin panel)
- Router password is the Tenda N301 hardware admin password
- Expired billing users are auto-blocked by the server cron worker
