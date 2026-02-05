<?php
// /auth/billing.php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// -----------------------
// GET REQUEST PARAMS
// -----------------------
$router_id = $_GET['id'] ?? null;
$paid_mac  = $_GET['paid_mac'] ?? null;
$plan_id   = $_GET['plan_id'] ?? null;

if (!$router_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Router ID not specified.']);
    exit;
}

// -----------------------
// FETCH ROUTER CONFIG
// -----------------------
$routerData = getRouterConfig($router_id);
if (!$routerData) {
    http_response_code(404);
    echo json_encode(['error' => 'Router not found']);
    exit;
}

$ip       = $routerData['ip'];
$port     = $routerData['port'] ?: 80;
$router   = "http://$ip" . ($port != 80 ? ":$port" : "");
$password = $routerData['password'];

// -----------------------
// LOGIN TO ROUTER
// -----------------------
$cookie = createCookieFile();
curl_post("$router/login/Auth", ["password" => base64_encode($password)], $cookie);

// -----------------------
// CONNECT TO DATABASE
// -----------------------
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -----------------------
// UPDATE USER INTERNET ACCESS
// -----------------------
if ($paid_mac) {
    $paid_mac = strtolower($paid_mac);
    $stmt = $db->prepare("UPDATE users SET internet_access = 1, plan_id = :plan_id WHERE mac = :mac AND router_id = :router_id");
    $stmt->execute([
        ':mac'       => $paid_mac,
        ':router_id' => $router_id,
        ':plan_id'   => $plan_id ?? null
    ]);
}

// -----------------------
// FETCH CURRENT ONLINE & BLACKLIST
// -----------------------
$qos_json = curl_get(
    "$router/goform/getQos?random=" . microtime(true) . "&modules=onlineList,blackList",
    $cookie,
    "$router/index.html"
);

$qos    = json_decode($qos_json, true);
$online = $qos['onlineList'] ?? [];
$black  = $qos['blackList'] ?? [];

// -----------------------
// BUILD NEW BLOCKLIST (like Python version)
// -----------------------
$new_blacklist = [];
$all_macs = [];

foreach (array_merge($online, $black) as $dev) {
    $mac = strtolower($dev['qosListMac'] ?? '');
    if (!$mac || in_array($mac, $all_macs)) continue;
    $all_macs[] = $mac;

    // Check user in DB
    $stmt = $db->prepare("SELECT internet_access FROM users WHERE mac = ? AND router_id = ?");
    $stmt->execute([$mac, $router_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Insert unknown device
        $stmt = $db->prepare("
            INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
            ON CONFLICT(mac, router_id) DO UPDATE SET hostname=excluded.hostname, ip=excluded.ip
        ");
        $stmt->execute([
            $dev['qosListHostname'] ?? 'unknown',
            $dev['qosListIP'] ?? '',
            $mac,
            $router_id
        ]);
        $user = ['internet_access' => 0];
    }

    // Block if no internet access
    if ((int)$user['internet_access'] === 0) {
        $dev['qosListAccess']    = 'false';
        $dev['qosListUpLimit']   = '0';
        $dev['qosListDownLimit'] = '0';
        $new_blacklist[$mac] = $dev;
    }
}

// -----------------------
// REBUILD FULL QOS TABLE
// -----------------------
$qosList = "";

// Online devices
foreach ($online as $dev) {
    $mac = strtolower($dev['qosListMac'] ?? '');
    if (!$mac) continue;
    if (isset($new_blacklist[$mac])) $dev = $new_blacklist[$mac];

    $qosList .= sprintf(
        "%s\t%s\t%s\t%s\t%s\t%s\n",
        $dev['qosListHostname'] ?? 'unknown',
        $dev['qosListRemark'] ?? '',
        $dev['qosListMac'] ?? '',
        $dev['qosListUpLimit'] ?? '0',
        $dev['qosListDownLimit'] ?? '0',
        $dev['qosListAccess'] ?? 'true'
    );
}

// Offline blocked devices
foreach ($new_blacklist as $mac => $dev) {
    $found = false;
    foreach ($online as $o) {
        if (strtolower($o['qosListMac']) === $mac) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $qosList .= sprintf(
            "%s\t%s\t%s\t%s\t%s\t%s\n",
            $dev['qosListHostname'] ?? 'unknown',
            $dev['qosListRemark'] ?? '',
            $dev['qosListMac'] ?? '',
            $dev['qosListUpLimit'] ?? '0',
            $dev['qosListDownLimit'] ?? '0',
            $dev['qosListAccess'] ?? 'false'
        );
    }
}

// -----------------------
// PUSH NEW QOS TO ROUTER
// -----------------------
curl_post("$router/goform/setQos", ['module1' => 'qosList', 'qosList' => $qosList], $cookie, "$router/index.html");

// -----------------------
// RETURN RESPONSE
// -----------------------
echo json_encode([
    'router_id'     => $router_id,
    'blocked_count' => count($new_blacklist),
    'status'        => 'Billing updated successfully',
    'paid_mac'      => $paid_mac ?? null,
    'plan_id'       => $plan_id ?? null
]);

