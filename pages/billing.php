<?php
ob_start();

// Include header and sidebar
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';

// Connect to SQLite Database
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch all routers and plans
$routers = $db->query("SELECT id, name FROM routers")->fetchAll(PDO::FETCH_ASSOC);
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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_id'], $_POST['new_plan_id'])) {
        $userId = $_POST['user_id'];
        $newPlanId = $_POST['new_plan_id'];

        // Get user info (including MAC + router)
        $userStmt = $db->prepare("SELECT mac, router_id FROM billing WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            die("User not found");
        }

        // Get plan
        $planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $planStmt->execute([$newPlanId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            die("Plan not found");
        }

        // Calculate duration
        $duration = ($plan['days'] ?? 0) * 86400 +
                    ($plan['hours'] ?? 0) * 3600 +
                    ($plan['minutes'] ?? 0) * 60;

        // Use current time
        $now = time();
        $endAt = date('Y-m-d H:i:s', $now + $duration);

        // Update using MAC + router_id (IMPORTANT)
        $update = $db->prepare("
            UPDATE billing 
            SET plan_id = ?, remaining_time = ?, end_at = ?, internet_access = 1 
            WHERE mac = ? AND router_id = ?
        ");

        $update->execute([
            $newPlanId,
            $duration,
            $endAt,
            $user['mac'],
            $user['router_id']
        ]);

        echo "Updated rows: " . $update->rowCount();
        exit;
    }

    if (isset($_POST['delete_user_id'])) {
        $deleteUserId = $_POST['delete_user_id'];
        $deleteStmt = $db->prepare("DELETE FROM billing WHERE id = ?");
        $deleteStmt->execute([$deleteUserId]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">Expired Users by Router</h1>

            <?php foreach ($routers as $router): ?>
                <h2><?php echo htmlspecialchars($router['name']); ?></h2>

                <?php
                $stmt = $db->prepare("
                    SELECT b.*, p.name AS plan_name, p.days, p.hours, p.minutes
                    FROM billing b
                    JOIN plans p ON b.plan_id = p.id
                    WHERE b.router_id = ?
                    ORDER BY b.created_at DESC
                ");
                $stmt->execute([$router['id']]);
                $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Filter expired users only
                $users = array_filter($allUsers, function($u) {
                    return (strtotime($u['end_at']) - time()) <= 0;
                });
                ?>

                <?php if ($users): ?>
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
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
                                        $rowId = 'user-' . $user['id'];

                                        // Ensure internet_access is 0 for expired users
                                        $updateAccessStmt = $db->prepare("UPDATE billing SET internet_access = 0 WHERE id = ?");
                                        $updateAccessStmt->execute([$user['id']]);

                                        $planDuration = ($user['days'] ?? 0) . "d " . ($user['hours'] ?? 0) . "h " . ($user['minutes'] ?? 0) . "m";
                                    ?>
                                    <tr id="<?php echo $rowId; ?>" style="background-color: #f8d7da;">
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($user['mac']); ?></td>
                                        <td><?php echo htmlspecialchars($user['plan_name']); ?></td>
                                        <td><?php echo $planDuration; ?></td>
                                        <td class="remaining-time" 
                                            data-user-id="<?php echo $user['id']; ?>" 
                                            data-end="<?php echo $user['end_at']; ?>">
                                            <?php echo formatRemainingTime($remainingSeconds); ?>
                                        </td>
                                        <td><?php echo $user['created_at']; ?></td>
                                        <td><?php echo $user['end_at']; ?></td>
                                        <td>
                                            <form method="POST" class="mb-1">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="new_plan_id" class="form-control form-control-sm mb-1">
                                                    <?php foreach ($plans as $plan): ?>
                                                        <option value="<?php echo $plan['id']; ?>" <?php echo $plan['id'] == $user['plan_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($plan['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-info btn-sm w-100">Change Plan</button>
                                            </form>
                                            <form method="POST" class="mb-1">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm w-100">Delete</button>
                                            </form>
                                            <!-- Throttle/Unthrottle buttons -->
                                            <button class="btn btn-warning btn-sm w-100 mb-1" onclick="throttleDevice('<?php echo $user['mac']; ?>')">Throttle</button>
                                            <button class="btn btn-success btn-sm w-100" onclick="unthrottleDevice('<?php echo $user['mac']; ?>')">Unthrottle</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No expired users found for this router.</p>
                <?php endif; ?>

            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
// JavaScript countdown
document.querySelectorAll('.remaining-time').forEach(td => {
    const userId = td.dataset.userId;
    const endAt = new Date(td.dataset.end);
    const row = document.getElementById('user-' + userId);

    const interval = setInterval(() => {
        const now = new Date();
        let remaining = Math.floor((endAt - now) / 1000);
        if (remaining < 0) remaining = 0;

        let d = Math.floor(remaining / 86400);
        let h = Math.floor((remaining % 86400) / 3600);
        let m = Math.floor((remaining % 3600) / 60);
        let s = remaining % 60;
        td.textContent = `${d}d ${h}h ${m}m ${s}s`;

        if (remaining <= 0) {
            row.style.backgroundColor = '#f8d7da';
        }
    }, 1000);
});

// --- Throttle/Unthrottle functions ---
async function throttleDevice(mac) {
    try {
        const res = await fetch('/auth/v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'throttle_device', mac })
        });
        const json = await res.json();
        if (json.success) {
            alert(`Device ${mac} throttled successfully`);
            location.reload(); // refresh page to update status
        } else {
            alert(json.message || 'Failed to throttle device');
        }
    } catch (err) {
        console.error(err);
        alert('Error throttling device');
    }
}

async function unthrottleDevice(mac) {
    try {
        const res = await fetch('/auth/v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'unthrottle_device', mac })
        });
        const json = await res.json();
        if (json.success) {
            alert(`Device ${mac} unthrottled successfully`);
            location.reload(); // refresh page to update status
        } else {
            alert(json.message || 'Failed to unthrottle device');
        }
    } catch (err) {
        console.error(err);
        alert('Error unthrottling device');
    }
}
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
