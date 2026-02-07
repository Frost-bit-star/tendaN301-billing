<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// -----------------------
// DATABASE CONNECTION
// -----------------------
if (!file_exists(DB_PATH)) {
    die(json_encode(['error' => 'Database not found']));
}

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -----------------------
// CURL HELPERS
// -----------------------
function curl_post($url, $data = [], $cookieFile = '', $referer = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($referer) curl_setopt($ch, CURLOPT_REFERER, $referer);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('Curl POST error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function curl_get($url, $cookieFile = '', $referer = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($referer) curl_setopt($ch, CURLOPT_REFERER, $referer);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('Curl GET error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

if (!function_exists('createCookieFile')) {
    function createCookieFile() {
        return tempnam(sys_get_temp_dir(), 'cookie_');
    }
}

// -----------------------
// GET ALL ROUTERS
// -----------------------
$routers = $db->query("SELECT * FROM routers")->fetchAll(PDO::FETCH_ASSOC);
if (!$routers) die(json_encode(['error' => 'No routers found']));

$results = [];

foreach ($routers as $routerData) {
    try {
        $ip       = $routerData['ip'];
        $port     = $routerData['port'] ?: 80;
        $routerId = $routerData['id'];
        $password = $routerData['password'];
        $router   = "http://$ip" . ($port != 80 ? ":$port" : "");

        // -----------------------
        // LOGIN
        // -----------------------
        $cookie = createCookieFile();
        curl_post("$router/login/Auth", ["password" => base64_encode($password)], $cookie);

        // -----------------------
        // FETCH CURRENT ONLINE DEVICES
        // -----------------------
        $qos_json = curl_get("$router/goform/getQos?random=" . microtime(true) . "&modules=onlineList,macFilter", $cookie, "$router/index.html");
        $qos = json_decode($qos_json, true) ?: [];
        $online = $qos['onlineList'] ?? [];

        // -----------------------
        // SYNC NEW DEVICES TO DATABASE
        // -----------------------
        foreach ($online as $d) {
            if (empty($d['qosListMac'])) continue;
            $mac = strtoupper($d['qosListMac']);

            // Check if device exists
            $stmt = $db->prepare("SELECT 1 FROM users WHERE mac = ? AND router_id = ?");
            $stmt->execute([$mac, $routerId]);
            if (!$stmt->fetch()) {
                // New device -> blocked by default
                $stmtInsert = $db->prepare("
                    INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
                    VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
                ");
                $stmtInsert->execute([
                    $d['qosListHostname'] ?? 'unknown',
                    $d['qosListIP'] ?? '',
                    $mac,
                    $routerId
                ]);
            } else {
                // Existing device -> update hostname/IP if changed
                $stmtUpdate = $db->prepare("UPDATE users SET hostname = ?, ip = ? WHERE mac = ? AND router_id = ?");
                $stmtUpdate->execute([
                    $d['qosListHostname'] ?? 'unknown',
                    $d['qosListIP'] ?? '',
                    $mac,
                    $routerId
                ]);
            }
        }

        // -----------------------
        // FETCH FULL USERS LIST
        // -----------------------
        $stmt = $db->prepare("SELECT * FROM users WHERE router_id = ?");
        $stmt->execute([$routerId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $onlineList = [];
        $macFilterList = [];

        foreach ($users as $u) {
            $mac = strtoupper($u['mac']);
            $hostname = $u['hostname'] ?: 'unknown';
            $upLimit = '0';
            $downLimit = '0';
            $blocked = ((int)$u['internet_access'] === 0); // blocked if internet_access = 0
            $access = $blocked ? 'false' : 'true';

            // Online list entry
            $onlineList[] = "$hostname\t$hostname\t$mac\t$upLimit\t$downLimit\t$access";

            // Blocked devices go into MAC filter
            if ($blocked) {
                $macFilterList[] = "$hostname\t$hostname\t$mac\t$upLimit\t$downLimit\tfalse";
            }
        }

        $onlineListStr = implode("\n", $onlineList);
        $macFilterStr = implode("\n", $macFilterList);

        // -----------------------
        // PUSH TO ROUTER
        // -----------------------
        curl_post("$router/goform/setQos", [
            'module1'       => 'onlineList',
            'onlineList'    => $onlineListStr,
            'onlineListLen' => count($onlineList),
            'qosEn'         => '1',
            'qosAccessEn'   => '1'
        ], $cookie, "$router/index.html");

        curl_post("$router/goform/setMacFilter", [
            'macFilterEn'      => count($macFilterList) > 0 ? '1' : '0',
            'macFilterMode'    => 'deny',
            'macFilterList'    => $macFilterStr,
            'macFilterListLen' => count($macFilterList)
        ], $cookie, "$router/index.html");

        curl_post("$router/goform/save", ['random' => time()], $cookie, "$router/index.html");

        $results[] = [
            'router_id'       => $routerId,
            'ip'              => $ip,
            'total_devices'   => count($users),
            'blocked_devices' => count($macFilterList),
            'status'          => 'Full blocklist enforced'
        ];

    } catch (Exception $e) {
        $results[] = [
            'router_id' => $routerData['id'],
            'ip'        => $routerData['ip'],
            'error'     => $e->getMessage()
        ];
    }
}

// -----------------------
// FINAL RESPONSE
// -----------------------
echo json_encode($results, JSON_PRETTY_PRINT);
