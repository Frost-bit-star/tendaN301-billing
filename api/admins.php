<?php
// /api/admins.php - Super admin management of general admins
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
$meId = (int)($_SESSION['admin_id'] ?? 0);

if ($method === 'GET') {
    $stmt = $db->query("
        SELECT a.id, a.username, a.email, a.role, a.voucher_limit, a.status, a.created_at, a.created_by,
               (SELECT COUNT(*) FROM vouchers v WHERE v.created_by = a.id) AS vouchers_used
        FROM admins a
        ORDER BY a.id ASC
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $superCount = (int)$db->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'")->fetchColumn();
    foreach ($admins as &$a) {
        $a['voucher_limit'] = (int)$a['voucher_limit'];
        $a['status'] = (int)$a['status'];
        $a['vouchers_used'] = (int)$a['vouchers_used'];
        $a['is_self'] = ((int)$a['id'] === $meId);
    }
    jsonResponse(['success' => true, 'admins' => $admins, 'super_count' => $superCount]);
}

if ($method === 'POST') {
    switch ($action) {
        // -----------------------------
        // Create a new general admin
        // -----------------------------
        case 'create': {
            $username = trim($input['username'] ?? '');
            $email    = strtolower(trim($input['email'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $limit    = isset($input['voucher_limit']) && $input['voucher_limit'] !== '' ? (int)$input['voucher_limit'] : -1;
            $active   = isset($input['active']) ? (int)$input['active'] : 1;

            if (!$username || !$email || !$password) {
                jsonResponse(['error' => 'Username, email and password are required'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid email address'], 400);
            }
            if (strlen($password) < 4) {
                jsonResponse(['error' => 'Password must be at least 4 characters'], 400);
            }
            $stmt = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'Username or email already exists'], 409);
            }

            $db->prepare("
                INSERT INTO admins (username, email, password, role, voucher_limit, status, created_by)
                VALUES (?, ?, ?, 'admin', ?, ?, ?)
            ")->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $limit, $active, $meId]);

            jsonResponse(['success' => true, 'message' => "Admin '$username' created", 'id' => (int)$db->lastInsertId()]);
        }

        // -----------------------------
        // Update limit / status / email
        // -----------------------------
        case 'update': {
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'Admin ID is required'], 400);

            $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) jsonResponse(['error' => 'Admin not found'], 404);

            if ($id === $meId) {
                jsonResponse(['error' => 'You cannot edit your own account here'], 403);
            }

            $newLimit = isset($input['voucher_limit']) && $input['voucher_limit'] !== '' ? (int)$input['voucher_limit'] : -1;
            $newStatus = isset($input['status']) ? (int)$input['status'] : (int)$target['status'];
            $newEmail = isset($input['email']) ? strtolower(trim($input['email'])) : $target['email'];

            if ($newEmail !== $target['email']) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Invalid email address'], 400);
                $dup = $db->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                $dup->execute([$newEmail, $id]);
                if ($dup->fetch()) jsonResponse(['error' => 'Email already in use'], 409);
            }

            // Prevent disabling the last super admin
            if ($target['role'] === 'superadmin' && $newStatus === 0) {
                $superCount = (int)$db->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin' AND status = 1")->fetchColumn();
                if ($superCount <= 1) {
                    jsonResponse(['error' => 'Cannot disable the last active super admin'], 400);
                }
            }

            $db->prepare("UPDATE admins SET email = ?, voucher_limit = ?, status = ? WHERE id = ?")
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

            $stmt = $db->prepare("SELECT id FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) jsonResponse(['error' => 'Admin not found'], 404);

            $db->prepare("UPDATE admins SET password = ? WHERE id = ?")
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);

            jsonResponse(['success' => true, 'message' => 'Password reset']);
        }

        // -----------------------------
        // Delete an admin
        // -----------------------------
        case 'delete': {
            $id = (int)($input['id'] ?? 0);
            if (!$id) jsonResponse(['error' => 'Admin ID is required'], 400);

            if ($id === $meId) {
                jsonResponse(['error' => 'You cannot delete your own account'], 400);
            }

            $stmt = $db->prepare("SELECT role FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) jsonResponse(['error' => 'Admin not found'], 404);

            if ($target['role'] === 'superadmin') {
                $superCount = (int)$db->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'")->fetchColumn();
                if ($superCount <= 1) {
                    jsonResponse(['error' => 'Cannot delete the last super admin'], 400);
                }
            }

            $db->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
            jsonResponse(['success' => true, 'message' => 'Admin deleted']);
        }

        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
