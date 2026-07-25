<?php
// api/v1/router.php - Edit, delete, view a single router
require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();
$method = $_SERVER['REQUEST_METHOD'];

// Get router ID from query or body
$input = getInput();
$routerId = intval($_GET['id'] ?? $input['id'] ?? 0);

if (!$routerId) {
    respond(['success' => false, 'error' => 'Router ID required'], 400);
}

// Verify ownership
$stmt = $db->prepare("SELECT * FROM routers WHERE id = ? AND tenant_id = ?");
$stmt->execute([$routerId, $accountId]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    respond(['success' => false, 'error' => 'Router not found or access denied'], 404);
}

// Assign ownership if not set yet
if ($router['tenant_id'] === null) {
    $db->prepare("UPDATE routers SET tenant_id = ? WHERE id = ?")->execute([$accountId, $routerId]);
}

switch ($method) {

    // -------------------------
    // GET - View router details
    // -------------------------
    case 'GET':
        unset($router['password']);
        $router['online'] = isRouterOnline($router['ip'], $router['port'] ?: 80);
        $router['id'] = (int)$router['id'];
        $router['port'] = (int)$router['port'];
        respond(['success' => true, 'router' => $router]);
        break;

    // -------------------------
    // PUT - Update router
    // -------------------------
    case 'PUT':
        $name = trim($input['name'] ?? $router['name']);
        $ip   = trim($input['ip'] ?? $router['ip']);
        $port = intval($input['port'] ?? $router['port']);
        $pass = trim($input['password'] ?? '');

        if (empty($pass)) {
            // Keep existing password
            $stmt = $db->prepare("UPDATE routers SET name = ?, ip = ?, port = ? WHERE id = ?");
            $stmt->execute([$name, $ip, $port, $routerId]);
        } else {
            $stmt = $db->prepare("UPDATE routers SET name = ?, ip = ?, port = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $ip, $port, $pass, $routerId]);
        }

        respond(['success' => true, 'message' => "Router updated"]);
        break;

    // -------------------------
    // DELETE - Remove router
    // -------------------------
    case 'DELETE':
        $db->prepare("DELETE FROM routers WHERE id = ? AND tenant_id = ?")
           ->execute([$routerId, $accountId]);
        respond(['success' => true, 'message' => 'Router deleted']);
        break;

    default:
        respond(['success' => false, 'error' => 'Method not allowed'], 405);
}
