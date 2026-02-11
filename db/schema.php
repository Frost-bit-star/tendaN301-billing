<?php
// create_db.php

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

//  Add missing router columns
addColumnIfMissing($db, 'routers', 'last_run', 'TEXT');
addColumnIfMissing($db, 'routers', 'last_qos_hash', 'TEXT');

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

// -------------------------
// Devices table
// -------------------------
$db->exec("
CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mac TEXT NOT NULL,
    router_id INTEGER NOT NULL,
    plan_id INTEGER DEFAULT NULL,
    internet_access INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
)
");

// Ensure UNIQUE(mac, router_id)
$db->exec("
CREATE UNIQUE INDEX IF NOT EXISTS idx_devices_mac_router
ON devices(mac, router_id)
");

echo "Database schema verified and missing columns added successfully.\n";
