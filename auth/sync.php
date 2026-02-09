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
        $cookie = createCookieFile(); // from config.php
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

            $stmt = $db->prepare("SELECT 1 FROM users WHERE mac = ? AND router_id = ?");
            $stmt->execute([$mac, $routerId]);

            if (!$stmt->fetch()) {
                // New device -> set internet_access = 0 (unpaid/throttle)
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
                // Update hostname/IP if changed
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
        $macFilterList = []; // optional, we keep empty because throttling replaces blocking

        foreach ($users as $u) {
            $mac = strtoupper($u['mac']);
            $hostname = $u['hostname'] ?: 'unknown';

            // Determine bandwidth limits
            if ((int)$u['internet_access'] === 1) {
                // Paid user -> 10 MB/s = 10240 KB/s
                $upLimit = 10240;
                $downLimit = 10240;
            } else {
                // Unpaid -> throttle to 1 KB/s
                $upLimit = 1;
                $downLimit = 1;
            }

            $onlineList[] = "$hostname\t$hostname\t$mac\t$upLimit\t$downLimit\ttrue";
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

        // Push empty MAC filter (optional)
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
            'throttled_devices' => count(array_filter($users, fn($x) => (int)$x['internet_access'] === 0)),
            'status'          => 'QoS throttling enforced'
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
