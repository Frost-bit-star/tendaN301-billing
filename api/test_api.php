<?php
require_once __DIR__ . '/mikrotik_api.php';

$api = new MikroTikAPI("192.168.88.1", 8728, "admin", "12345678");
$api->connect();

$identity = $api->command(['/system/identity/print']);
echo "Identity: " . json_encode($identity) . "\n";

$svcs = $api->command(['/ip/service/print']);
echo "Services count: " . count($svcs) . "\n";

$ssh = array_filter($svcs, fn($s) => $s['name'] ?? '' === 'ssh');
echo "SSH: " . json_encode($ssh) . "\n";

$api->close();
