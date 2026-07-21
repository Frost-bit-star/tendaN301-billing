<?php
// api/v1/routers.php - List, add routers for authenticated user
require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT id, name, ip, port, last_run, last_mode, last_sync
        FROM routers
        WHERE tenant_id = ?
        ORDER BY name ASC
    ");
    $stmt->execute([$accountId]);
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routers as &$router) {
        $router['online'] = isRouterOnline($router['ip'], $router['port'] ?: 80);
        $router['id'] = (int)$router['id'];
        $router['port'] = (int)$router['port'];
    }

    respond(['success' => true, 'routers' => $routers]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();

    $name = trim($input['name'] ?? '');
    $ip   = trim($input['ip'] ?? '');
    $port = intval($input['port'] ?? 80);
    $pass = trim($input['password'] ?? '');

    if (!$name || !$ip || !$pass) {
        respond(['success' => false, 'error' => 'Name, IP and password are required'], 400);
    }

    // Check if router name already exists for this user
    $check = $db->prepare("SELECT id FROM routers WHERE name = ? AND tenant_id = ?");
    $check->execute([$name, $accountId]);
    $existing = $check->fetch();

    if ($existing) {
        $stmt = $db->prepare("
            UPDATE routers SET ip = ?, port = ?, password = ?, tenant_id = COALESCE(tenant_id, ?)
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$ip, $port, $pass, $accountId, $existing['id'], $accountId]);
        respond(['success' => true, 'message' => "Router '$name' updated", 'id' => (int)$existing['id']]);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO routers (name, ip, port, password, tenant_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $ip, $port, $pass, $accountId]);

    respond([
        'success' => true,
        'message' => "Router '$name' added",
        'id'      => (int)$db->lastInsertId()
    ]);
    exit;
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);
