<?php
// create_db.php
$db = new PDO('sqlite:' . __DIR__ . '/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

// -------------------------
// Plans table (time-based)
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
// Users table (fixed)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hostname TEXT NOT NULL,
    ip TEXT NOT NULL,
    mac TEXT NOT NULL,
    router_id INTEGER NOT NULL,
    plan_id INTEGER DEFAULT NULL,
    internet_access INTEGER DEFAULT 1,  -- 1 = Yes, 0 = No
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
)
");

// Ensure UNIQUE(mac, router_id) exists for users
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_mac_router ON users(mac, router_id)");

// -------------------------
// Devices table (new)
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mac TEXT NOT NULL,
    router_id INTEGER NOT NULL,
    plan_id INTEGER DEFAULT NULL,
    internet_access INTEGER DEFAULT 1,  -- 1 = Yes, 0 = No
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
)
");

// Ensure UNIQUE(mac, router_id) exists for devices
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_mac_router ON devices(mac, router_id)");

echo "Database and tables created/updated successfully.\n";
