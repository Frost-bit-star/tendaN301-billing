<?php
// schema.php

$db = new PDO('sqlite:' . __DIR__ . '/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -------------------------
// Helper: add column if missing (SQLite-safe)
// -------------------------
function addColumnIfMissing(PDO $db, string $table, string $column, string $definition) {
    $cols = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($column, $cols, true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

// -------------------------
// Routers table
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS routers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    ip TEXT NOT NULL,
    port INTEGER DEFAULT 80,
    password TEXT NOT NULL
)
");

// Add missing router columns
addColumnIfMissing($db, 'routers', 'last_run', 'TEXT');          // last time router was processed
addColumnIfMissing($db, 'routers', 'last_qos_hash', 'TEXT');     // last QoS state hash
addColumnIfMissing($db, 'routers', 'last_mode', 'TEXT');         // 'blacklist' or 'whitelist'
addColumnIfMissing($db, 'routers', 'last_sync', 'INTEGER');      // unix timestamp of last push

// MikroTik support columns
addColumnIfMissing($db, 'routers', 'type', "TEXT DEFAULT 'tenda'");
addColumnIfMissing($db, 'routers', 'location', 'TEXT');
addColumnIfMissing($db, 'routers', 'device_id', 'TEXT');
addColumnIfMissing($db, 'routers', 'wireguard_ip', 'TEXT');
addColumnIfMissing($db, 'routers', 'provisioning_status', "TEXT DEFAULT 'offline'");
addColumnIfMissing($db, 'routers', 'last_provisioned_at', 'TEXT');
addColumnIfMissing($db, 'routers', 'provision_token', 'TEXT');
addColumnIfMissing($db, 'routers', 'wg_pubkey', 'TEXT');

// -------------------------
// Plans table
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    days INTEGER DEFAULT 0,
    hours INTEGER DEFAULT 0,
    minutes INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

// -------------------------
// Users table
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hostname TEXT NOT NULL,
    ip TEXT NOT NULL,
    mac TEXT NOT NULL,
    router_id INTEGER NOT NULL,
    plan_id INTEGER DEFAULT NULL,
    internet_access INTEGER DEFAULT 1,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
)
");

// Ensure UNIQUE(mac, router_id)
$db->exec("
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_mac_router
ON users(mac, router_id)
");

// Add missing sync tracking columns for users
addColumnIfMissing($db, 'users', 'last_router_state', 'TEXT');  // true/false
addColumnIfMissing($db, 'users', 'last_synced_at', 'INTEGER');   // unix timestamp

// -------------------------
// Devices table (billing / active users)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS billing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mac TEXT NOT NULL,
    router_id INTEGER NOT NULL,
    plan_id INTEGER DEFAULT NULL,
    internet_access INTEGER DEFAULT 1,  -- Add internet_access column here
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remaining_time INTEGER DEFAULT 0,
    end_at TEXT DEFAULT NULL,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
)
");

// Ensure UNIQUE(mac, router_id)
$db->exec("
CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_mac_router
ON billing(mac, router_id)
");

// -------------------------
// Admins table (login & recovery)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

// Insert default admin if not exists
$check = $db->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
$check->execute([':username' => 'admin']);
if ($check->fetchColumn() == 0) {
    $insert = $db->prepare("
        INSERT INTO admins (username, password, email)
        VALUES (:username, :password, :email)
    ");
    $insert->execute([
        ':username' => 'admin',
        ':password' => password_hash('1111', PASSWORD_DEFAULT),
        ':email' => 'jnyaragita12@gmail.com'
    ]);
}

// -------------------------
// Accounts table (multi-tenant users)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

// -------------------------
// Tokens table (API sessions)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS tokens (
    token TEXT PRIMARY KEY,
    account_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(account_id) REFERENCES accounts(id)
)
");

// Add tenant_id to routers if missing
addColumnIfMissing($db, 'routers', 'tenant_id', 'INTEGER DEFAULT NULL');

// -------------------------
// Add missing columns for devices table if script re-run
// -------------------------
addColumnIfMissing($db, 'billing', 'remaining_time', 'INTEGER DEFAULT 0');
addColumnIfMissing($db, 'billing', 'end_at', 'TEXT');

// -------------------------
// Ensure `internet_access` column exists in `billing` table
addColumnIfMissing($db, 'billing', 'internet_access', 'INTEGER DEFAULT 1');

// -------------------------
// Vouchers table
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS vouchers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    plan_id INTEGER DEFAULT NULL,
    router_id INTEGER DEFAULT NULL,
    phone TEXT DEFAULT NULL,
    price REAL DEFAULT 0,
    status TEXT DEFAULT 'active',
    customer_name TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TEXT DEFAULT NULL,
    expires_at TEXT DEFAULT NULL,
    FOREIGN KEY(plan_id) REFERENCES plans(id),
    FOREIGN KEY(router_id) REFERENCES routers(id)
)
");

addColumnIfMissing($db, 'vouchers', 'customer_name', 'TEXT DEFAULT NULL');
addColumnIfMissing($db, 'vouchers', 'used_mac', 'TEXT DEFAULT NULL');

// -------------------------
if (php_sapi_name() === 'cli') {
    echo "Database schema verified and updated with MikroTik + voucher support.\n";
}
?>
