<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// -----------------------
// PARAMS
// -----------------------
$router_id = $_GET['id'] ?? null;
$paid_mac  = $_GET['paid_mac'] ?? null;
$plan_id   = $_GET['plan_id'] ?? null;

if (!$router_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Router ID missing']);
    exit;
}

// -----------------------
// ROUTER CONFIG
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
// LOGIN
// -----------------------
$cookie = createCookieFile();
curl_post("$router/login/Auth", [
    "password" => base64_encode($password)
], $cookie);

// -----------------------
// DATABASE
// -----------------------
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -----------------------
// MARK PAID USER
// -----------------------
if ($paid_mac) {
    $paid_mac = strtolower($paid_mac);
    $stmt = $db->prepare("
        UPDATE users
        SET internet_access = 1, plan_id = ?
        WHERE mac = ? AND router_id = ?
    ");
    $stmt->execute([$plan_id, $paid_mac, $router_id]);
}

// -----------------------
// FETCH ALL DEVICES
// -----------------------
$qos_json = curl_get(
    "$router/goform/getQos?random=" . microtime(true) . "&modules=onlineList,blackList",
    $cookie,
    "$router/index.html"
);

$qos    = json_decode($qos_json, true) ?: [];
$online = $qos['onlineList'] ?? [];
$black  = $qos['blackList'] ?? [];

// Merge unique MACs
$devices = [];
foreach (array_merge($online, $black) as $d) {
    if (!empty($d['qosListMac'])) {
        $mac = strtolower($d['qosListMac']);
        $devices[$mac] = $d;
    }
}

// -----------------------
// SYNC ALL DEVICES TO DB
// -----------------------
foreach ($devices as $mac => $dev) {
    $stmt = $db->prepare("
        INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
        VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
        ON CONFLICT(mac, router_id)
        DO UPDATE SET hostname=excluded.hostname, ip=excluded.ip
    ");
    $stmt->execute([
        $dev['qosListHostname'] ?? 'unknown',
        $dev['qosListIP'] ?? '',
        $mac,
        $router_id
    ]);
}

// -----------------------
// BUILD QOS + MAC BLOCKLIST
// -----------------------
$qosList      = "";
$macBlockList = [];

$stmt = $db->prepare("
    SELECT mac, internet_access
    FROM users
    WHERE router_id = ?
");
$stmt->execute([$router_id]);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $u) {
    $mac = strtolower($u['mac']);
    if (!isset($devices[$mac])) continue;

    $blocked = ((int)$u['internet_access'] === 0);

    // QoS rule (1kbps = unusable)
    $qosList .= sprintf(
        "%s\t\t%s\t%s\t%s\t%s\n",
        $devices[$mac]['qosListHostname'] ?? 'unknown',
        strtoupper($mac),
        $blocked ? '1' : '0',
        $blocked ? '1' : '0',
        $blocked ? 'false' : 'true'
    );

    // MAC FILTER (REAL BLOCK)
    if ($blocked) {
        $macBlockList[] = strtoupper($mac);
    }
}

// -----------------------
// PUSH QOS
// -----------------------
$qosLen = substr_count(trim($qosList), "\n") + 1;

curl_post("$router/goform/setQos", [
    'module1'     => 'qosList',
    'qosList'     => $qosList,
    'qosListLen'  => $qosLen,
    'qosEn'       => '1',
    'qosAccessEn' => '1'
], $cookie, "$router/index.html");

// -----------------------
// APPLY MAC FILTER (BLOCK MODE)
// -----------------------
if (!empty($macBlockList)) {

    curl_post("$router/goform/setMacFilter", [
        'macFilterEn'  => '1',
        'macFilterMode'=> 'deny',
        'macList'      => implode(';', $macBlockList),
        'macListLen'   => count($macBlockList)
    ], $cookie, "$router/index.html");
}

// -----------------------
// FORCE SAVE (NVRAM COMMIT)
// -----------------------
curl_post("$router/goform/save", [
    'random' => time()
], $cookie, "$router/index.html");

// -----------------------
// RESPONSE
// -----------------------
echo json_encode([
    'router_id'       => $router_id,
    'total_devices'  => count($devices),
    'blocked_devices'=> count($macBlockList),
    'status'         => 'QoS + MAC filter enforced (persistent)',
    'paid_mac'       => $paid_mac,
    'plan_id'        => $plan_id
]);
