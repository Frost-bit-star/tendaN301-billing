<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/schema.php';

$device = $_GET['device'] ?? '';
$pubkey = $_GET['pubkey'] ?? '';

if (empty($device) || empty($pubkey)) {
    http_response_code(400);
    echo json_encode(["error" => "missing data"]);
    exit;
}

$stmt = $db->prepare("SELECT * FROM routers WHERE device_id = :device AND type = 'mikrotik'");
$stmt->execute([':device' => $device]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    http_response_code(404);
    echo json_encode(["error" => "router not found"]);
    exit;
}

$stmt = $db->prepare("UPDATE routers SET wg_pubkey = :pubkey, provisioning_status = 'online', last_provisioned_at = :ts WHERE id = :id");
$stmt->execute([
    ':pubkey' => $pubkey,
    ':ts' => date('Y-m-d H:i:s'),
    ':id' => $router['id'],
]);

$allowed = $router['wireguard_ip'];

exec("echo 'jackal' | sudo -S wg set wg0 peer " . escapeshellarg($pubkey) . " allowed-ips " . escapeshellarg($allowed . '/32') . " 2>&1", $output, $returnCode);

echo json_encode([
    "success" => true,
    "wireguard_ip" => $allowed,
]);
