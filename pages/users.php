<?php
ob_start();
$pageTitle = 'Users';
$activePage = 'users';

// Include header and sidebar
include __DIR__ . '/../components/header.php';

// Connect to SQLite Database
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Tenant scope
$tenantId = $_SESSION['account_id'] ?? null;
$isAdmin  = ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin');

// Fetch routers and plans scoped by tenant
if ($isAdmin) {
    $routers = $db->query("SELECT id, name FROM routers")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT id, name FROM routers WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$plans = $db->query("SELECT * FROM plans")->fetchAll(PDO::FETCH_ASSOC);

// Function to format remaining time
function formatRemainingTime($seconds) {
    if ($seconds <= 0) return "0d 0h 0m 0s";
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return "{$days}d {$hours}h {$minutes}m {$secs}s";
}

// Fetch whitelist from API with improved error handling
function getRouterWhitelist($routerId) {
    $url = "/auth/v2.php";
    $payload = json_encode([
        "action" => "get_users",
        "router_id" => $routerId
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if ($res === false) {
        return [];
    }

    $data = json_decode($res, true);
    return $data && !empty($data['results'][0]['whitelist']) ? array_keys($data['results'][0]['whitelist']) : [];
}

// Handle POST actions (plan change, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_id'], $_POST['new_plan_id'])) {
        $userId = $_POST['user_id'];
        $newPlanId = $_POST['new_plan_id'];

        // Fetch the selected plan
        $newPlanStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $newPlanStmt->execute([$newPlanId]);
        $newPlan = $newPlanStmt->fetch(PDO::FETCH_ASSOC);

        $durationInSeconds = ($newPlan['days'] ?? 0) * 86400
                           + ($newPlan['hours'] ?? 0) * 3600
                           + ($newPlan['minutes'] ?? 0) * 60;

        // Fetch user's created_at
        $userStmt = $db->prepare("SELECT created_at FROM billing WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $createdAt = strtotime($user['created_at']);

        // Calculate end_at
        $endAt = date('Y-m-d H:i:s', $createdAt + $durationInSeconds);

        // Update billing table and restore internet access
        $updateStmt = $db->prepare("UPDATE billing SET plan_id = ?, remaining_time = ?, end_at = ?, internet_access = 1 WHERE id = ?");
        $updateStmt->execute([$newPlanId, $durationInSeconds, $endAt, $userId]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['delete_user_id'])) {
        $deleteUserId = $_POST['delete_user_id'];
        $deleteStmt = $db->prepare("DELETE FROM billing WHERE id = ?");
        $deleteStmt->execute([$deleteUserId]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Sync Logic
    if (isset($_POST['sync_router_id'])) {
        $routerId = $_POST['sync_router_id'];
        $stmtRouter = $db->prepare("SELECT * FROM routers WHERE id = ?");
        $stmtRouter->execute([$routerId]);
        $router = $stmtRouter->fetch(PDO::FETCH_ASSOC);

        if ($router) {
            // Fetch whitelist and compare
            $whitelistMacs = array_map(function($mac) {
                return strtoupper(preg_replace('/[^A-F0-9]/', '', str_replace(':', '', $mac)));
            }, getRouterWhitelist($router['id']));

            $stmt = $db->prepare("
                SELECT b.*, p.name AS plan_name, p.days, p.hours, p.minutes
                FROM billing b
                JOIN plans p ON b.plan_id = p.id
                WHERE b.router_id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$router['id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recalculate DB MACs
            $dbMacs = array_map(function($u) {
                return strtoupper(preg_replace('/[^A-F0-9]/', '', str_replace(':', '', $u['mac'])));
            }, $users);

            $missingInDb = array_diff($whitelistMacs, $dbMacs);
            $extraInDb   = array_diff($dbMacs, $whitelistMacs);

            //  Add missing
            foreach ($missingInDb as $mac) {
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM billing WHERE mac = ? AND router_id = ?");
                $stmtCheck->execute([$mac, $router['id']]);

                if ($stmtCheck->fetchColumn() == 0) {
                    $stmtInsert = $db->prepare("
                        INSERT INTO billing 
                        (name, phone_number, mac, router_id, plan_id, created_at, end_at, internet_access)
                        VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'), 1)
                    ");
                    $stmtInsert->execute([
                        'Auto-Added Device',
                        '',
                        $mac,
                        $router['id'],
                        1
                    ]);
                }
            }

            //  Disable extra (DO NOT DELETE)
            foreach ($extraInDb as $mac) {
                $stmtUpdate = $db->prepare("
                    UPDATE billing 
                    SET internet_access = 0 
                    WHERE mac = ? AND router_id = ?
                ");
                $stmtUpdate->execute([$mac, $router['id']]);
            }

            //  Reload page after sync
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Users by Router</h1>
        <p class="page-subtitle">
            <span id="time"></span> <span id="ampm"></span> &middot; <span id="date"></span>
        </p>
    </div>
</div>

<?php foreach ($routers as $router): ?>
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <div class="card-title">
                <?php echo htmlspecialchars($router['name']); ?>
            </div>
            <form method="POST" style="margin:0">
                <input type="hidden" name="sync_router_id" value="<?php echo $router['id']; ?>">
                <button class="btn btn-outline btn-sm">Sync Router</button>
            </form>
        </div>

        <?php
        // Fetch all users for the router
        $stmt = $db->prepare("
            SELECT b.*, p.name AS plan_name, p.days, p.hours, p.minutes
            FROM billing b
            JOIN plans p ON b.plan_id = p.id
            WHERE b.router_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$router['id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch whitelist and compare
        $whitelistMacs = array_map(function($mac) {
            return strtoupper(preg_replace('/[^A-F0-9]/', '', str_replace(':', '', $mac)));
        }, getRouterWhitelist($router['id']));
        $dbMacs = array_map(function($u) {
            return strtoupper(preg_replace('/[^A-F0-9]/', '', str_replace(':', '', $u['mac'])));
        }, $users);
        $missingInDb = array_diff($whitelistMacs, $dbMacs);
        $extraInDb   = array_diff($dbMacs, $whitelistMacs);
        ?>

        <div class="alert alert-info" style="margin:16px">
            <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
            <span>
                <strong>Sync Status:</strong>
                Whitelist: <?php echo count($whitelistMacs); ?> |
                Database: <?php echo count($dbMacs); ?> |
                Missing in DB: <?php echo count($missingInDb); ?> |
                Extra in DB: <?php echo count($extraInDb); ?>
            </span>
        </div>

        <?php if ($users): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone Number</th>
                            <th>MAC Address</th>
                            <th>Plan</th>
                            <th>Plan Duration</th>
                            <th>Remaining Time</th>
                            <th>Created At</th>
                            <th>Ends At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            $remainingSeconds = max(strtotime($user['end_at']) - time(), 0);
                            $planDuration = ($user['days'] ?? 0) . "d " . ($user['hours'] ?? 0) . "h " . ($user['minutes'] ?? 0) . "m";
                        ?>
                        <tr id="user-<?php echo $user['id']; ?>"
                            data-router-id="<?php echo $router['id']; ?>"
                            data-mac="<?php echo $user['mac']; ?>">
                            <td style="font-weight:500"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                            <td><code><?php echo htmlspecialchars($user['mac']); ?></code></td>
                            <td><?php echo htmlspecialchars($user['plan_name']); ?></td>
                            <td><?php echo $planDuration; ?></td>
                            <td class="remaining-time" data-end="<?php echo $user['end_at']; ?>" style="font-size:12px">
                                <?php echo formatRemainingTime($remainingSeconds); ?>
                            </td>
                            <td style="font-size:12px;color:var(--on-surface-med)"><?php echo $user['created_at']; ?></td>
                            <td style="font-size:12px;color:var(--on-surface-med)"><?php echo $user['end_at']; ?></td>
                            <td>
                                <div class="td-actions">
                                    <form method="POST" class="mb-0">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="new_plan_id" class="form-control" style="padding:5px 10px;font-size:12px">
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?php echo $plan['id']; ?>" <?php echo $plan['id'] == $user['plan_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($plan['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">Change Plan</button>
                                    </form>
                                    <form method="POST" class="mb-0">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%" onclick="return confirm('Delete this user?')">Delete</button>
                                    </form>
                                    <form method="POST" class="mb-0 throttle-form">
                                        <input type="hidden" name="throttle_user_id" value="<?php echo $user['id']; ?>">
                                        <input type="number" name="upload_speed" class="form-control" style="padding:5px 10px;font-size:12px" placeholder="Upload (kbps)" step="0.01" required>
                                        <input type="number" name="download_speed" class="form-control" style="padding:5px 10px;font-size:12px" placeholder="Download (kbps)" step="0.01" required>
                                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%">Throttle</button>
                                    </form>
                                    <button class="btn btn-primary btn-sm unthrottle-btn" style="width:100%"
                                        data-router-id="<?php echo $router['id']; ?>"
                                        data-mac="<?php echo $user['mac']; ?>">Unthrottle</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:40px">
                <div class="empty-state-icon"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>
                <h3>No users found</h3>
                <p>No users found for this router.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
// Real-time clock
function updateClock() {
    const now = new Date();

    // 12-hour format
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12;

    document.getElementById("time").textContent = `${hours}:${minutes}`;
    document.getElementById("ampm").textContent = ampm;

    // Full date
    const dateOptions = { weekday: "long", month: "long", day: "numeric" };
    document.getElementById("date").textContent = now.toLocaleDateString(undefined, dateOptions);

    // Update remaining time
    document.querySelectorAll('.remaining-time').forEach(td => {
        const endAt = new Date(td.dataset.end);
        let remaining = Math.floor((endAt - now) / 1000);
        if (remaining < 0) remaining = 0;
        let d = Math.floor(remaining / 86400);
        let h = Math.floor((remaining % 86400) / 3600);
        let m = Math.floor((remaining % 3600) / 60);
        let s = remaining % 60;
        td.textContent = `${d}d ${h}h ${m}m ${s}s`;
    });
}

// Initial run
updateClock();
setInterval(updateClock, 1000);

// Manual unthrottle
document.querySelectorAll('.unthrottle-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const mac = btn.dataset.mac;
        try {
            const res = await fetch('/auth/v2.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'unthrottle_device', mac })
            });
            const json = await res.json();
            if (json.success) alert(`Device ${mac} unthrottled`);
            else alert(json.message || 'Failed to unthrottle device');
            location.reload();
        } catch (err) {
            console.error(err);
            alert('Error unthrottling device');
        }
    });
});

// Throttle form submission with JS
document.querySelectorAll('.throttle-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const row = form.closest('tr');
        const mac = row.dataset.mac;
        const routerId = row.dataset.routerId;

        const upload = form.querySelector('[name="upload_speed"]').value;
        const download = form.querySelector('[name="download_speed"]').value;

        try {
            const res = await fetch(
                `/auth/throttle.php?action=set_throttle&router_id=${routerId}&mac=${mac}&up=${upload}&down=${download}`
            );

            const result = await res.json();

            if (result.success) {
                alert(` Throttle set for ${mac}`);
            } else {
                alert(` Failed: ${result.error || 'Unknown error'}`);
            }

        } catch (err) {
            console.error(err);
            alert(' Error applying throttle');
        }
    });
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
