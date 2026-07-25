<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

function generateDeviceId() {
    return sprintf(
        '%08x-%04x-%04x-%04x-%012x',
        mt_rand(0, 0xffffffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffffffffffff)
    );
}

function generateProvisionToken() {
    return bin2hex(random_bytes(16));
}

function generateWireGuardKeys() {
    $privateKey = trim(shell_exec('wg genkey 2>/dev/null') ?: '');
    if (empty($privateKey)) {
        $privateKey = base64_encode(random_bytes(32));
    }
    $publicKey = trim(shell_exec("echo '$privateKey' | wg pubkey 2>/dev/null") ?: '');
    if (empty($publicKey)) {
        $publicKey = base64_encode(random_bytes(32));
    }
    return ['private' => $privateKey, 'public' => $publicKey];
}

function getServerHost() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($host) || str_starts_with($host, '127.') || str_starts_with($host, 'localhost') || preg_match('/:\d{4,5}$/', $host)) {
        return 'jasiri.stackverify.site';
    }
    return $host;
}

function getServerPublicKey() {
    $keyFile = __DIR__ . '/../db/server_wg_pubkey.txt';
    if (file_exists($keyFile)) {
        return trim(file_get_contents($keyFile));
    }
    $fallback = trim(shell_exec('wg show wg0 public-key 2>/dev/null') ?: '');
    if (!empty($fallback)) {
        file_put_contents($keyFile, $fallback);
        return $fallback;
    }
    return null;
}

function assignWireGuardIP($db) {
    $stmt = $db->query("SELECT wireguard_ip FROM routers WHERE wireguard_ip IS NOT NULL AND type='mikrotik'");
    $used = $stmt->fetchAll(PDO::FETCH_COLUMN);
    for ($i = 2; $i < 254; $i++) {
        $ip = "10.100.0.$i";
        if (!in_array($ip, $used)) return $ip;
    }
    return null;
}

function addWireGuardPeer($peerPubKey, $allowedIP) {
    $escaped = escapeshellarg($peerPubKey);
    $ipEsc = escapeshellarg("$allowedIP/32");
    exec("echo 'jackal' | sudo -S wg set wg0 peer $escaped allowed-ips $ipEsc 2>&1", $output, $returnCode);
    return $returnCode === 0;
}

function removeWireGuardPeer($peerPubKey) {
    $escaped = escapeshellarg($peerPubKey);
    exec("echo 'jackal' | sudo -S wg set wg0 peer $escaped remove 2>&1", $output, $returnCode);
    return $returnCode === 0;
}

function generateProvisionScript($db, $routerId, $name, $wireguardIP, $deviceId) {
    $wgKeys = generateWireGuardKeys();
    $listenPort = 13231 + ($routerId % 1000);
    $timestamp = date('Ymd_His');
    $serverHost = getServerHost();
    $serverPubKey = getServerPublicKey();

    if (!$serverPubKey) {
        return ['error' => 'Server WireGuard public key not found. Run wireguard-setup.sh first.'];
    }

    $script = "# Jasiri WiFi Auto-Provisioning Script\n";
    $script .= "# Device: $name - $deviceId\n";
    $script .= "# Generated: " . date('Y-m-d H:i:s') . " UTC\n";
    $script .= "# Client WireGuard IP: $wireguardIP\n\n";

    $script .= "/system identity set name=\"$name\"\n\n";

    $script .= ":do { /interface bridge remove [find name=jasiri-bridge] } on-error={}\n";
    $script .= ":do { /interface bridge add comment=\"Jasiri WiFi Bridge\" name=jasiri-bridge } on-error={}\n\n";

    $script .= ":do { /ip address remove [find interface=jasiri-bridge] } on-error={}\n";
    $script .= ":do { /ip address add address=10.10.0.1/24 comment=\"Added by Jasiri\" interface=jasiri-bridge } on-error={}\n";
    $script .= ":do { /ip pool remove [find name=jasiri-pool] } on-error={}\n";
    $script .= ":do { /ip pool add name=jasiri-pool ranges=10.10.0.2-10.10.0.254 } on-error={}\n";
    $script .= ":do { /ip dhcp-server remove [find name=jasiri-dhcp] } on-error={}\n";
    $script .= ":do { /ip dhcp-server add address-pool=jasiri-pool disabled=no interface=jasiri-bridge name=jasiri-dhcp } on-error={}\n";
    $script .= ":do { /ip dhcp-server network remove [find address~\"10.10.0.0\"] } on-error={}\n";
    $script .= ":do { /ip dhcp-server network add address=10.10.0.0/24 dns-server=8.8.8.8,8.8.4.4 gateway=10.10.0.1 } on-error={}\n\n";

    $script .= ":do { /radius remove [find service=hotspot] } on-error={}\n";
    $script .= ":do { /radius remove [find service=ppp] } on-error={}\n";
    $script .= ":do { /radius add address=10.100.0.1 secret=\"jasiri123\" service=hotspot authentication-port=1812 accounting-port=1813 timeout=3s realm=\"$deviceId\" comment=\"Jasiri RADIUS\" } on-error={}\n";
    $script .= ":do { /radius add address=10.100.0.1 secret=\"jasiri123\" service=ppp authentication-port=1812 accounting-port=1813 timeout=3s realm=\"$deviceId\" comment=\"Jasiri RADIUS PPP\" } on-error={}\n";
    $script .= "/radius incoming set accept=yes port=3799\n\n";

    $script .= ":do { /interface wireguard remove [find name=jasiri-wg] } on-error={}\n";
    $script .= ":do { /interface wireguard add mtu=1420 name=jasiri-wg private-key=\"{$wgKeys['private']}\" listen-port=$listenPort } on-error={}\n\n";

    $script .= ":do { /ip address remove [find interface=jasiri-wg] } on-error={}\n";
    $script .= ":do { /ip address add address=$wireguardIP/24 interface=jasiri-wg } on-error={}\n\n";

    $script .= ":do { /interface wireguard peers remove [find interface=jasiri-wg] } on-error={}\n";
    $script .= ":do { /interface wireguard peers add interface=jasiri-wg public-key=\"$serverPubKey\" endpoint-address=$serverHost endpoint-port=13231 allowed-address=10.100.0.0/24 persistent-keepalive=25s } on-error={}\n\n";

    $script .= "/ip service set api-ssl address=10.100.0.0/24 disabled=no port=8729\n";
    $script .= "/ip service set ssh address=10.100.0.0/24 disabled=no\n";
    $script .= "/ip service set www address=10.100.0.0/24 disabled=no port=80\n\n";

    $apiUser = 'jasiri-api';
    $apiPass = 'api_' . bin2hex(random_bytes(4));
    $script .= ":do { /user remove [find name=$apiUser] } on-error={}\n";
    $script .= "/user add name=$apiUser password=\"$apiPass\" group=full comment=\"Jasiri Management API\"\n\n";

    $fwRules = [
        ["Allow WireGuard", "protocol=udp dst-port=$listenPort"],
        ["Allow API SSL", "protocol=tcp dst-port=8729 src-address=10.100.0.0/24"],
        ["Allow REST (www)", "protocol=tcp dst-port=80 src-address=10.100.0.0/24"],
        ["Allow SSH from WireGuard", "protocol=tcp dst-port=22 src-address=10.100.0.0/24"],
        ["Allow SNMP from WireGuard", "protocol=udp dst-port=161 src-address=10.100.0.0/24"],
        ["Allow RADIUS CoA from server", "dst-port=3799 protocol=udp src-address=10.100.0.0/24"],
    ];
    foreach ($fwRules as [$comment, $params]) {
        $script .= ":do { /ip firewall filter remove [find chain=input comment=\"$comment\"] } on-error={}\n";
        $script .= "/ip firewall filter add action=accept chain=input comment=\"$comment\" $params\n";
    }
    $script .= "\n";

    $script .= "/snmp set enabled=yes\n";
    $script .= ":do { /snmp community remove [find name=jasiri] } on-error={}\n";
    $script .= "/snmp community add name=jasiri addresses=10.100.0.0/24\n\n";

    $script .= ":do { /ip firewall nat remove [find comment=\"Jasiri Internet Access\"] } on-error={}\n";
    $script .= "/ip firewall nat add action=masquerade chain=srcnat comment=\"Jasiri Internet Access\"\n\n";

    $script .= "/log info \"Jasiri WiFi provisioning completed successfully\"\n";
    $script .= "/log info \"Device ID: $deviceId\"\n";
    $script .= "/log info \"WireGuard Client IP: $wireguardIP\"\n\n";

    $script .= ":put \"======================================\"\n";
    $script .= ":put \"Jasiri WiFi Provisioning Complete!\"\n";
    $script .= ":put \"======================================\"\n";
    $script .= ":put \"Device ID: $deviceId\"\n";
    $script .= ":put \"Client IP: $wireguardIP\"\n";
    $script .= ":put \"Server IP: 10.100.0.1\"\n";
    $script .= ":put \"======================================\"\n";

    return [
        'script' => $script,
        'api_user' => $apiUser,
        'api_pass' => $apiPass,
        'mikrotik_privkey' => $wgKeys['private'],
        'mikrotik_pubkey' => $wgKeys['public'],
        'listen_port' => $listenPort,
        'timestamp' => $timestamp,
    ];
}

function isMikroTikOnline($wireguardIP) {
    $fp = @fsockopen($wireguardIP, 8729, $errno, $errstr, 3);
    if (is_resource($fp)) {
        fclose($fp);
        return true;
    }
    $output = [];
    exec("wg show wg0 latest-handshakes 2>/dev/null", $output);
    return false;
}

function checkWgHandshake($peerPubKey) {
    $output = [];
    exec("wg show wg0 latest-handshakes 2>/dev/null", $output);
    foreach ($output as $line) {
        if (strpos($line, $peerPubKey) !== false) {
            $parts = explode("\t", $line);
            if (isset($parts[1]) && intval($parts[1]) > 0) {
                $handshakeTime = intval($parts[1]);
                $now = time();
                if (($now - $handshakeTime) < 180) {
                    return true;
                }
            }
        }
    }
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'register';

    if ($action === 'register') {
        $name = trim($input['name'] ?? '');
        $location = trim($input['location'] ?? '');

        if (empty($name)) {
            jsonResponse(['error' => 'Device name is required'], 400);
        }

        $serverPubKey = getServerPublicKey();
        if (!$serverPubKey) {
            jsonResponse(['error' => 'Server WireGuard not configured. Run wireguard-setup.sh first.'], 500);
        }

        $deviceId = generateDeviceId();
        $provisionToken = generateProvisionToken();
        $wireguardIP = assignWireGuardIP($db);

        if (!$wireguardIP) {
            jsonResponse(['error' => 'No available WireGuard IPs (10.100.0.2-254 exhausted)'], 500);
        }

        $tenantId = $_SESSION['account_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO routers (name, ip, port, password, type, location, device_id, wireguard_ip, provisioning_status, provision_token, tenant_id)
            VALUES (:name, '0.0.0.0', 8729, '', 'mikrotik', :location, :device_id, :wireguard_ip, 'pending', :provision_token, :tenant_id)
        ");
        $stmt->execute([
            ':name' => $name,
            ':location' => $location,
            ':device_id' => $deviceId,
            ':wireguard_ip' => $wireguardIP,
            ':provision_token' => $provisionToken,
            ':tenant_id' => $tenantId,
        ]);

        $routerId = $db->lastInsertId();

        $provData = generateProvisionScript($db, $routerId, $name, $wireguardIP, $deviceId);

        if (isset($provData['error'])) {
            jsonResponse(['error' => $provData['error']], 500);
        }

        $stmt = $db->prepare("UPDATE routers SET password = :pass, wg_pubkey = :pubkey WHERE id = :id");
        $stmt->execute([
            ':pass' => $provData['api_pass'],
            ':pubkey' => $provData['mikrotik_pubkey'],
            ':id' => $routerId,
        ]);

        addWireGuardPeer($provData['mikrotik_pubkey'], $wireguardIP);

        jsonResponse([
            'success' => true,
            'router_id' => $routerId,
            'device_id' => $deviceId,
            'provision_token' => $provisionToken,
            'wireguard_ip' => $wireguardIP,
            'server_public_key' => $serverPubKey,
            'mikrotik_public_key' => $provData['mikrotik_pubkey'],
            'api_user' => $provData['api_user'],
            'api_pass' => $provData['api_pass'],
            'timestamp' => $provData['timestamp'],
            'fetch_command' => "/tool fetch mode=https url=\"https://" . getServerHost() . "/provision/$provisionToken\" dst-path=jasiri_{$provData['timestamp']}.rsc; :delay 2s; /import jasiri_{$provData['timestamp']}.rsc;",
        ]);
    }

    if ($action === 'check_status') {
        $routerId = $input['router_id'] ?? null;
        if (!$routerId) {
            jsonResponse(['error' => 'router_id required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
        $stmt->execute([':id' => $routerId]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            jsonResponse(['error' => 'Router not found'], 404);
        }

        $wgIP = $router['wireguard_ip'];
        $online = @fsockopen($wgIP, 8729, $errno, $errstr, 3);
        $isOnline = is_resource($online);
        if ($isOnline) fclose($online);

        if (!$isOnline && !empty($router['wg_pubkey'])) {
            $isOnline = checkWgHandshake($router['wg_pubkey']);
        }

        $newStatus = $isOnline ? 'online' : 'offline';
        $stmt = $db->prepare("UPDATE routers SET provisioning_status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $routerId]);

        jsonResponse([
            'router_id' => (int)$routerId,
            'status' => $newStatus,
            'wireguard_ip' => $wgIP,
        ]);
    }

    if ($action === 'save_config') {
        $routerId = $input['router_id'] ?? null;
        if (!$routerId) {
            jsonResponse(['error' => 'router_id required'], 400);
        }

        $stmt = $db->prepare("UPDATE routers SET provisioning_status = 'online', last_provisioned_at = :ts WHERE id = :id");
        $stmt->execute([':ts' => date('Y-m-d H:i:s'), ':id' => $routerId]);

        jsonResponse(['success' => true, 'message' => 'Configuration saved']);
    }

    jsonResponse(['error' => 'Unknown action'], 400);
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $tenantId = $_SESSION['account_id'] ?? null;
        $role = $_SESSION['role'] ?? null;

        if ($role === 'superadmin') {
            $stmt = $db->query("SELECT * FROM routers WHERE type = 'mikrotik' ORDER BY id DESC");
        } else {
            $stmt = $db->prepare("SELECT * FROM routers WHERE type = 'mikrotik' AND tenant_id = :tid ORDER BY id DESC");
            $stmt->execute([':tid' => $tenantId]);
        }

        $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($routers as &$r) {
            $wgIP = $r['wireguard_ip'];
            $fp = @fsockopen($wgIP, 8729, $errno, $errstr, 3);
            $r['online'] = is_resource($fp);
            if ($r['online']) fclose($fp);

            if (!$r['online'] && !empty($r['wg_pubkey'])) {
                $r['online'] = checkWgHandshake($r['wg_pubkey']);
            }

            $newStatus = $r['online'] ? 'online' : $r['provisioning_status'];
            if ($newStatus !== $r['provisioning_status']) {
                $stmt = $db->prepare("UPDATE routers SET provisioning_status = :status WHERE id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $r['id']]);
                $r['provisioning_status'] = $newStatus;
            }
        }

        jsonResponse(['routers' => $routers]);
    }

    if ($action === 'provision_script') {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            jsonResponse(['error' => 'Token required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM routers WHERE provision_token = :token AND type = 'mikrotik'");
        $stmt->execute([':token' => $token]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            jsonResponse(['error' => 'Invalid token'], 404);
        }

        $provData = generateProvisionScript($db, $router['id'], $router['name'], $router['wireguard_ip'], $router['device_id']);

        if (isset($provData['error'])) {
            jsonResponse(['error' => $provData['error']], 500);
        }

        $stmt = $db->prepare("UPDATE routers SET provisioning_status = 'provisioning', wg_pubkey = :pubkey, last_provisioned_at = :ts WHERE id = :id");
        $stmt->execute([
            ':pubkey' => $provData['mikrotik_pubkey'],
            ':ts' => date('Y-m-d H:i:s'),
            ':id' => $router['id'],
        ]);

        header('Content-Type: text/plain');
        echo $provData['script'];
        exit;
    }

    if ($action === 'check_status') {
        $routerId = $_GET['router_id'] ?? null;
        if (!$routerId) {
            jsonResponse(['error' => 'router_id required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
        $stmt->execute([':id' => $routerId]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            jsonResponse(['error' => 'Router not found'], 404);
        }

        $wgIP = $router['wireguard_ip'];
        $fp = @fsockopen($wgIP, 8729, $errno, $errstr, 3);
        $isOnline = is_resource($fp);
        if ($isOnline) fclose($fp);

        if (!$isOnline && !empty($router['wg_pubkey'])) {
            $isOnline = checkWgHandshake($router['wg_pubkey']);
        }

        $newStatus = $isOnline ? 'online' : $router['provisioning_status'];
        if ($newStatus !== $router['provisioning_status']) {
            $stmt = $db->prepare("UPDATE routers SET provisioning_status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $routerId]);
        }

        jsonResponse([
            'router_id' => (int)$routerId,
            'status' => $newStatus,
            'wireguard_ip' => $wgIP,
        ]);
    }

    if ($action === 'wg_status') {
        $output = [];
        exec("echo 'jackal' | sudo -S wg show wg0 2>/dev/null", $output, $returnCode);
        jsonResponse([
            'success' => $returnCode === 0,
            'wg_output' => implode("\n", $output),
        ]);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
