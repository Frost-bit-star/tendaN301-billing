<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* --------------------- HELPERS --------------------- */

function respond($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function curl_post($url, $data, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception(curl_error($ch));
    curl_close($ch);
    return $res;
}

function curl_get($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception(curl_error($ch));
    curl_close($ch);
    return $res;
}

function create_cookie() {
    return tempnam(sys_get_temp_dir(), 'rt_');
}

function isRouterOnline($ip, $port=80, $timeout=2) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) { fclose($fp); return true; }
    return false;
}

function normalize_mac($mac) {
    return strtoupper(str_replace('-', ':', trim($mac)));
}

/* --------------------- INPUT --------------------- */

$input    = json_decode(file_get_contents("php://input"), true);
$action   = $input['action'] ?? null;
$routerId = $input['router_id'] ?? null;
$newDevice= $input['device'] ?? null;
$newMode  = $input['mode'] ?? null;

if (!$input) respond(['success'=>false,'error'=>'Invalid JSON body'],400);
if (!file_exists(DB_PATH)) respond(['success'=>false,'error'=>'Database not found'],500);

/* --------------------- LOAD ROUTERS --------------------- */

$db = new PDO("sqlite:" . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($routerId) {
    $stmt = $db->prepare("SELECT * FROM routers WHERE id=?");
    $stmt->execute([$routerId]);
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $routers = $db->query("SELECT * FROM routers")->fetchAll(PDO::FETCH_ASSOC);
}

if (!$routers) respond(['success'=>false,'error'=>'No routers found'],404);

$results = [];

/* --------------------- MAIN LOOP --------------------- */

foreach ($routers as $r) {

    $routerId  = $r['id'];
    $routerUrl = "http://{$r['ip']}" . ($r['port'] ? ":{$r['port']}" : "");
    $cookie = create_cookie();

    try {

        if (!isRouterOnline($r['ip'], $r['port'] ?: 80)) {
            $results[] = ['router_id'=>$routerId,'status'=>'offline'];
            continue;
        }

        /* LOGIN */
        curl_post("$routerUrl/login/Auth", [
            "password" => base64_encode($r['password'])
        ], $cookie);

        /* FETCH MAC FILTER (WHITELIST) */
        $natJson = curl_get(
            "$routerUrl/goform/getNAT?modules=macFilter&random=" . microtime(true),
            $cookie
        );

        $natData = json_decode($natJson, true);
        $macFilterRaw = $natData['macFilter']['macFilterList'] ?? [];
        $currentMode  = $natData['macFilter']['curFilterMode'] ?? 'pass';

        $whitelist = [];

        if (is_array($macFilterRaw)) {
            foreach ($macFilterRaw as $dev) {
                if (($dev['filterMode'] ?? '') !== 'pass') continue;

                $mac = normalize_mac($dev['mac'] ?? '');
                if (!$mac) continue;

                $host = preg_replace('/[^\w\.\-]/','',
                    $dev['hostname'] ?? $dev['remark'] ?? 'device'
                );

                $whitelist[$mac] = $host;
            }
        }

        /* FETCH ONLINE + BLACKLIST (QOS) */
        $qosJson = curl_get(
            "$routerUrl/goform/getQos?random=" . microtime(true) . "&modules=onlineList,blackList",
            $cookie
        );

        $qosData = json_decode($qosJson, true);

        $onlineClients = [];
        $blackClients  = [];

        if (!empty($qosData['onlineList'])) {
            foreach ($qosData['onlineList'] as $dev) {
                $mac = normalize_mac($dev["qosListMac"] ?? '');
                if (!$mac) continue;

                $onlineClients[$mac] = [
                    'hostname' => $dev["qosListHostname"] ?? 'unknown',
                    'ip'       => $dev["qosListIP"] ?? '',
                    'type'     => $dev["qosListConnectType"] ?? ''
                ];
            }
        }

        if (!empty($qosData['blackList'])) {
            foreach ($qosData['blackList'] as $dev) {
                $mac = normalize_mac($dev["qosListMac"] ?? '');
                if (!$mac) continue;

                $blackClients[$mac] = [
                    'hostname' => $dev["qosListHostname"] ?? 'unknown',
                    'ip'       => $dev["qosListIP"] ?? '',
                    'type'     => $dev["qosListConnectType"] ?? ''
                ];
            }
        }

        /* ---------------- ACTIONS ---------------- */

        switch ($action) {

            case 'get_users':
                $results[] = [
                    'router_id'      => $routerId,
                    'status'         => 'online',
                    'filter_mode'    => $currentMode,
                    'whitelist'      => $whitelist,
                    'online_clients' => $onlineClients,
                    'blacklist'      => $blackClients
                ];
                break;

            case 'toggle_mode':

                if (!in_array($newMode, ['pass','deny'])) {
                    throw new Exception("Mode must be 'pass' or 'deny'");
                }

                curl_post("$routerUrl/goform/setNAT", [
                    'module6'    => 'macFilter',
                    'filterMode' => $newMode
                ], $cookie);

                curl_post("$routerUrl/goform/save", ['random'=>time()], $cookie);

                $results[] = [
                    'router_id' => $routerId,
                    'status'    => 'mode_updated',
                    'new_mode'  => $newMode
                ];
                break;

            case 'add_device':

                if (!$newDevice || empty($newDevice['mac']) || empty($newDevice['hostname'])) {
                    throw new Exception("Device MAC and hostname required");
                }

                $mac  = normalize_mac($newDevice['mac']);
                $host = preg_replace('/[^\w\.\-]/','',$newDevice['hostname']);

                $whitelist[$mac] = $host;

                $macLines = [];
                foreach ($whitelist as $m => $h) {
                    $macLines[] = "$h\t$h\t$m";
                }

                curl_post("$routerUrl/goform/setNAT", [
                    'module6'       => 'macFilter',
                    'filterMode'    => 'pass',
                    'macFilterList' => implode("\n", $macLines)
                ], $cookie);

                curl_post("$routerUrl/goform/save", ['random'=>time()], $cookie);

                $results[] = [
                    'router_id'       => $routerId,
                    'status'          => 'device_added',
                    'whitelist_count' => count($whitelist)
                ];
                break;

            case 'get_routers':
                $results[] = [
                    'router_id'   => $routerId,
                    'ip'          => $r['ip'],
                    'port'        => $r['port'] ?: 80,
                    'status'      => 'online',
                    'filter_mode' => $currentMode
                ];
                break;

            default:
                throw new Exception("Unknown action");
        }

    } catch (Exception $e) {
        $results[] = [
            'router_id'=>$routerId,
            'status'=>'failed',
            'error'=>$e->getMessage()
        ];
    }

    if (file_exists($cookie)) unlink($cookie);
}

/* ---------------- RESPONSE ---------------- */

respond([
    'success'=>true,
    'routers_processed'=>count($results),
    'results'=>$results
]);
