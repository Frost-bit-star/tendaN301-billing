<?php
// api/v1/whitelist.php - Add/remove devices from router whitelist
require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();
$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();

$routerId = intval($input['router_id'] ?? $_GET['router_id'] ?? 0);
if (!$routerId) {
    respond(['success' => false, 'error' => 'router_id required'], 400);
}

// Verify ownership
$stmt = $db->prepare("SELECT * FROM routers WHERE id = ? AND tenant_id = ?");
$stmt->execute([$routerId, $accountId]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    respond(['success' => false, 'error' => 'Router not found or access denied'], 404);
}

$ip       = $router['ip'];
$port     = $router['port'] ?: 80;
$password = $router['password'];
$routerUrl = "http://$ip" . ($port != 80 ? ":$port" : "");

// --- CURL helpers ---
function wl_curl_post($url, $data, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function wl_curl_get($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

$cookie = tempnam(sys_get_temp_dir(), 'wl_');

try {
    // Login to router
    wl_curl_post("$routerUrl/login/Auth", ["password" => base64_encode($password)], $cookie);

    // Fetch current whitelist
    $natJson = wl_curl_get("$routerUrl/goform/getNAT?modules=macFilter&random=" . microtime(true), $cookie);
    $natData = json_decode($natJson, true);
    $macFilterRaw = $natData['macFilter']['macFilterList'] ?? [];
    $currentMode  = $natData['macFilter']['curFilterMode'] ?? 'pass';

    // Build current whitelist
    $whitelist = [];
    if (is_array($macFilterRaw)) {
        foreach ($macFilterRaw as $dev) {
            if (($dev['filterMode'] ?? '') !== 'pass') continue;
            $mac = strtoupper(trim($dev['mac'] ?? ''));
            if (!$mac) continue;
            $host = preg_replace('/[^\w\.\-]/', '', $dev['hostname'] ?? $dev['remark'] ?? 'device');
            $whitelist[$mac] = $host;
        }
    }

    if ($method === 'GET') {
        // List devices in whitelist
        $devices = [];
        foreach ($whitelist as $mac => $host) {
            $devices[] = ['mac' => $mac, 'hostname' => $host];
        }
        respond(['success' => true, 'whitelist' => $devices, 'filter_mode' => $currentMode]);
        exit;
    }

    if ($method === 'POST') {
        // Add device to whitelist
        $mac  = strtoupper(trim($input['mac'] ?? ''));
        $host = preg_replace('/[^\w\.\-]/', '', $input['hostname'] ?? 'device');

        if (!$mac) {
            respond(['success' => false, 'error' => 'MAC address required'], 400);
        }

        $whitelist[$mac] = $host;

        $macLines = [];
        foreach ($whitelist as $m => $h) {
            $macLines[] = "$h\t$h\t$m";
        }

        wl_curl_post("$routerUrl/goform/setNAT", [
            'module6'       => 'macFilter',
            'filterMode'    => 'pass',
            'macFilterList' => implode("\n", $macLines)
        ], $cookie);

        wl_curl_post("$routerUrl/goform/save", ['random' => time()], $cookie);

        // Also store in billing DB if plan info provided
        $planId = intval($input['plan_id'] ?? 0);
        $phone  = trim($input['phone_number'] ?? '');

        if ($planId) {
            $planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

            if ($plan) {
                $totalSeconds = ($plan['days'] ?? 0) * 86400
                              + ($plan['hours'] ?? 0) * 3600
                              + ($plan['minutes'] ?? 0) * 60;
                $endAt = date('Y-m-d H:i:s', time() + $totalSeconds);
                $name  = trim($input['name'] ?? $host);

                $db->prepare("
                    INSERT INTO billing (router_id, mac, plan_id, name, phone_number, remaining_time, end_at, internet_access)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ON CONFLICT(router_id, mac) DO UPDATE SET
                        plan_id=excluded.plan_id,
                        name=excluded.name,
                        phone_number=excluded.phone_number,
                        remaining_time=excluded.remaining_time,
                        end_at=excluded.end_at,
                        internet_access=1
                ")->execute([$routerId, $mac, $planId, $name, $phone, $totalSeconds, $endAt]);

                // Also ensure user exists in users table
                $db->prepare("
                    INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
                    VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
                    ON CONFLICT(mac, router_id) DO UPDATE SET internet_access = 1
                ")->execute([$name, '', $mac, $routerId]);
            }
        }

        respond(['success' => true, 'message' => "Device $mac added to whitelist", 'whitelist_count' => count($whitelist)]);
        exit;
    }

    if ($method === 'DELETE') {
        // Remove device from whitelist
        $mac = strtoupper(trim($input['mac'] ?? ''));
        if (!$mac || !isset($whitelist[$mac])) {
            respond(['success' => false, 'error' => 'MAC not found in whitelist'], 404);
        }

        unset($whitelist[$mac]);

        $macLines = [];
        foreach ($whitelist as $m => $h) {
            $macLines[] = "$h\t$h\t$m";
        }

        wl_curl_post("$routerUrl/goform/setNAT", [
            'module6'       => 'macFilter',
            'filterMode'    => 'pass',
            'macFilterList' => implode("\n", $macLines)
        ], $cookie);

        wl_curl_post("$routerUrl/goform/save", ['random' => time()], $cookie);

        respond(['success' => true, 'message' => "Device $mac removed from whitelist"]);
        exit;
    }

    respond(['success' => false, 'error' => 'Method not allowed. Use GET, POST, DELETE'], 405);

} catch (Exception $e) {
    respond(['success' => false, 'error' => $e->getMessage()], 500);
} finally {
    if (file_exists($cookie)) @unlink($cookie);
}
