<?php
// api/v1/billing.php - Add and list billing users for authenticated account
require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();
$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();

$routerId = intval($input['router_id'] ?? $_GET['router_id'] ?? 0);

// GET - List billing users
if ($method === 'GET') {
    if (!$routerId) {
        respond(['success' => false, 'error' => 'router_id required'], 400);
    }

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM routers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$routerId, $accountId]);
    if (!$stmt->fetch()) {
        respond(['success' => false, 'error' => 'Router not found or access denied'], 404);
    }

    $stmt = $db->prepare("
        SELECT b.*, p.name AS plan_name, p.days, p.hours, p.minutes
        FROM billing b
        LEFT JOIN plans p ON b.plan_id = p.id
        WHERE b.router_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$routerId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $remaining = max(strtotime($user['end_at']) - time(), 0);
        $user['remaining_seconds'] = $remaining;
        $user['expired'] = ($remaining <= 0);
        $user['id'] = (int)$user['id'];
    }

    respond(['success' => true, 'users' => $users]);
    exit;
}

// POST - Add billing user
if ($method === 'POST') {
    $mac       = strtoupper(trim($input['mac'] ?? ''));
    $planId    = intval($input['plan_id'] ?? 0);
    $name      = trim($input['name'] ?? 'Unknown');
    $phone     = trim($input['phone_number'] ?? '');

    if (!$routerId || !$mac || !$planId) {
        respond(['success' => false, 'error' => 'router_id, mac and plan_id are required'], 400);
    }

    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM routers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$routerId, $accountId]);
    if (!$stmt->fetch()) {
        respond(['success' => false, 'error' => 'Router not found or access denied'], 404);
    }

    // Get plan
    $planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        respond(['success' => false, 'error' => 'Plan not found'], 404);
    }

    $totalSeconds = ($plan['days'] ?? 0) * 86400
                  + ($plan['hours'] ?? 0) * 3600
                  + ($plan['minutes'] ?? 0) * 60;
    $endAt = date('Y-m-d H:i:s', time() + $totalSeconds);

    // Insert or update billing
    $db->prepare("
        INSERT INTO billing (router_id, mac, plan_id, name, phone_number, remaining_time, end_at, internet_access)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ON CONFLICT(router_id, mac) DO UPDATE SET
            plan_id=excluded.plan_id,
            name=excluded.name,
            phone_number=excluded.phone_number,
            remaining_time=excluded.remaining_time,
            end_at=excluded.end_at,
            internet_access=1
    ")->execute([$routerId, $mac, $planId, $name, $phone, $totalSeconds, $endAt]);

    // Ensure user exists in users table
    $db->prepare("
        INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
        VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
        ON CONFLICT(mac, router_id) DO UPDATE SET internet_access = 1
    ")->execute([$name, '', $mac, $routerId]);

    respond([
        'success' => true,
        'message' => 'User added to billing',
        'billing' => [
            'router_id'     => $routerId,
            'mac'           => $mac,
            'plan_id'       => $planId,
            'name'          => $name,
            'phone_number'  => $phone,
            'remaining_time'=> $totalSeconds,
            'end_at'        => $endAt
        ]
    ]);
    exit;
}

// DELETE - Remove billing user
if ($method === 'DELETE') {
    $billingId = intval($input['id'] ?? $_GET['id'] ?? 0);

    if (!$billingId) {
        respond(['success' => false, 'error' => 'Billing ID required'], 400);
    }

    $stmt = $db->prepare("
        DELETE FROM billing WHERE id = ? AND router_id IN (
            SELECT id FROM routers WHERE tenant_id = ?
        )
    ");
    $stmt->execute([$billingId, $accountId]);

    if ($stmt->rowCount() === 0) {
        respond(['success' => false, 'error' => 'Not found or access denied'], 404);
    }

    respond(['success' => true, 'message' => 'User removed from billing']);
    exit;
}

respond(['success' => false, 'error' => 'Method not allowed. Use GET, POST, DELETE'], 405);
