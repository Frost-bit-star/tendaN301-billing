<?php
require_once __DIR__ . '/config.php';

set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: application/json');

// -----------------------
// CONFIG
// -----------------------
$ROUTER_COOLDOWN = 120; // seconds (SAFE for 4MB RAM)
$WORKER_SLEEP    = 30;  // PM2 heartbeat

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
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($referer) curl_setopt($ch, CURLOPT_REFERER, $referer);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new Exception('Curl POST error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

function curl_get($url, $cookieFile = '', $referer = '') {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($referer) curl_setopt($ch, CURLOPT_REFERER, $referer);
    $res = curl_exec($ch);
    if ($res === false) {
        throw new Exception('Curl GET error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

// -----------------------
// WORKER LOOP (PM2 MANAGED)
// -----------------------
while (true) {

    $results = [];

    $routers = $db->query("SELECT * FROM routers")->fetchAll(PDO::FETCH_ASSOC);
    if (!$routers) {
        sleep($WORKER_SLEEP);
        continue;
    }

    foreach ($routers as $routerData) {
        try {
            $routerId = $routerData['id'];
            $ip       = $routerData['ip'];
            $port     = $routerData['port'] ?: 80;
            $password = $routerData['password'];
            $router   = "http://$ip" . ($port != 80 ? ":$port" : "");

            // -----------------------
            // COOLDOWN CHECK
            // -----------------------
            $lastRun = strtotime($routerData['last_run'] ?? '1970-01-01');
            if (time() - $lastRun < $ROUTER_COOLDOWN) {
                continue;
            }

            // -----------------------
            // LOGIN (PER ROUTER COOKIE)
            // -----------------------
            $cookie = createCookieFile();
            curl_post(
                "$router/login/Auth",
                ['password' => base64_encode($password)],
                $cookie
            );

            // -----------------------
            // FETCH ONLINE DEVICES
            // -----------------------
            $qos_json = curl_get(
                "$router/goform/getQos?random=" . microtime(true) . "&modules=onlineList",
                $cookie,
                "$router/index.html"
            );

            $qos    = json_decode($qos_json, true) ?: [];
            $online = $qos['onlineList'] ?? [];

            // -----------------------
            // SYNC DEVICES
            // -----------------------
            foreach ($online as $d) {
                if (empty($d['qosListMac'])) continue;

                $mac      = strtoupper($d['qosListMac']);
                $hostname = $d['qosListHostname'] ?? 'unknown';
                $ipAddr   = $d['qosListIP'] ?? '';

                $stmt = $db->prepare("
                    SELECT internet_access
                    FROM users
                    WHERE mac = ? AND router_id = ?
                ");
                $stmt->execute([$mac, $routerId]);

                if (!$stmt->fetch()) {
                    $db->prepare("
                        INSERT INTO users
                        (hostname, ip, mac, router_id, internet_access, connected_at)
                        VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
                    ")->execute([$hostname, $ipAddr, $mac, $routerId]);
                } else {
                    $db->prepare("
                        UPDATE users
                        SET hostname = ?, ip = ?
                        WHERE mac = ? AND router_id = ?
                    ")->execute([$hostname, $ipAddr, $mac, $routerId]);
                }
            }

            // -----------------------
            // BUILD QOS LIST
            // -----------------------
            $stmt = $db->prepare("SELECT * FROM users WHERE router_id = ?");
            $stmt->execute([$routerId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $onlineList = [];

            foreach ($users as $u) {
                $mac = strtoupper($u['mac']);
                $hostname = $u['hostname'] ?: 'unknown';

                if ((int)$u['internet_access'] === 1) {
                    $up = 10240;
                    $down = 10240;
                } else {
                    $up = 1;
                    $down = 1;
                }

                $onlineList[] = "$hostname\t$hostname\t$mac\t$up\t$down\ttrue";
            }

            $onlineListStr = implode("\n", $onlineList);
            $payloadHash  = sha1($onlineListStr);

            // -----------------------
            // CHANGE DETECTION
            // -----------------------
            if ($payloadHash === ($routerData['last_qos_hash'] ?? null)) {
                continue;
            }

            // -----------------------
            // PUSH + SAVE (REQUIRED)
            // -----------------------
            curl_post("$router/goform/setQos", [
                'module1'       => 'onlineList',
                'onlineList'    => $onlineListStr,
                'onlineListLen' => count($onlineList),
                'qosEn'         => '1',
                'qosAccessEn'   => '1'
            ], $cookie, "$router/index.html");

            curl_post("$router/goform/save", [
                'random' => time()
            ], $cookie, "$router/index.html");

            // -----------------------
            // UPDATE ROUTER STATE
            // -----------------------
            $db->prepare("
                UPDATE routers
                SET last_run = CURRENT_TIMESTAMP,
                    last_qos_hash = ?
                WHERE id = ?
            ")->execute([$payloadHash, $routerId]);

            $results[] = [
                'router_id' => $routerId,
                'ip'        => $ip,
                'devices'   => count($users),
                'status'    => 'updated'
            ];

        } catch (Throwable $e) {
            error_log('[QoS] ' . $e->getMessage());
        }
    }

    sleep($WORKER_SLEEP);
}
