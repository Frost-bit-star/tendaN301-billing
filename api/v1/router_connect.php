<?php
// api/v1/router_connect.php - App connects to Tenda N301 directly, fetches full router state
// GET /api/v1/router_connect.php?token=TOKEN&router_id=1
//
// Returns: whitelist, online devices, blacklisted devices, filter mode
// The app talks to the router directly (local network) — same as the website does.

require_once __DIR__ . '/helpers.php';

$accountId = getAccountId();
$db = db();
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

$ip        = $router['ip'];
$port      = $router['port'] ?: 80;
$password  = $router['password'];
$routerUrl = "http://$ip" . ($port != 80 ? ":$port" : "");

// --- CURL helpers (same as whitelist.php) ---
function rc_curl_post($url, $data, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function rc_curl_get($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('cURL error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function rc_normalize_mac($mac) {
    $mac = strtoupper(trim($mac));
    $mac = str_replace('-', ':', $mac);
    return $mac;
}

$cookie = tempnam(sys_get_temp_dir(), 'rc_');

try {
    // Login to router
    rc_curl_post("$routerUrl/login/Auth", ["password" => base64_encode($password)], $cookie);

    // 1. Fetch whitelist (MAC filter)
    $natJson  = rc_curl_get("$routerUrl/goform/getNAT?modules=macFilter&random=" . microtime(true), $cookie);
    $natData  = json_decode($natJson, true);
    $macFilterRaw = $natData['macFilter']['macFilterList'] ?? [];
    $filterMode   = $natData['macFilter']['curFilterMode'] ?? 'pass';

    $whitelist = [];
    if (is_array($macFilterRaw)) {
        foreach ($macFilterRaw as $dev) {
            if (($dev['filterMode'] ?? '') !== 'pass') continue;
            $mac  = rc_normalize_mac($dev['mac'] ?? '');
            $host = preg_replace('/[^\w\.\-]/', '', $dev['hostname'] ?? $dev['remark'] ?? 'device');
            if ($mac) $whitelist[$mac] = $host;
        }
    }

    // 2. Fetch online devices + blacklisted
    $qosJson = rc_curl_get("$routerUrl/goform/getQos?random=" . microtime(true) . "&modules=onlineList,blackList", $cookie);
    $qosData = json_decode($qosJson, true);

    $online = [];
    foreach (($qosData['onlineList'] ?? []) as $dev) {
        $mac = rc_normalize_mac($dev['qosListMac'] ?? '');
        if (!$mac) continue;
        $online[] = [
            'mac'       => $mac,
            'hostname'  => preg_replace('/[^\w\.\-]/', '', $dev['qosListHostname'] ?? 'device'),
            'ip'        => $dev['qosListIP'] ?? '',
            'type'      => $dev['qosListConnectType'] ?? 'wifi',
            'upload'    => intval($dev['qosListUpLimit'] ?? 0),
            'download'  => intval($dev['qosListDownLimit'] ?? 0),
            'access'    => ($dev['qosListAccess'] ?? 'true') === 'true'
        ];
    }

    $blacklisted = [];
    foreach (($qosData['blackList'] ?? []) as $dev) {
        $mac = rc_normalize_mac($dev['qosListMac'] ?? '');
        if (!$mac) continue;
        $blacklisted[] = [
            'mac'      => $mac,
            'hostname' => preg_replace('/[^\w\.\-]/', '', $dev['qosListHostname'] ?? 'device'),
            'ip'       => $dev['qosListIP'] ?? '',
            'type'     => $dev['qosListConnectType'] ?? 'wifi'
        ];
    }

    respond([
        'success'    => true,
        'router_id'  => $routerId,
        'router_ip'  => $ip,
        'filter_mode'=> $filterMode,
        'whitelist'  => $whitelist,
        'online'     => $online,
        'blacklisted'=> $blacklisted,
        'fetched_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    respond(['success' => false, 'error' => 'Router connection failed: ' . $e->getMessage()], 500);
} finally {
    if (file_exists($cookie)) @unlink($cookie);
}
