<?php

require_once __DIR__ . '/../db/schema.php';

header('Content-Type: application/json');

$device = $_GET['device'] ?? '';
$pubkey = $_GET['pubkey'] ?? '';

if (!$device || !$pubkey) {
    echo json_encode([
        'error' => 'missing device or pubkey'
    ]);
    exit;
}

$stmt = $db->prepare("
    UPDATE routers 
    SET wg_pubkey = :pubkey,
        provisioning_status = 'online'
    WHERE device_id = :device
");

$stmt->execute([
    ':pubkey' => $pubkey,
    ':device' => $device
]);

$ip = $db->prepare("
    SELECT wireguard_ip 
    FROM routers 
    WHERE device_id = :device
");

$ip->execute([
    ':device' => $device
]);

$router = $ip->fetch(PDO::FETCH_ASSOC);

if ($router) {
    $allowed = $router['wireguard_ip'];

    exec(
        "echo 'jackal' | sudo -S wg set wg0 peer " .
        escapeshellarg($pubkey) .
        " allowed-ips " .
        escapeshellarg($allowed . '/32') .
        " 2>&1", $output, $returnCode
    );
}

echo json_encode([
    'success' => true,
    'device' => $device,
    'pubkey' => $pubkey
]);
