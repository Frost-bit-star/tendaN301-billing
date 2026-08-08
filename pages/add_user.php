<?php
ob_start();
$pageTitle = 'Add User';
$activePage = 'add_user';

include __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../auth/config.php';

// -----------------------
// RETRIEVE PARAMETERS
// -----------------------
$routerId   = $_GET['router_id'] ?? '';
$macAddress = strtoupper($_GET['paid_mac'] ?? '');
$planId     = $_GET['plan_id'] ?? '';

if (!$routerId || !$macAddress || !$planId) {
    echo "<div class='alert alert-danger'><svg viewBox='0 0 24 24'><path d='M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z'/></svg><span>Missing required parameters. Please check the URL and try again.</span></div>";
    exit;
}

// -----------------------
// DATABASE CONNECTION
// -----------------------
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure billing table has necessary columns
$db->exec("
CREATE TABLE IF NOT EXISTS billing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    router_id INTEGER NOT NULL,
    mac TEXT NOT NULL,
    plan_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    phone_number TEXT NOT NULL,
    remaining_time INTEGER,
    end_at TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id),
    UNIQUE(mac, router_id)
)
");

// Add missing columns if they do not exist
$columns = $db->query("PRAGMA table_info(billing)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'name');
if (!in_array('remaining_time', $colNames)) {
    $db->exec("ALTER TABLE billing ADD COLUMN remaining_time INTEGER DEFAULT 0");
}
if (!in_array('end_at', $colNames)) {
    $db->exec("ALTER TABLE billing ADD COLUMN end_at TEXT DEFAULT NULL");
}

// -----------------------
// FETCH ROUTER & PLAN (tenant-scoped)
// -----------------------
$tenantId = $_SESSION['account_id'] ?? null;
$isAdmin  = ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin');

if ($isAdmin) {
    $routerStmt = $db->prepare("SELECT * FROM routers WHERE id = ?");
    $routerStmt->execute([$routerId]);
} else {
    $routerStmt = $db->prepare("SELECT * FROM routers WHERE id = ? AND tenant_id = ?");
    $routerStmt->execute([$routerId, $tenantId]);
}
$router = $routerStmt->fetch(PDO::FETCH_ASSOC);

$planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
$planStmt->execute([$planId]);
$plan = $planStmt->fetch(PDO::FETCH_ASSOC);

if (!$router || !$plan) {
    echo "<div class='alert alert-danger'><svg viewBox='0 0 24 24'><path d='M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z'/></svg><span>Invalid router or plan data. Please check the URL and try again.</span></div>";
    exit;
}

// Calculate plan duration in seconds
$durationInSeconds = ($plan['days'] ?? 0) * 86400
                   + ($plan['hours'] ?? 0) * 3600
                   + ($plan['minutes'] ?? 0) * 60;
$endAt = date('Y-m-d H:i:s', time() + $durationInSeconds);

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
// HANDLE FORM SUBMISSION
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? 'Unknown';
    $phoneNumber = $_POST['phone_number'] ?? '';

    try {
        // 1️⃣ Insert into billing
        $stmt = $db->prepare("
            INSERT INTO billing (router_id, mac, plan_id, name, phone_number, remaining_time, end_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$routerId, $macAddress, $planId, $name, $phoneNumber, $durationInSeconds, $endAt]);

        // 2️⃣ Update or insert user as paid
        $stmtCheck = $db->prepare("SELECT * FROM users WHERE mac = ? AND router_id = ?");
        $stmtCheck->execute([$macAddress, $routerId]);
        $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $stmtUpdate = $db->prepare("UPDATE users SET internet_access = 1 WHERE mac = ? AND router_id = ?");
            $stmtUpdate->execute([$macAddress, $routerId]);
        } else {
            $stmtInsert = $db->prepare("
                INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
                VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
            ");
            $stmtInsert->execute(['unknown', '', $macAddress, $routerId]);
        }

        // -----------------------
        // PUSH TO ROUTER (SYNC ALL USERS)
        // -----------------------
        $ip = $router['ip'];
        $port = $router['port'] ?: 80;
        $password = $router['password'];
        $routerUrl = "http://$ip" . ($port != 80 ? ":$port" : "");

        $cookie = createCookieFile();
        curl_post("$routerUrl/login/Auth", ["password" => base64_encode($password)], $cookie);

        $stmtUsers = $db->prepare("SELECT * FROM users WHERE router_id = ?");
        $stmtUsers->execute([$routerId]);
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $onlineList = [];
        foreach ($users as $u) {
            $mac = strtoupper($u['mac']);
            $hostname = $u['hostname'] ?: 'unknown';
            $upLimit = ((int)$u['internet_access'] === 1) ? 10240 : 1;
            $downLimit = $upLimit;
            $onlineList[] = "$hostname\t$hostname\t$mac\t$upLimit\t$downLimit\ttrue";
        }

        curl_post("$routerUrl/goform/setQos", [
            'module1' => 'onlineList',
            'onlineList' => implode("\n", $onlineList),
            'onlineListLen' => count($onlineList),
            'qosEn' => '1',
            'qosAccessEn' => '1'
        ], $cookie, "$routerUrl/index.html");

        curl_post("$routerUrl/goform/save", ['random' => time()], $cookie, "$routerUrl/index.html");

        header("Location: /view");
        exit;

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'><svg viewBox='0 0 24 24'><path d='M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z'/></svg><span>Error: " . htmlspecialchars($e->getMessage()) . "</span></div>";
    }
}
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Add User</h1>
        <p class="page-subtitle">Activate a device by assigning it a plan</p>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-header"><div class="card-title">Plan &amp; Device Details</div></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Router Name</label>
                <div style="font-size:14px;font-weight:500"><?php echo htmlspecialchars($router['name']); ?></div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">MAC Address</label>
                <div><code><?php echo htmlspecialchars($macAddress); ?></code></div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Plan Name</label>
                <div style="font-size:14px;font-weight:500"><?php echo htmlspecialchars($plan['name']); ?></div>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Plan Duration</label>
                <div style="font-size:14px;font-weight:500">
                    <?php echo ($plan['days'] ?? 0) . " days " . ($plan['hours'] ?? 0) . " hours " . ($plan['minutes'] ?? 0) . " minutes"; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="max-width:560px">
    <div class="card-header"><div class="card-title">Customer Details</div></div>
    <div class="card-body">
        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" for="userName">Name</label>
                <input type="text" name="name" id="userName" class="form-control" placeholder="Enter Name" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="userPhone">Phone Number</label>
                <input type="text" name="phone_number" id="userPhone" class="form-control" placeholder="Enter Phone Number" required>
            </div>

            <button type="submit" class="btn btn-primary">Save User &amp; Activate</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
