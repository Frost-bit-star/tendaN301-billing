<?php
// api/v1/sync.php - App pushes local router data to website database
// POST /api/v1/sync.php
//
// Body:
//   token, router_id, whitelist (object), online (array), blacklisted (array)
//
// Updates: users table (online devices), router last_sync timestamp
// The app fetches data from router locally, then pushes here to keep website in sync.

require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();
$input = getInput();

$routerId = intval($input['router_id'] ?? 0);
if (!$routerId) {
    respond(['success' => false, 'error' => 'router_id required'], 400);
}

// Verify ownership
$stmt = $db->prepare("SELECT * FROM routers WHERE id = ? AND tenant_id = ?");
$stmt->execute([$routerId, $accountId]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    respond(['success' => false, 'error' => 'Router not found or access denied'], 404);
}

$whitelist  = $input['whitelist']  ?? [];  // { "AA:BB:CC:DD:EE:FF": "hostname", ... }
$online     = $input['online']     ?? [];  // [{ mac, hostname, ip, type, ... }]
$blacklisted= $input['blacklisted']?? [];  // [{ mac, hostname, ip }]

$stats = ['online' => 0, 'blacklisted' => 0, 'whitelist' => 0];

try {
    $db->beginTransaction();

    // --- Sync online devices to `users` table ---
    foreach ($online as $dev) {
        $mac  = strtoupper(trim($dev['mac'] ?? ''));
        $host = preg_replace('/[^\w\.\-]/', '', $dev['hostname'] ?? 'device');
        $ip   = $dev['ip'] ?? '';
        if (!$mac) continue;

        $db->prepare("
            INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at, last_router_state, last_synced_at)
            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, 'true', ?)
            ON CONFLICT(mac, router_id) DO UPDATE SET
                hostname = excluded.hostname,
                ip = excluded.ip,
                internet_access = 1,
                last_router_state = 'true',
                last_synced_at = excluded.last_synced_at
        ")->execute([$host, $ip, $mac, $routerId, time()]);

        $stats['online']++;
    }

    // Mark devices no longer online
    $allMac = array_map(fn($d) => strtoupper(trim($d['mac'] ?? '')), $online);
    $allMac = array_filter($allMac);

    if ($allMac) {
        $placeholders = implode(',', array_fill(0, count($allMac), '?'));
        $db->prepare("
            UPDATE users SET last_router_state = 'false', last_synced_at = ?
            WHERE router_id = ? AND mac NOT IN ($placeholders)
        ")->execute(array_merge([time(), $routerId], array_values($allMac)));
    }

    // --- Sync blacklisted devices ---
    foreach ($blacklisted as $dev) {
        $mac  = strtoupper(trim($dev['mac'] ?? ''));
        $host = preg_replace('/[^\w\.\-]/', '', $dev['hostname'] ?? 'device');
        $ip   = $dev['ip'] ?? '';
        if (!$mac) continue;

        $db->prepare("
            INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at, last_router_state, last_synced_at)
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP, 'false', ?)
            ON CONFLICT(mac, router_id) DO UPDATE SET
                hostname = excluded.hostname,
                ip = excluded.ip,
                internet_access = 0,
                last_router_state = 'false',
                last_synced_at = excluded.last_synced_at
        ")->execute([$host, $ip, $mac, $routerId, time()]);

        $stats['blacklisted']++;
    }

    // --- Track whitelist count ---
    $stats['whitelist'] = count($whitelist);

    // --- Update router last_sync ---
    $db->prepare("UPDATE routers SET last_sync = ? WHERE id = ?")->execute([time(), $routerId]);

    $db->commit();

    respond([
        'success'   => true,
        'message'   => 'Data synced successfully',
        'router_id' => $routerId,
        'stats'     => $stats,
        'synced_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    $db->rollBack();
    respond(['success' => false, 'error' => 'Sync failed: ' . $e->getMessage()], 500);
}
