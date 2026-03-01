<?php
header('Content-Type: application/json');

try {
    // -----------------------
    // DATABASE CONNECTION
    // -----------------------
    $db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // -----------------------
    // ACCEPT INPUT FROM JSON POST OR GET
    // -----------------------
    $input = json_decode(file_get_contents('php://input'), true) ?: $_GET;

    $router_id = intval($input['router_id'] ?? 0);
    $mac       = strtoupper(trim($input['paid_mac'] ?? $input['mac'] ?? ''));
    $plan_id   = intval($input['plan_id'] ?? 0);
    $name      = trim($input['name'] ?? 'Unknown');
    $phone     = trim($input['phone_number'] ?? '');

    if (!$router_id || !$mac || !$plan_id || !$phone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // -----------------------
    // ENSURE TABLES EXIST
    // -----------------------
    $db->exec("
    CREATE TABLE IF NOT EXISTS billing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        router_id INTEGER NOT NULL,
        mac TEXT NOT NULL,
        plan_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        phone_number TEXT NOT NULL,
        remaining_time INTEGER,
        end_at TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(router_id, mac)
    )");

    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hostname TEXT,
        ip TEXT,
        mac TEXT NOT NULL,
        router_id INTEGER NOT NULL,
        internet_access INTEGER DEFAULT 0,
        connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(router_id, mac)
    )");

    // -----------------------
    // FETCH ROUTER & PLAN
    // -----------------------
    $routerStmt = $db->prepare("SELECT * FROM routers WHERE id = ?");
    $routerStmt->execute([$router_id]);
    $router = $routerStmt->fetch(PDO::FETCH_ASSOC);

    $planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $planStmt->execute([$plan_id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$router) {
        echo json_encode(['success'=>false, 'error'=>'Router not found']);
        exit;
    }
    if (!$plan) {
        echo json_encode(['success'=>false, 'error'=>'Plan not found']);
        exit;
    }

    // -----------------------
    // CALCULATE PLAN DURATION
    // -----------------------
    $totalSeconds =
        ($plan['days'] ?? 0) * 86400 +
        ($plan['hours'] ?? 0) * 3600 +
        ($plan['minutes'] ?? 0) * 60;

    $endAt = date('Y-m-d H:i:s', time() + $totalSeconds);

    // -----------------------
    // INSERT OR UPDATE BILLING
    // -----------------------
    $stmt = $db->prepare("
        INSERT INTO billing (router_id, mac, plan_id, name, phone_number, remaining_time, end_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(router_id, mac) DO UPDATE SET
            plan_id=excluded.plan_id,
            name=excluded.name,
            phone_number=excluded.phone_number,
            remaining_time=excluded.remaining_time,
            end_at=excluded.end_at
    ");
    $stmt->execute([$router_id, $mac, $plan_id, $name, $phone, $totalSeconds, $endAt]);

    // -----------------------
    // UPDATE OR INSERT USER
    // -----------------------
    $stmtUser = $db->prepare("SELECT * FROM users WHERE router_id = ? AND mac = ?");
    $stmtUser->execute([$router_id, $mac]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stmtUpdateUser = $db->prepare("UPDATE users SET internet_access = 1 WHERE router_id = ? AND mac = ?");
        $stmtUpdateUser->execute([$router_id, $mac]);
        $userAction = 'updated';
    } else {
        $stmtInsertUser = $db->prepare("
            INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
        ");
        $stmtInsertUser->execute(['unknown', '', $mac, $router_id]);
        $userAction = 'created';
    }

    // -----------------------
    // RETURN SUCCESS JSON
    // -----------------------
    echo json_encode([
        'success' => true,
        'message' => "Billing saved and user $userAction successfully",
        'billing' => [
            'router_id' => $router_id,
            'mac' => $mac,
            'plan_id' => $plan_id,
            'name' => $name,
            'phone_number' => $phone,
            'remaining_time' => $totalSeconds,
            'end_at' => $endAt
        ],
        'user_action' => $userAction
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
