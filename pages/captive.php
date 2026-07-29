<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db/schema.php';
require_once __DIR__ . '/../api/mikrotik_api.php';

$routerDeviceId = $_GET['router'] ?? '';
$clientMAC = $_GET['mac'] ?? $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$redirURL = $_GET['url'] ?? 'https://jasiri.stackverify.site/success';
$error = '';
$success = '';
$autoLogin = false;
$showPhoneForm = false;

if (empty($clientMAC) && isset($_SERVER['HTTP_CLIENT_MAC'])) {
    $clientMAC = $_SERVER['HTTP_CLIENT_MAC'];
}

$stmt = $db->prepare("SELECT * FROM routers WHERE device_id = :did AND type = 'mikrotik'");
$stmt->execute([':did' => $routerDeviceId]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    $stmt = $db->query("SELECT * FROM routers WHERE type = 'mikrotik' LIMIT 1");
    $router = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $router) {
    $voucherCode = preg_replace('/\s+/', '', trim($_POST['voucher_code'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $mac = trim($_POST['mac'] ?? $clientMAC);
    $step = $_POST['step'] ?? '1';

    if ($step === '1') {
        if (empty($voucherCode)) {
            $error = 'Please enter a voucher code';
        } else {
            $stmt = $db->prepare("SELECT v.*, p.name as plan_name, p.days, p.hours, p.minutes FROM vouchers v LEFT JOIN plans p ON v.plan_id = p.id WHERE v.code = :code");
            $stmt->execute([':code' => $voucherCode]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$voucher) {
                $error = 'Invalid voucher code';
            } elseif ($voucher['status'] !== 'active') {
                $error = 'Voucher already used or expired';
            } elseif ($voucher['expires_at'] && strtotime($voucher['expires_at']) < time()) {
                $error = 'Voucher has expired';
            } else {
                $_SESSION['pending_voucher'] = $voucher;
                $_SESSION['pending_mac'] = $mac;
                $showPhoneForm = true;
                $voucherCode = $voucher['code'];
            }
        }
    } elseif ($step === '2') {
        $voucher = $_SESSION['pending_voucher'] ?? null;
        $mac = $_SESSION['pending_mac'] ?? $mac;

        if (!$voucher) {
            $error = 'Session expired, please enter your voucher code again';
            unset($_SESSION['pending_voucher'], $_SESSION['pending_mac']);
        } elseif (empty($phone)) {
            $error = 'Please enter your mobile number';
            $_SESSION['pending_mac'] = $mac;
            $showPhoneForm = true;
            $voucherCode = $voucher['code'];
        } else {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            $duration = ($voucher['days'] ?? 0) * 86400 + ($voucher['hours'] ?? 0) * 3600 + ($voucher['minutes'] ?? 0) * 60;
            if ($duration <= 0) $duration = 3600;
            $hours = floor($duration / 3600);
            $mins = floor(($duration % 3600) / 60);
            $uptime = "${hours}h${mins}m0s";

            $stmt = $db->prepare("UPDATE vouchers SET status = 'used', used_at = :ts, used_mac = :mac, router_id = :rid, phone = :phone WHERE id = :id");
            $stmt->execute([
                ':ts' => date('Y-m-d H:i:s'),
                ':mac' => $mac,
                ':rid' => $router['id'],
                ':phone' => $phone,
                ':id' => $voucher['id'],
            ]);

            try {
                $apiIP = !empty($router['wireguard_ip']) ? $router['wireguard_ip'] : $router['ip'];
                $apiPort = intval($router['port'] ?: 8729);
                $api = new MikroTikAPI($apiIP, $apiPort, 'jasiri-api', $router['password'] ?? '');
                $api->connect();
                $api->addHotspotUser('hotspot1', $voucher['code'], $voucher['code'], $uptime);
                $api->close();
                $autoLogin = true;
                $success = 'Voucher activated! Connecting you now...';
            } catch (Exception $e) {
                $autoLogin = false;
                $success = 'Voucher activated! Go back and login with your voucher code as both username and password.';
            }

            unset($_SESSION['pending_voucher'], $_SESSION['pending_mac']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jasiri WiFi - Unganisha</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #f0f7ff 0%, #d6ecff 100%); 
            color: #111111;
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px 0;
        }
        .card { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 24px; 
            padding: 36px 28px; 
            max-width: 400px; 
            width: 90%; 
            box-shadow: 0 20px 40px rgba(0, 102, 204, 0.08), 0 1px 3px rgba(0, 0, 0, 0.03); 
        }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(214, 236, 255, 0.8);
            border-radius: 50%;
            margin-bottom: 12px;
            box-shadow: 0 8px 16px rgba(0, 122, 255, 0.06);
        }
        .logo svg { width: 26px; height: 26px; fill: #0066cc; }
        .logo h1 { font-size: 24px; color: #111; font-weight: 700; letter-spacing: -0.5px; }
        .logo p { color: #666; font-size: 13px; margin-top: 4px; font-weight: 400; }
        
        .suggestions {
            margin-bottom: 22px;
        }
        .suggestions-title {
            font-size: 11px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }
        .chips-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .chip {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(214, 236, 255, 0.8);
            border-radius: 12px;
            padding: 12px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .chip:hover {
            border-color: #0066cc;
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 102, 204, 0.08);
        }
        .chip .time {
            display: block;
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
            font-weight: 500;
        }
        .chip .price {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #0066cc;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 11px; color: #555; margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-group input { 
            width: 100%; 
            padding: 15px 16px; 
            border: 1.5px solid rgba(200, 225, 250, 0.8); 
            border-radius: 14px; 
            font-size: 18px; 
            text-align: center; 
            letter-spacing: 4px; 
            font-weight: 600; 
            outline: none; 
            background: rgba(255, 255, 255, 0.9);
            color: #111;
            transition: all 0.25s ease; 
        }
        .form-group input:focus { border-color: #0066cc; background: #ffffff; box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1); }
        .btn { 
            width: 100%; 
            padding: 15px; 
            background: #0066cc; 
            color: white; 
            border: none; 
            border-radius: 14px; 
            font-size: 15px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); 
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.2);
        }
        .btn:hover { background: #0052a3; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0, 102, 204, 0.3); }
        .btn:active { transform: translateY(0); }
        
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .alert-error { background: rgba(255, 245, 245, 0.9); color: #c53030; border: 1px solid rgba(254, 215, 215, 0.8); }
        .alert-success { background: rgba(240, 255, 244, 0.9); color: #22543d; border: 1px solid rgba(198, 246, 213, 0.8); }
        
        .support-info {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding-top: 16px;
        }
        .support-info span { font-weight: 600; color: #111; }
        .footer { text-align: center; margin-top: 14px; font-size: 11px; color: #888; letter-spacing: 0.3px; }

        /* Connecting Loader */
        .connecting-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.96);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .earth-loader {
            --watercolor: #3344c1;
            --landcolor: #7cc133;
            width: 7.5em;
            height: 7.5em;
            background-color: var(--watercolor);
            position: relative;
            overflow: hidden;
            border-radius: 50%;
            box-shadow:
                inset 0em 0.5em rgba(255,255,255,0.25),
                inset 0em -0.5em rgba(0,0,0,0.25);
            border: solid 0.15em white;
        }

        .connecting-overlay p {
            margin-top:20px;
            color:#3344c1;
            font-size:18px;
            font-weight:600;
        }

        .earth-loader svg:nth-child(1) {
            position:absolute;
            bottom:-2em;
            width:7em;
            animation:round1 5s infinite linear .75s;
        }

        .earth-loader svg:nth-child(2) {
            position:absolute;
            top:-3em;
            width:7em;
            animation:round1 5s infinite linear;
        }

        .earth-loader svg:nth-child(3) {
            position:absolute;
            top:-2.5em;
            width:7em;
            animation:round2 5s infinite linear;
        }

        .earth-loader svg:nth-child(4) {
            position:absolute;
            bottom:-2.2em;
            width:7em;
            animation:round2 5s infinite linear .75s;
        }

        @keyframes round1 {
            0% { left:-2em; transform:rotate(0deg); }
            50% { left:-6em; transform:rotate(25deg); }
            100% { left:7em; transform:rotate(-25deg); }
        }

        @keyframes round2 {
            0% { left:5em; transform:rotate(0deg); }
            50% { left:-7em; transform:rotate(25deg); }
            100% { left:8em; transform:rotate(-25deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <div class="logo-icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61l1.42-1.42C5.64 15.05 5 13.6 5 12c0-3.87 3.13-7 7-7s7 3.13 7 7c0 1.6-.64 3.05-1.39 4.19l1.42 1.42C20.26 16.07 21 14.12 21 12c0-4.97-4.03-9-9-9zm0 4c-2.76 0-5 2.24-5 5 0 1.18.42 2.26 1.12 3.11l1.42-1.42C9.17 13.34 9 12.69 9 12c0-1.66 1.34-3 3-3s3 1.34 3 3c0 .69-.17 1.34-.54 1.69l1.42 1.42C18.58 14.26 19 13.18 19 12c0-2.76-2.24-5-5-5zm0 4c-.55 0-1 .45-1 1 0 .28.11.53.29.71l1.42 1.42c.18.18.43.29.71.29.55 0 1-.45 1-1 0-.55-.45-1-1-1zm0 4a3.001 3.001 0 0 1-2.83-2H14.83A3.001 3.001 0 0 1 12 15z"/></svg>
            </div>
            <h1>Jasiri WiFi</h1>
            <p><?= $showPhoneForm ? 'Thibitisha namba yako ya simu' : 'Weka namba ya vocha yako kuingia mtandaoni' ?></p>
        </div>

        <?php if (!$showPhoneForm): ?>
        <div class="suggestions">
            <div class="suggestions-title">Nunua Vocher</div>
            <div class="chips-grid">
                <div class="chip">
                    <span class="time">Masaa 12</span>
                    <span class="price">500 TSH</span>
                </div>
                <div class="chip">
                    <span class="time">Masaa 24</span>
                    <span class="price">1000 TSH</span>
                </div>
                <div class="chip">
                    <span class="time">Siku 7</span>
                    <span class="price">5000 TSH</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php if (!empty($autoLogin)): ?>
            <form id="autoLogin" action="http://10.10.0.1/login" method="post">
                <input type="hidden" name="username" value="<?= htmlspecialchars($voucherCode ?? '') ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($voucherCode ?? '') ?>">
                <input type="hidden" name="dst" value="<?= htmlspecialchars($redirURL) ?>">
            </form>
            <script>setTimeout(function(){ document.getElementById('autoLogin').submit(); }, 2000);</script>
            <?php endif; ?>
        <?php elseif ($showPhoneForm): ?>
        <!-- Step 2: Phone number confirmation -->
        <div class="alert" style="background: rgba(240, 248, 255, 0.9); color: #004080; border: 1px solid rgba(184, 218, 255, 0.8); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; text-align: center; font-size: 14px;">
            <strong>Voucher <?= htmlspecialchars($voucherCode) ?></strong> ni sahihi. Tafadhali weka namba yako ya simu.
        </div>
        <form method="POST" action="?router=<?= urlencode($routerDeviceId) ?>&mac=<?= urlencode($clientMAC) ?>">
            <div class="form-group">
                <label>Namba ya Simu</label>
                <input type="tel" name="phone" placeholder="0758 224 994" required autofocus
                    oninput="this.value = this.value.replace(/[^0-9]/g,'')">
            </div>
            <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
            <input type="hidden" name="step" value="2">
            <button type="submit" class="btn">Thibitisha na Unganisha</button>
        </form>
        <?php else: ?>
        <form method="POST" action="?router=<?= urlencode($routerDeviceId) ?>&mac=<?= urlencode($clientMAC) ?>">
            <div class="form-group">
                <label>Namba ya Vocha</label>
                <input type="text" name="voucher_code" placeholder="0000 0000" maxlength="11" required autofocus
                    oninput="this.value = this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim()">
            </div>
            <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
            <input type="hidden" name="step" value="1">
            <button type="submit" class="btn">Unganisha</button>
        </form>
        <?php endif; ?>

        <div class="support-info">
            Una tatizo? Wasiliana na huduma kwa wateja: <span>0758244994</span>
        </div>

        <div class="footer">Imeywezeshwa na Jasiri WiFi</div>
    </div>

    <div class="connecting-overlay" id="connectingOverlay">
        <div class="earth-loader">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="90" fill="#7CC133"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="90" fill="#7CC133"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="90" fill="#7CC133"/>
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="90" fill="#7CC133"/>
            </svg>
        </div>
        <p>Inaunganisha Jasiri WiFi...</p>
    </div>

    <script>
        const loginForm = document.querySelector("form[method='POST']");
        if (loginForm) {
            const connectBtn = loginForm.querySelector(".btn");
            loginForm.addEventListener("submit", function(){
                connectBtn.disabled = true;
                connectBtn.innerHTML = "Inaunganisha...";
                document.getElementById("connectingOverlay").style.display = "flex";
            });
        }
    </script>
</body>
</html>
