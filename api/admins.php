<?php
// /api/admins.php - Super admin management of ALL tenant admins (accounts).
// Each account in the `accounts` table is a "general admin" running their own
// WISP instance. The super admin creates, limits, resets and removes them.
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Only the super admin can manage admins']);
    exit;
}

require_once __DIR__ . '/../db/schema.php';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';

if ($method === 'GET') {
    $stmt = $db->query("
        SELECT a.id, a.name, a.email, a.currency, a.voucher_limit, a.status, a.created_at, a.created_by,
               (SELECT COUNT(*) FROM vouchers v WHERE v.created_by = a.id) AS vouchers_used,
               (SELECT COUNT(*) FROM routers r WHERE r.tenant_id = a.id) AS routers_count
        FROM accounts a
        ORDER BY a.id ASC
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($admins as &$a) {
        $a['voucher_limit'] = (int)$a['voucher_limit'];
        $a['status'] = (int)$a['status'];
        $a['vouchers_used'] = (int)$a['vouchers_used'];
        $a['routers_count'] = (int)$a['routers_count'];
    }
    jsonResponse(['success' => true, 'admins' => $admins]);
}

if ($method === 'POST') {
    switch ($action) {
        // -----------------------------
        // Create a new tenant admin (general admin)
        // -----------------------------
        case 'create': {
            $name     = trim($input['name'] ?? '');
            $email    = strtolower(trim($input['email'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $limit    = isset($input['voucher_limit']) && $input['voucher_limit'] !== '' ? (int)$input['voucher_limit'] : -1;
            $active   = isset($input['active']) ? (int)$input['active'] : 1;

            if (!$name || !$email || !$password) {
                jsonResponse(['error' => 'Name, email and password are required'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid email address'], 400);
            }
            if (strlen($password) < 4) {
                jsonResponse(['error' => 'Password must be at least 4 characters'], 400);
            }
            $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'An admin with this email already exists'], 409);
            }

            $db->prepare("
                INSERT INTO accounts (name, email, password, currency, voucher_limit, status, created_by)
                VALUES (?, ?, ?, 'TZS', ?, ?, ?)
            ")->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $limit, $active, $_SESSION['admin_id'] ?? null]);

            jsonResponse(['success' => true, 'message' => "Admin '$name' created", 'id' => (int)$db->lastInsertId()]);
        }

        // -----------------------------
        // Update limit / status / email
        // -----------------------------
        case 'update': {
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'Admin ID is required'], 400);

            $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) jsonResponse(['error' => 'Admin not found'], 404);

            $newLimit  = isset($input['voucher_limit']) && $input['voucher_limit'] !== '' ? (int)$input['voucher_limit'] : -1;
            $newStatus = isset($input['status']) ? (int)$input['status'] : (int)$target['status'];
            $newEmail  = isset($input['email']) ? strtolower(trim($input['email'])) : $target['email'];

            if ($newEmail !== $target['email']) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Invalid email address'], 400);
                $dup = $db->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
                $dup->execute([$newEmail, $id]);
                if ($dup->fetch()) jsonResponse(['error' => 'Email already in use'], 409);
            }

            $db->prepare("UPDATE accounts SET email = ?, voucher_limit = ?, status = ? WHERE id = ?")
               ->execute([$newEmail, $newLimit, $newStatus, $id]);

            jsonResponse(['success' => true, 'message' => 'Admin updated']);
        }

        // -----------------------------
        // Reset password
        // -----------------------------
        case 'password': {
            $id = (int)($input['id'] ?? 0);
            $newPassword = (string)($input['new_password'] ?? '');
            if (!$id) jsonResponse(['error' => 'Admin ID is required'], 400);
            if (strlen($newPassword) < 4) jsonResponse(['error' => 'Password must be at least 4 characters'], 400);

            $stmt = $db->prepare("SELECT id FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonResponse(['error' => 'Admin not found'], 404);

            $db->prepare("UPDATE accounts SET password = ? WHERE id = ?")
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);

            jsonResponse(['success' => true, 'message' => 'Password reset']);
        }

        // -----------------------------
        // Delete a tenant admin
        // -----------------------------
        case 'delete': {
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'Admin ID is required'], 400);

            $stmt = $db->prepare("SELECT id, name FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) jsonResponse(['error' => 'Admin not found'], 404);

            // Clean up their API tokens
            $db->prepare("DELETE FROM tokens WHERE account_id = ?")->execute([$id]);
            // Orphan their vouchers' attribution is kept for history
            $db->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);

            jsonResponse(['success' => true, 'message' => 'Admin "' . $target['name'] . '" deleted']);
        }

        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
