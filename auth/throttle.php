<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// --------------------- HELPERS ---------------------
function curl_get($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('CURL GET error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function curl_post($url, $data, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('CURL POST error: ' . curl_error($ch));
    curl_close($ch);
    return $res;
}

function create_cookie() {
    return tempnam(sys_get_temp_dir(), 'rt_');
}

function normalize_mac($mac) {
    return strtoupper(str_replace('-', ':', trim($mac)));
}

// --------------------- INPUT ---------------------
$action    = $_GET['action'] ?? 'get_users';       // 'get_users' or 'set_throttle'
$targetMac = $_GET['mac'] ?? null;                 // MAC address to throttle
$upLimit   = isset($_GET['up']) ? (int)$_GET['up'] : null;   // in kbps
$downLimit = isset($_GET['down']) ? (int)$_GET['down'] : null; // in kbps

// --------------------- DATABASE ---------------------
$db = new PDO("sqlite:" . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --------------------- SET THROTTLE (ROUTER-SPECIFIC) ---------------------
if ($action === 'set_throttle') {
    $routerId = $_GET['router_id'] ?? null;

    if (!$routerId || !$targetMac) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'router_id and mac are required']);
        exit;
    }

    $router = $db->query("SELECT * FROM routers WHERE id=".(int)$routerId)->fetch(PDO::FETCH_ASSOC);
    if (!$router) {
        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>'Router not found']);
        exit;
    }

    $routerUrl = "http://{$router['ip']}" . (!empty($router['port']) ? ":{$router['port']}" : '');
    $cookie = create_cookie();

    try {
        // LOGIN
        curl_post("$routerUrl/login/Auth", ["password"=>base64_encode($router['password'])], $cookie);

        // FETCH USERS
        $qosJson = curl_get("$routerUrl/goform/getQos?random=".microtime(true)."&modules=onlineList,blackList", $cookie);
        $qosData = json_decode($qosJson, true);

        $users = [];
        foreach (['onlineList','blackList'] as $list) {
            if (!empty($qosData[$list])) {
                foreach ($qosData[$list] as $dev) {
                    $mac = normalize_mac($dev['qosListMac'] ?? '');
                    if (!$mac) continue;
                    $interface = $dev['qosListConnectType'] ?? 'unknown';
                    $users[$mac] = [
                        'mac'=>$mac,
                        'hostname'=>$dev['qosListHostname'] ?? 'unknown',
                        'upLimit'=>$dev['qosListUpLimit'] ?? 0,
                        'downLimit'=>$dev['qosListDownLimit'] ?? 0,
                        'internet_access'=>$list==='onlineList'
                    ];
                }
            }
        }

        if (!isset($users[$targetMac])) {
            throw new Exception("Device $targetMac not found on router $routerId");
        }

        // APPLY THROTTLE
        $onlineList = [];
        foreach ($users as $mac=>$u) {
            $up = ($mac===$targetMac && $upLimit!==null)?$upLimit:$u['upLimit'];
            $down = ($mac===$targetMac && $downLimit!==null)?$downLimit:$u['downLimit'];
            $access = $u['internet_access']?'true':'false';
            $onlineList[] = "{$u['hostname']}\t{$u['hostname']}\t{$mac}\t{$up}\t{$down}\t{$access}";
        }

        curl_post("$routerUrl/goform/setQos", [
            'module1'=>'onlineList',
            'onlineList'=>implode("\n",$onlineList),
            'onlineListLen'=>count($onlineList),
            'qosEn'=>'1',
            'qosAccessEn'=>'1'
        ], $cookie);

        curl_post("$routerUrl/goform/save", ['random'=>time()], $cookie);

        echo json_encode(['success'=>true, 'message'=>"Throttle applied to $targetMac"]);

    } catch(Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    } finally {
        if(file_exists($cookie)) unlink($cookie);
    }

    exit;
}

// --------------------- GET USERS (ALL ROUTERS) ---------------------
$routers = $db->query("SELECT * FROM routers")->fetchAll(PDO::FETCH_ASSOC);
$allRouters = [];

foreach ($routers as $router) {
    $routerId  = $router['id'];
    $routerUrl = "http://{$router['ip']}" . (!empty($router['port']) ? ":{$router['port']}" : '');
    $cookie    = create_cookie();

    try {
        curl_post("$routerUrl/login/Auth", ["password" => base64_encode($router['password'])], $cookie);

        $qosJson = curl_get("$routerUrl/goform/getQos?random=" . microtime(true) . "&modules=onlineList,blackList", $cookie);
        $qosData = json_decode($qosJson, true);

        $users = [];
        foreach (['onlineList', 'blackList'] as $list) {
            if (!empty($qosData[$list])) {
                foreach ($qosData[$list] as $dev) {
                    $mac = normalize_mac($dev['qosListMac'] ?? '');
                    if (!$mac) continue;
                    $interface = $dev['qosListConnectType'] ?? 'unknown';
                    $users[$mac] = [
                        'mac' => $mac,
                        'ip' => $dev['qosListIP'] ?? '',
                        'hostname' => $dev['qosListHostname'] ?? 'unknown',
                        'internet_access' => $list === 'onlineList',
                        'interface' => $interface,
                        'upLimit' => $dev['qosListUpLimit'] ?? 0,
                        'downLimit' => $dev['qosListDownLimit'] ?? 0,
                        'last_seen' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        $allRouters[] = [
            'router_id' => $routerId,
            'name'      => $router['name'] ?? "Router $routerId",
            'ip'        => $router['ip'],
            'port'      => $router['port'] ?: 80,
            'status'    => 'online',
            'users'     => array_values($users)
        ];

    } catch (Exception $e) {
        $allRouters[] = [
            'router_id' => $routerId,
            'name'      => $router['name'] ?? "Router $routerId",
            'ip'        => $router['ip'],
            'port'      => $router['port'] ?: 80,
            'status'    => 'offline',
            'error'     => $e->getMessage(),
            'users'     => []
        ];
    } finally {
        if (file_exists($cookie)) unlink($cookie);
    }
}

// --------------------- OUTPUT ---------------------
echo json_encode([
    'total_routers' => count($allRouters),
    'routers' => $allRouters
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
