<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db/schema.php';
require_once __DIR__ . '/../api/mikrotik_api.php';

$routerDeviceId = $_GET['router'] ?? '';
$clientMAC = $_GET['mac'] ?? $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$redirURL = $_GET['url'] ?? 'http://example.com';
$error = '';
$success = '';
$autoLogin = false;

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
    $mac = trim($_POST['mac'] ?? $clientMAC);

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
            $duration = ($voucher['days'] ?? 0) * 86400 + ($voucher['hours'] ?? 0) * 3600 + ($voucher['minutes'] ?? 0) * 60;
            if ($duration <= 0) $duration = 3600;
            $hours = floor($duration / 3600);
            $mins = floor(($duration % 3600) / 60);
            $uptime = "${hours}h${mins}m0s";

            $stmt = $db->prepare("UPDATE vouchers SET status = 'used', used_at = :ts, used_mac = :mac, router_id = :rid WHERE id = :id");
            $stmt->execute([
                ':ts' => date('Y-m-d H:i:s'),
                ':mac' => $mac,
                ':rid' => $router['id'],
                ':id' => $voucher['id'],
            ]);

            try {
                $apiIP = $router['wireguard_ip'];
                if (empty($apiIP) || $apiIP === '0.0.0.0' || !empty($router['ip']) && $router['ip'] !== '0.0.0.0') {
                    $testIP = !empty($router['ip']) && $router['ip'] !== '0.0.0.0' ? $router['ip'] : $apiIP;
                    $fp = @fsockopen($testIP, 8729, $errno, $errstr, 2);
                    if (is_resource($fp)) {
                        fclose($fp);
                        $apiIP = $testIP;
                    }
                }
                $apiPort = intval($router['port'] ?: 8729);
                $api = new MikroTikAPI($apiIP, $apiPort, 'jasiri-api', $router['password'] ?? '');
                $api->connect();
                $api->addHotspotUser('hotspot1', $voucherCode, $voucherCode, $uptime);
                $api->close();
                $autoLogin = true;
                $success = 'Voucher activated! Connecting you now...';
            } catch (Exception $e) {
                $autoLogin = false;
                $success = 'Voucher activated! Go back and login with your voucher code as both username and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jasiri WiFi - Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 20px; padding: 40px; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo h1 { font-size: 28px; color: #333; }
        .logo p { color: #888; font-size: 14px; margin-top: 4px; }
        .wifi-icon { font-size: 48px; margin-bottom: 12px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; color: #666; margin-bottom: 6px; font-weight: 500; }
        .form-group input { width: 100%; padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 18px; text-align: center; letter-spacing: 4px; font-weight: 600; outline: none; transition: border-color 0.3s; }
        .form-group input:focus { border-color: #667eea; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn:active { transform: translateY(0); }
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
        .alert-error { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #28a745; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <div class="wifi-icon">📶</div>
            <h1>Jasiri WiFi</h1>
            <p>Enter your voucher code to get online</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php if (!empty($autoLogin)): ?>
            <form id="autoLogin" action="http://10.10.0.1/login" method="post">
                <input type="hidden" name="username" value="<?= htmlspecialchars($voucherCode ?? '') ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($voucherCode ?? '') ?>">
                <input type="hidden" name="dst" value="<?= htmlspecialchars($redirURL) ?>">
            </form>
            <script>setTimeout(function(){ document.getElementById('autoLogin').submit(); }, 2000);</script>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="?router=<?= urlencode($routerDeviceId) ?>&mac=<?= urlencode($clientMAC) ?>">
            <div class="form-group">
                <label>Voucher Code</label>
                <input type="text" name="voucher_code" placeholder="0000 0000" maxlength="11" required autofocus
                    oninput="this.value = this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim()">
            </div>
            <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
            <button type="submit" class="btn">Connect</button>
        </form>

        <div class="footer">Powered by Jasiri WiFi</div>
    </div>
</body>
</html>
