<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db/schema.php';
require_once __DIR__ . '/../services/sms.php';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'customers';

    if ($action === 'customers') {
        $voucherCustomers = $db->query("
            SELECT v.phone, v.customer_name, v.code, v.used_at, v.plan_id,
                   p.name as plan_name
            FROM vouchers v
            LEFT JOIN plans p ON v.plan_id = p.id
            WHERE v.status = 'used'
              AND v.phone IS NOT NULL AND v.phone != ''
            ORDER BY v.used_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $billingCustomers = $db->query("
            SELECT b.phone_number as phone, b.name as customer_name, NULL as code,
                   b.created_at as used_at, b.plan_id,
                   p.name as plan_name
            FROM billing b
            LEFT JOIN plans p ON b.plan_id = p.id
            WHERE b.phone_number IS NOT NULL AND b.phone_number != ''
            ORDER BY b.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $all = array_merge($voucherCustomers, $billingCustomers);

        $unique = [];
        $seen = [];
        foreach ($all as $c) {
            $phone = $c['phone'];
            if (!isset($seen[$phone])) {
                $seen[$phone] = true;
                $unique[] = $c;
            }
        }

        jsonResponse(['customers' => $unique, 'total' => count($unique)]);
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'send') {
        $numbers = $input['numbers'] ?? [];
        $message = trim($input['message'] ?? '');

        if (empty($numbers)) {
            jsonResponse(['error' => 'At least one phone number is required'], 400);
        }

        if (empty($message)) {
            jsonResponse(['error' => 'Message is required'], 400);
        }

        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($numbers as $number) {
            $number = preg_replace('/[^0-9]/', '', $number);
            if (empty($number)) continue;

            $result = sendSms($number, $message);
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = ['number' => $number, 'error' => $result['response'] ?? 'HTTP ' . $result['http_code']];
            }
        }

        jsonResponse([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
            'total' => count($numbers),
        ]);
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

jsonResponse(['error' => 'Invalid request method'], 400);
