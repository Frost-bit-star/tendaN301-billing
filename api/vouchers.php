<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db/schema.php';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function generateVoucherCode($length = 8) {
    $chars = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'generate';

    if ($action === 'generate') {
        $planId = $input['plan_id'] ?? null;
        $routerId = $input['router_id'] ?? null;
        $phone = trim($input['phone'] ?? '');
        $customerName = trim($input['customer_name'] ?? '');
        $price = floatval($input['price'] ?? 0);
        $quantity = max(1, min(100, intval($input['quantity'] ?? 1)));
        $expiresAt = $input['expires_at'] ?? null;

        if (!$planId) {
            jsonResponse(['error' => 'Plan is required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM plans WHERE id = :id");
        $stmt->execute([':id' => $planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            jsonResponse(['error' => 'Plan not found'], 404);
        }

        $totalSeconds = ($plan['days'] ?? 0) * 86400 + ($plan['hours'] ?? 0) * 3600 + ($plan['minutes'] ?? 0) * 60;

        if (!$expiresAt) {
            $expiresAt = date('Y-m-d H:i:s', time() + $totalSeconds + (30 * 86400));
        }

        $vouchers = [];
        $stmt = $db->prepare("
            INSERT INTO vouchers (code, plan_id, router_id, phone, customer_name, price, status, expires_at)
            VALUES (:code, :plan_id, :router_id, :phone, :customer_name, :price, 'active', :expires_at)
        ");

        for ($i = 0; $i < $quantity; $i++) {
            $code = generateVoucherCode();
            $attempts = 0;
            while ($attempts < 10) {
                $check = $db->prepare("SELECT COUNT(*) FROM vouchers WHERE code = :code");
                $check->execute([':code' => $code]);
                if ($check->fetchColumn() == 0) break;
                $code = generateVoucherCode();
                $attempts++;
            }

            $stmt->execute([
                ':code' => $code,
                ':plan_id' => $planId,
                ':router_id' => $routerId,
                ':phone' => $phone,
                ':customer_name' => $customerName,
                ':price' => $price,
                ':expires_at' => $expiresAt,
            ]);

            $vouchers[] = [
                'id' => $db->lastInsertId(),
                'code' => $code,
                'plan' => $plan['name'],
                'price' => $price,
                'expires_at' => $expiresAt,
            ];
        }

        jsonResponse(['success' => true, 'vouchers' => $vouchers, 'count' => count($vouchers)]);
    }

    if ($action === 'redeem') {
        $code = trim($input['code'] ?? '');

        if (empty($code)) {
            jsonResponse(['error' => 'Voucher code is required'], 400);
        }

        $stmt = $db->prepare("SELECT v.*, p.name as plan_name, p.days, p.hours, p.minutes FROM vouchers v LEFT JOIN plans p ON v.plan_id = p.id WHERE v.code = :code");
        $stmt->execute([':code' => $code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            jsonResponse(['error' => 'Invalid voucher code'], 404);
        }

        if ($voucher['status'] !== 'active') {
            jsonResponse(['error' => 'Voucher is already ' . $voucher['status']], 400);
        }

        if ($voucher['expires_at'] && strtotime($voucher['expires_at']) < time()) {
            $db->prepare("UPDATE vouchers SET status = 'expired' WHERE id = :id")->execute([':id' => $voucher['id']]);
            jsonResponse(['error' => 'Voucher has expired'], 400);
        }

        $totalSeconds = ($voucher['days'] ?? 0) * 86400 + ($voucher['hours'] ?? 0) * 3600 + ($voucher['minutes'] ?? 0) * 60;
        $endAt = date('Y-m-d H:i:s', time() + $totalSeconds);

        $db->prepare("UPDATE vouchers SET status = 'used', used_at = :ts WHERE id = :id")
           ->execute([':ts' => date('Y-m-d H:i:s'), ':id' => $voucher['id']]);

        jsonResponse([
            'success' => true,
            'voucher' => [
                'code' => $voucher['code'],
                'plan' => $voucher['plan_name'],
                'duration' => $totalSeconds,
                'end_at' => $endAt,
                'phone' => $voucher['phone'],
                'price' => $voucher['price'],
            ]
        ]);
    }

    jsonResponse(['error' => 'Unknown action'], 400);
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $status = $_GET['status'] ?? null;
        $routerId = $_GET['router_id'] ?? null;
        $limit = min(500, max(1, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));

        $where = [];
        $params = [];

        if ($status) {
            $where[] = 'v.status = :status';
            $params[':status'] = $status;
        }
        if ($routerId) {
            $where[] = 'v.router_id = :router_id';
            $params[':router_id'] = $routerId;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM vouchers v $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT v.*, p.name as plan_name, p.days, p.hours, p.minutes, r.name as router_name
            FROM vouchers v
            LEFT JOIN plans p ON v.plan_id = p.id
            LEFT JOIN routers r ON v.router_id = r.id
            $whereClause
            ORDER BY v.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statsStmt = $db->query("SELECT status, COUNT(*) as count FROM vouchers GROUP BY status");
        $stats = [];
        while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = $row['count'];
        }

        jsonResponse([
            'vouchers' => $vouchers,
            'total' => $total,
            'stats' => $stats,
        ]);
    }

    if ($action === 'stats') {
        $statsStmt = $db->query("SELECT status, COUNT(*) as count FROM vouchers GROUP BY status");
        $stats = ['total' => 0, 'active' => 0, 'used' => 0, 'expired' => 0];
        while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = $row['count'];
            $stats['total'] += $row['count'];
        }
        jsonResponse(['stats' => $stats]);
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        jsonResponse(['error' => 'Voucher ID required'], 400);
    }

    $stmt = $db->prepare("DELETE FROM vouchers WHERE id = :id");
    $stmt->execute([':id' => $id]);

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid request'], 400);
