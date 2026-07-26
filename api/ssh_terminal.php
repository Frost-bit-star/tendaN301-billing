<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db/schema.php';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$routerId = $input['router_id'] ?? null;

if (!$routerId) {
    jsonResponse(['error' => 'router_id required'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
$stmt->execute([':id' => $routerId]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    jsonResponse(['error' => 'Router not found'], 404);
}

$basePort = 7681 + intval($routerId);

if ($action === 'start') {
    exec("echo 'jackal' | sudo -S kill $(pgrep -f 'ttyd.*$basePort') 2>/dev/null");

    $host = !empty($router['ip']) && $router['ip'] !== '0.0.0.0' ? $router['ip'] : $router['wireguard_ip'];
    $pass = !empty($router['password']) ? $router['password'] : '1111';

    if (empty($host)) {
        jsonResponse(['error' => 'No IP address for this router'], 400);
    }

    $cmd = "echo 'jackal' | sudo -S ttyd -p $basePort --writable -t fontSize=14 -t theme='{\"background\":\"#1a1a2e\",\"foreground\":\"#e0e0e0\"}' sshpass -p " . escapeshellarg($pass) . " ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 admin@" . escapeshellarg($host) . " 2>&1";
    exec("nohup bash -c " . escapeshellarg($cmd) . " > /dev/null 2>&1 &", $output, $returnCode);

    usleep(500000);

    $fp = @fsockopen('127.0.0.1', $basePort, $errno, $errstr, 2);
    $listening = is_resource($fp);
    if ($listening) fclose($fp);

    jsonResponse([
        'success' => true,
        'port' => $basePort,
        'url' => "/ssh_terminal_frame.php?port=$basePort&router=$routerId",
        'listening' => $listening,
    ]);
}

if ($action === 'stop') {
    exec("echo 'jackal' | sudo -S kill $(pgrep -f 'ttyd.*$basePort') 2>/dev/null");
    jsonResponse(['success' => true, 'message' => 'Terminal closed']);
}

if ($action === 'status') {
    exec("pgrep -f 'ttyd.*$basePort'", $output, $returnCode);
    $running = $returnCode === 0;
    jsonResponse(['running' => $running, 'port' => $basePort]);
}

jsonResponse(['error' => 'Unknown action'], 400);
