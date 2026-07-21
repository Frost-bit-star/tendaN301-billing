<?php
// api/v1/plans.php - List plans (shared across all users)
require_once __DIR__ . '/helpers.php';

$db = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, name, days, hours, minutes, created_at FROM plans ORDER BY created_at DESC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respond(['success' => true, 'plans' => $plans]);
    exit;
}

// Only admins should create/edit plans via API, but keeping simple for now
if ($method === 'POST') {
    // Token optional for plans - any logged in user can list, but only POST needs to be checked
    getAccountId();
    $input = getInput();
    $name = trim($input['name'] ?? '');
    $days = intval($input['days'] ?? 0);
    $hours = intval($input['hours'] ?? 0);
    $minutes = intval($input['minutes'] ?? 0);

    if (!$name) {
        respond(['success' => false, 'error' => 'Plan name required'], 400);
    }

    $stmt = $db->prepare("INSERT INTO plans (name, days, hours, minutes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $days, $hours, $minutes]);

    respond(['success' => true, 'message' => 'Plan created', 'plan_id' => (int)$db->lastInsertId()]);
    exit;
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);
