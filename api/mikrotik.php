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
require_once __DIR__ . '/mikrotik_api.php';

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

function getServerHost() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($host) || str_starts_with($host, '127.') || str_starts_with($host, 'localhost')) {
        return 'jasiri.stackverify.site';
    }
    return $host;
}

function getServerScheme() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($host) || str_starts_with($host, '127.') || str_starts_with($host, 'localhost') || preg_match('/^192\.168\./', $host) || preg_match('/^10\./', $host)) {
        return 'http';
    }
    return 'https';
}

function getServerWgPort() {
    $output = [];
    exec("wg show wg0 listen-port 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0 && !empty($output[0])) {
        return intval(trim($output[0]));
    }
    $confFile = '/etc/wireguard/wg0.conf';
    if (file_exists($confFile)) {
        $conf = file_get_contents($confFile);
        if (preg_match('/ListenPort\s*=\s*(\d+)/', $conf, $m)) {
            return intval($m[1]);
        }
    }
    return 13231;
}

function getWireGuardEndpoint() {
    return '156.232.88.212';
}

function getServerPublicKey() {
    $keyFile = __DIR__ . '/../db/server_wg_pubkey.txt';
    $actual = trim(shell_exec('wg show wg0 public-key 2>/dev/null') ?: '');
    if (!empty($actual)) {
        if (!file_exists($keyFile) || trim(file_get_contents($keyFile)) !== $actual) {
            file_put_contents($keyFile, $actual);
        }
        return $actual;
    }
    if (file_exists($keyFile)) {
        return trim(file_get_contents($keyFile));
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
    exec("wg show wg0 2>/dev/null", $wgOutput, $wgCheck);
    if ($wgCheck !== 0) {
        return false;
    }
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
    $listenPort = getServerWgPort();
    $timestamp = date('Ymd_His');
    $serverHost = getServerHost();
    $serverScheme = getServerScheme();
    $serverPubKey = getServerPublicKey();
    $wgEndpoint = getWireGuardEndpoint();
    $hasWG = !empty($serverPubKey) && !empty($wireguardIP) && $wireguardIP !== '0.0.0.0';

    $script = "# Jasiri WiFi Auto-Provisioning Script\n";
    $script .= "# Device: $name - $deviceId\n";
    $script .= "# Generated: " . date('Y-m-d H:i:s') . " UTC\n";
    $script .= "# Client WireGuard IP: $wireguardIP\n\n";

    $script .= "/system identity set name=\"$name\"\n\n";

    $script .= ":do { /interface bridge remove [find name=jasiri-bridge] } on-error={}\n";
    $script .= "/interface bridge add comment=\"Jasiri WiFi Bridge\" name=jasiri-bridge\n";
    $script .= "# --- Wireless Configuration ---\n";
    $script .= "/interface wireless security-profiles set [find default=yes] mode=none\n";
    $script .= "/interface wireless set [find default-name=wlan1] disabled=no mode=ap-bridge ssid=\"Jasiri WiFi\" security-profile=default\n";
    $script .= ":do { /interface bridge port remove [find interface=wlan1] } on-error={}\n";
    $script .= "/interface bridge port add bridge=jasiri-bridge interface=wlan1\n\n";

    $script .= ":do { /ip address remove [find interface=jasiri-bridge] } on-error={}\n";
    $script .= "/ip address add address=10.10.0.1/24 comment=\"Added by Jasiri\" interface=jasiri-bridge\n";
    $script .= ":do { /ip pool remove [find name=jasiri-pool] } on-error={}\n";
    $script .= "/ip pool add name=jasiri-pool ranges=10.10.0.2-10.10.0.254\n";
    $script .= ":do { /ip dhcp-server remove [find name=jasiri-dhcp] } on-error={}\n";
    $script .= "/ip dhcp-server add address-pool=jasiri-pool disabled=no interface=jasiri-bridge name=jasiri-dhcp\n";
    $script .= ":do { /ip dhcp-server network remove [find address~\"10.10.0.0\"] } on-error={}\n";
    $script .= "/ip dhcp-server network add address=10.10.0.0/24 dns-server=10.10.0.1,8.8.8.8,8.8.4.4 gateway=10.10.0.1\n\n";

    $script .= ":do { /radius remove [find service=hotspot] } on-error={}\n";
    $script .= ":do { /radius remove [find service=ppp] } on-error={}\n";
    $script .= "/radius add address=10.100.0.1 secret=\"jasiri123\" service=hotspot authentication-port=1812 accounting-port=1813 timeout=3s realm=\"$deviceId\" comment=\"Jasiri RADIUS\"\n";
    $script .= "/radius add address=10.100.0.1 secret=\"jasiri123\" service=ppp authentication-port=1812 accounting-port=1813 timeout=3s realm=\"$deviceId\" comment=\"Jasiri RADIUS PPP\"\n";
    $script .= "/radius incoming set accept=yes port=3799\n\n";

    if ($hasWG) {
        $script .= ":do { /interface wireguard remove [find name=jasiri-wg] } on-error={}\n";
        $script .= "/interface wireguard add mtu=1420 name=jasiri-wg listen-port=13241\n\n";

        $script .= ":do { /ip address remove [find interface=jasiri-wg] } on-error={}\n";
        $script .= "/ip address add address=$wireguardIP/24 interface=jasiri-wg\n\n";

        $script .= ":do { /interface wireguard peers remove [find interface=jasiri-wg] } on-error={}\n";
        $script .= "/interface wireguard peers add interface=jasiri-wg public-key=\"$serverPubKey\" endpoint-address=$wgEndpoint endpoint-port=$listenPort allowed-address=10.100.0.0/24 persistent-keepalive=25s\n\n";

        $script .= ":local wgPub [/interface wireguard get [find name=jasiri-wg] public-key]\n";
        $script .= ":local url \"{$serverScheme}://{$serverHost}/api/wireguard_register.php\"\n";
        $script .= ":local postData (\"device={$deviceId}&pubkey=\" . \$wgPub)\n";
        $script .= ":local r [/tool fetch mode=https url=\$url http-method=post http-data=\$postData http-header-field=\"Content-Type: application/x-www-form-urlencoded\" keep-result=no as-value]\n";
        $script .= ":log info (\"WG REGISTER: \" . (\$r->\"status\"))\n\n";
    } else {
        $script .= "# WireGuard skipped (server not configured)\n\n";
    }

    $serverSubnet = '192.168.0.0/16';
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $serverIP = preg_replace('/:\d+$/', '', $serverHost);
    if (preg_match('/^(\d+\.\d+\.\d+)\.\d+$/', $serverIP, $m)) {
        $serverSubnet = $m[1] . '.0/24';
    }

    if ($hasWG) {
        $script .= "/ip service set api-ssl address=10.100.0.0/24 disabled=no port=8729\n";
        $script .= "/ip service set ssh address=$serverSubnet,10.100.0.0/24 disabled=no\n";
        $script .= "/ip service set www address=10.100.0.0/24 disabled=no port=8080\n\n";
    } else {
        $script .= "/ip service set api-ssl address=0.0.0.0/0 disabled=no port=8729\n";
        $script .= "/ip service set ssh address=0.0.0.0/0 disabled=no\n";
        $script .= "/ip service set www address=0.0.0.0/0 disabled=no port=8080\n\n";
    }

    $apiUser = 'jasiri-api';

    // Reuse the existing password if the router already exists
    $stmt = $db->prepare("SELECT password FROM routers WHERE id = ?");
    $stmt->execute([$routerId]);
    $apiPass = $stmt->fetchColumn();

    if (empty($apiPass)) {
        $apiPass = 'api_' . bin2hex(random_bytes(4));

        $stmt = $db->prepare("UPDATE routers SET password = ? WHERE id = ?");
        $stmt->execute([$apiPass, $routerId]);
    }

    $script .= ":do { /user remove [find name=$apiUser] } on-error={}\n";
    $script .= "/user add name=$apiUser password=\"$apiPass\" group=full comment=\"Jasiri Management API\"\n\n";

    $fwSrc = $hasWG ? "src-address=10.100.0.0/24" : "src-address=0.0.0.0/0";
    $fwSrcExtra = $hasWG ? "src-address=$serverSubnet" : "";
    $fwRules = [
        ["Allow WireGuard", "protocol=udp dst-port=13241 src-address=10.100.0.1"],
        ["Allow API", "protocol=tcp dst-port=8728 $fwSrc"],
        ["Allow REST (www)", "protocol=tcp dst-port=8080 $fwSrc"],
        ["Allow SSH from WireGuard", "protocol=tcp dst-port=22 $fwSrc"],
        ["Allow SNMP from WireGuard", "protocol=udp dst-port=161 $fwSrc"],
        ["Allow RADIUS CoA from server", "dst-port=3799 protocol=udp src-address=10.100.0.0/24"],
    ];
    foreach ($fwRules as [$comment, $params]) {
        $script .= ":do { /ip firewall filter remove [find chain=input comment=\"$comment\"] } on-error={}\n";
        $script .= "/ip firewall filter add action=accept chain=input comment=\"$comment\" $params\n";
    }
    if ($hasWG && !empty($fwSrcExtra)) {
        $extraComments = [
            ["Allow API from LAN", "protocol=tcp dst-port=8728 $fwSrcExtra"],
            ["Allow WWW from LAN", "protocol=tcp dst-port=8080 $fwSrcExtra"],
            ["Allow SSH from LAN", "protocol=tcp dst-port=22 $fwSrcExtra"],
        ];
        foreach ($extraComments as [$comment, $params]) {
            $script .= ":do { /ip firewall filter remove [find chain=input comment=\"$comment\"] } on-error={}\n";
            $script .= "/ip firewall filter add action=accept chain=input comment=\"$comment\" $params\n";
        }
    }
    $script .= "\n";

    $script .= "/snmp set enabled=yes\n";
    $script .= ":do { /snmp community remove [find name=jasiri] } on-error={}\n";
    $script .= "/snmp community add name=jasiri addresses=10.100.0.0/24\n\n";

    $script .= ":do { /ip firewall nat remove [find comment=\"Jasiri Internet Access\"] } on-error={}\n";
    $script .= "/ip firewall nat add action=masquerade chain=srcnat comment=\"Jasiri Internet Access\"\n\n";

    $script .= "# --- Hotspot / Captive Portal ---\n";
    $script .= ":do { /ip hotspot profile remove [find name=hs-prof1] } on-error={}\n";
    $script .= "/ip hotspot profile add name=hs-prof1 hotspot-address=10.10.0.1 dns-name=jasiri.local login=http-pap\n\n";

    $script .= ":do { /ip hotspot remove [find name=hotspot1] } on-error={}\n";
    $script .= "/ip hotspot add name=hotspot1 interface=jasiri-bridge address-pool=jasiri-pool profile=hs-prof1\n";
    $script .= "/ip hotspot enable [find name=\"hotspot1\"]\n\n";

    $script .= ":do { /ip hotspot walled-garden remove [find dst-host~\"jasiri\"] } on-error={}\n";
    $script .= "/ip hotspot walled-garden add action=allow dst-host=jasiri.stackverify.site\n";
    $script .= "/ip hotspot walled-garden add action=allow dst-host=*.jasiri.stackverify.site\n\n";

    $script .= ":do { /ip dns static remove [find name=jasiri.local] } on-error={}\n";
    $script .= "/ip dns static add name=jasiri.local address=10.10.0.1 ttl=1m\n\n";

    $script .= "/ip dns set allow-remote-requests=yes servers=8.8.8.8,8.8.4.4\n\n";

    $script .= "/tool fetch mode={$serverScheme} url=\"{$serverScheme}://{$serverHost}/api/hotspot_login.php?device={$deviceId}\" dst-path=hotspot/login.html keep-result=yes as-value\n\n";

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
        'listen_port' => $listenPort,
        'timestamp' => $timestamp,
    ];
}

function isRouterOnlineWg($router) {
    if (empty($router['wg_pubkey'])) return false;

    $wgIP = $router['wireguard_ip'] ?? '';
    if (empty($wgIP)) return false;

    // First check if WireGuard peer exists at all
    if (!peerExists($router['wg_pubkey'])) return false;

    // Try connecting to API port — if TCP succeeds, device is online
    $fp = @fsockopen($wgIP, 8729, $errno, $errstr, 3);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

function peerExists($peerPubKey) {
    $output = [];
    exec("wg show wg0 peers | grep -c " . escapeshellarg($peerPubKey), $output, $returnCode);
    $count = intval(trim(implode('', $output)));
    return $count > 0;
}

function checkWgHandshake($peerPubKey) {
    $output = [];
    exec("wg show wg0 latest-handshakes | grep " . escapeshellarg($peerPubKey), $output);
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
        $lanIP = trim($input['lan_ip'] ?? '');

        if (empty($name)) {
            jsonResponse(['error' => 'Device name is required'], 400);
        }

        $serverPubKey = getServerPublicKey();

        $deviceId = generateDeviceId();
        $provisionToken = generateProvisionToken();

        $serverPubKeyNeeded = !empty($serverPubKey);
        if ($serverPubKeyNeeded) {
            $wireguardIP = assignWireGuardIP($db);
            if (!$wireguardIP) {
                jsonResponse(['error' => 'No available WireGuard IPs (10.100.0.2-254 exhausted)'], 500);
            }
        } else {
            $wireguardIP = null;
        }

        $tenantId = $_SESSION['account_id'] ?? null;
        $storedIP = !empty($lanIP) ? $lanIP : '0.0.0.0';

        $stmt = $db->prepare("
            INSERT INTO routers (name, ip, port, password, type, location, device_id, wireguard_ip, provisioning_status, provision_token, tenant_id)
            VALUES (:name, :ip, 8728, '12345678', 'mikrotik', :location, :device_id, :wireguard_ip, 'pending', :provision_token, :tenant_id)
        ");
        $stmt->execute([
            ':name' => $name,
            ':ip' => $storedIP,
            ':location' => $location,
            ':device_id' => $deviceId,
            ':wireguard_ip' => $wireguardIP ?? '0.0.0.0',
            ':provision_token' => $provisionToken,
            ':tenant_id' => $tenantId,
        ]);

        $routerId = $db->lastInsertId();

        $provData = generateProvisionScript($db, $routerId, $name, $wireguardIP, $deviceId);

        if (isset($provData['error'])) {
            jsonResponse(['error' => $provData['error']], 500);
        }

        jsonResponse([
            'success' => true,
            'router_id' => $routerId,
            'device_id' => $deviceId,
            'provision_token' => $provisionToken,
            'wireguard_ip' => $wireguardIP,
            'server_public_key' => $serverPubKey,
            'api_user' => $provData['api_user'],
            'api_pass' => $provData['api_pass'],
            'timestamp' => $provData['timestamp'],
            'fetch_command' => "/tool fetch mode={$serverScheme} url=\"{$serverScheme}://" . getServerHost() . "/provision/$provisionToken\" dst-path=jasiri_{$provData['timestamp']}.rsc; :delay 2s; /import jasiri_{$provData['timestamp']}.rsc;",
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

        $isOnline = isRouterOnlineWg($router);

        $newStatus = $isOnline ? 'online' : 'offline';
        $stmt = $db->prepare("UPDATE routers SET provisioning_status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $routerId]);

        jsonResponse([
            'router_id' => (int)$routerId,
            'status' => $newStatus,
            'wireguard_ip' => $router['wireguard_ip'],
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

    if ($action === 'delete') {
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

        if (!empty($router['wg_pubkey'])) {
            removeWireGuardPeer($router['wg_pubkey']);
        }

        $stmt = $db->prepare("DELETE FROM routers WHERE id = :id");
        $stmt->execute([':id' => $routerId]);

        jsonResponse(['success' => true, 'message' => 'Device deleted']);
    }

    if ($action === 'configure_wireless') {
        $routerId = intval($input['router_id'] ?? 0);
        $ssid = trim($input['ssid'] ?? '');
        $security = $input['security'] ?? 'open';

        if (!$routerId || empty($ssid)) {
            jsonResponse(['error' => 'router_id and ssid required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
        $stmt->execute([':id' => $routerId]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            jsonResponse(['error' => 'Router not found'], 404);
        }

        $apiIP = !empty($router['wireguard_ip']) ? $router['wireguard_ip'] : $router['ip'];
        $apiPort = intval($router['port'] ?: 8729);

        try {
            $api = new MikroTikAPI($apiIP, $apiPort, 'jasiri-api', $router['password'] ?? '');
            $api->connect();

            if ($security === 'open') {
                try { $api->setWirelessProfile('jasiri-open', 'none', 'none'); } catch (Exception $e) { /* some RouterOS versions reject this */ }
                $api->setWireless($ssid, 'jasiri-open', 'ap-bridge');
            } else {
                $api->setWireless($ssid, 'default', 'ap-bridge');
            }

            $api->setBridgePort('wlan1', 'jasiri-bridge');
            $api->close();

            jsonResponse(['success' => true, 'message' => "Wireless configured: $ssid"]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'API connection failed: ' . $e->getMessage()], 500);
        }
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
            $r['online'] = isRouterOnlineWg($r);

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

        $stmt = $db->prepare("UPDATE routers SET provisioning_status = 'provisioning', last_provisioned_at = :ts WHERE id = :id");
        $stmt->execute([
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

        if (ctype_digit($routerId)) {
            $stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
            $stmt->execute([':id' => $routerId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM routers WHERE (device_id = :id OR provision_token = :id) AND type = 'mikrotik'");
            $stmt->execute([':id' => $routerId]);
        }
        $router = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            jsonResponse(['error' => 'Router not found'], 404);
        }

        $isOnline = isRouterOnlineWg($router);

        $newStatus = $isOnline ? 'online' : $router['provisioning_status'];
        if ($newStatus !== $router['provisioning_status']) {
            $stmt = $db->prepare("UPDATE routers SET provisioning_status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $routerId]);
        }

        jsonResponse([
            'router_id' => (int)$routerId,
            'status' => $newStatus,
            'wireguard_ip' => $router['wireguard_ip'],
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

    if ($action === 'bandwidth') {
        $routerId = $_GET['router_id'] ?? null;
        if (!$routerId) jsonResponse(['error' => 'router_id required'], 400);

        $stmt = $db->prepare("SELECT * FROM routers WHERE id = :id AND type = 'mikrotik'");
        $stmt->execute([':id' => $routerId]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$router) jsonResponse(['error' => 'Router not found'], 404);

        $apiIP = !empty($router['wireguard_ip']) ? $router['wireguard_ip'] : $router['ip'];
        $apiPort = intval($router['port'] ?: 8729);

        try {
            $api = new MikroTikAPI($apiIP, $apiPort, 'jasiri-api', $router['password'] ?? '');
            $api->connect();

            $active = $api->getHotspotActiveUsers();
            $api->close();

            jsonResponse(['success' => true, 'active_users' => $active]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'API connection failed: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'dashboard_stats') {
        $tenantId = $_SESSION['account_id'] ?? null;
        $role = $_SESSION['role'] ?? null;

        if ($role === 'superadmin') {
            $routers = $db->query("SELECT * FROM routers WHERE type = 'mikrotik' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT * FROM routers WHERE type = 'mikrotik' AND tenant_id = :tid ORDER BY id DESC");
            $stmt->execute([':tid' => $tenantId]);
            $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $onlineDevices = 0;
        $totalDevices = count($routers);
        $deviceStats = [];

        foreach ($routers as $r) {
            $isOn = isRouterOnlineWg($r);
            if ($isOn) $onlineDevices++;
            $deviceStats[] = [
                'id' => $r['id'],
                'name' => $r['name'],
                'online' => $isOn,
                'location' => $r['location'] ?? '',
                'wireguard_ip' => $r['wireguard_ip'] ?? '',
            ];
        }

        $totalRevenue = $db->query("SELECT COALESCE(SUM(price), 0) FROM vouchers WHERE status = 'used'")->fetchColumn();
        $monthRevenue = $db->query("SELECT COALESCE(SUM(price), 0) FROM vouchers WHERE status = 'used' AND used_at >= date('now', 'start of month')")->fetchColumn();
        $todayRevenue = $db->query("SELECT COALESCE(SUM(price), 0) FROM vouchers WHERE status = 'used' AND date(used_at) = date('now')")->fetchColumn();

        $totalVouchers = $db->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
        $monthVouchers = $db->query("SELECT COUNT(*) FROM vouchers WHERE status = 'used' AND used_at >= date('now', 'start of month')")->fetchColumn();
        $activeVouchers = $db->query("SELECT COUNT(*) FROM vouchers WHERE status = 'active'")->fetchColumn();

        $revenueByMonth = $db->query("
            SELECT strftime('%Y-%m', used_at) as month, SUM(price) as revenue, COUNT(*) as count
            FROM vouchers WHERE status = 'used'
            GROUP BY strftime('%Y-%m', used_at)
            ORDER BY month DESC LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $totalDevices - $onlineDevices,
            'devices' => $deviceStats,
            'total_revenue' => floatval($totalRevenue),
            'month_revenue' => floatval($monthRevenue),
            'today_revenue' => floatval($todayRevenue),
            'total_vouchers' => $totalVouchers,
            'month_vouchers' => $monthVouchers,
            'active_vouchers' => $activeVouchers,
            'revenue_by_month' => $revenueByMonth,
        ]);
    }

    jsonResponse(['error' => 'Invalid request'], 400);
}
