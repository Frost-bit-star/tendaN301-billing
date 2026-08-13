<?php
// api/v1/helpers.php - Shared functions for all API v1 endpoints

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../db/locale.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../../db/routers.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        require_once __DIR__ . '/../../db/schema.php';
    }
    return $pdo;
}

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input)) return $input;
    if (!empty($_POST)) return $_POST;
    return [];
}

function getAccountId() {
    $token = $_GET['token'] ?? null;
    if (!$token) {
        $input = getInput();
        $token = $input['token'] ?? null;
    }
    if (!$token) {
        respond(['success' => false, 'error' => 'Token required'], 401);
    }

    $db = db();
    $stmt = $db->prepare("SELECT t.account_id, t.created_at, a.timezone FROM tokens t LEFT JOIN accounts a ON a.id = t.account_id WHERE t.token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(['success' => false, 'error' => 'Invalid token'], 401);
    }

    date_default_timezone_set(appValidTimezone($row['timezone'] ?? 'Africa/Dar_es_Salaam'));

    $created = strtotime($row['created_at']);
    if (time() - $created > 86400 * 7) {
        $db->prepare("DELETE FROM tokens WHERE token = ?")->execute([$token]);
        respond(['success' => false, 'error' => 'Token expired, please login again'], 401);
    }

    return $row['account_id'];
}

function isRouterOnline($ip, $port = 80, $timeout = 2) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}
