<?php

require_once __DIR__ . '/../db/schema.php';

header('Content-Type: application/json');

$device = $_GET['device'] ?? '';
$pubkey = $_GET['pubkey'] ?? '';

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . '/wireguard_register.log';

function wg_log($msg) {
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}

wg_log("--- REGISTER REQUEST ---");
wg_log("Device: $device");
wg_log("Pubkey: $pubkey");

if (!$device || !$pubkey) {
    $err = 'missing device or pubkey';
    wg_log("REJECTED: $err");
    echo json_encode(['error' => $err]);
    exit;
}

$stmt = $db->prepare("
    UPDATE routers 
    SET wg_pubkey = :pubkey,
        provisioning_status = 'registered'
    WHERE device_id = :device
");

$stmt->execute([
    ':pubkey' => $pubkey,
    ':device' => $device
]);

$matched = $stmt->rowCount();
wg_log("DB update: $matched row(s) matched");

if ($matched === 0) {
    wg_log("WARNING: no router with device_id=$device");
}

$ip = $db->prepare("
    SELECT wireguard_ip 
    FROM routers 
    WHERE device_id = :device
");

$ip->execute([
    ':device' => $device
]);

$router = $ip->fetch(PDO::FETCH_ASSOC);

if ($router && !empty($router['wireguard_ip'])) {
    $allowed = $router['wireguard_ip'];
    $cmd = "echo 'jackal' | sudo -S wg set wg0 peer " .
           escapeshellarg($pubkey) .
           " allowed-ips " .
           escapeshellarg($allowed . '/32') .
           " 2>&1";

    wg_log("Command: $cmd");
    exec($cmd, $output, $returnCode);
    wg_log("Return code: $returnCode");
    if ($output) wg_log("Output: " . implode("\n", $output));

    // Persist the peer so it survives a server reboot
    $saveCmd = "echo 'jackal' | sudo -S wg showconf wg0 > /tmp/wg0_new.conf 2>&1 && echo 'jackal' | sudo -S cp /tmp/wg0_new.conf /etc/wireguard/wg0.conf 2>&1";
    exec($saveCmd, $saveOutput, $saveReturn);
    wg_log("Persist config return code: $saveReturn");
    if ($saveOutput) wg_log("Persist output: " . implode("\n", $saveOutput));

    $wgShow = [];
    exec("wg show wg0 2>&1", $wgShow);
    wg_log("wg show wg0:\n" . implode("\n", $wgShow));
} else {
    wg_log("ERROR: no wireguard_ip for device_id=$device");
}

wg_log("--- DONE ---");

echo json_encode([
    'success' => true,
    'device' => $device,
    'pubkey' => $pubkey
]);
