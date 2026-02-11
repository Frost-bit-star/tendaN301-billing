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

// Function to format remaining time (for initial render)
function formatRemainingTime($seconds) {
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

        $newPlanStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $newPlanStmt->execute([$newPlanId]);
        $newPlan = $newPlanStmt->fetch(PDO::FETCH_ASSOC);

        $durationInSeconds = ($newPlan['days'] ?? 0) * 86400
                           + ($newPlan['hours'] ?? 0) * 3600
                           + ($newPlan['minutes'] ?? 0) * 60;

        $updateStmt = $db->prepare("UPDATE billing SET plan_id = ?, remaining_time = ? WHERE id = ?");
        $updateStmt->execute([$newPlanId, $durationInSeconds, $userId]);

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
}
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">Users by Router</h1>

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
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user):
                                        $planDuration = ($user['days'] ?? 0) . "d "
                                                      . ($user['hours'] ?? 0) . "h "
                                                      . ($user['minutes'] ?? 0) . "m";
                                        $isExpired = $user['remaining_time'] <= 0;
                                    ?>
                                        <tr id="user-<?php echo $user['id']; ?>" style="background-color: <?php echo $isExpired ? '#f8d7da' : ''; ?>">
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars($user['mac']); ?></td>
                                            <td><?php echo htmlspecialchars($user['plan_name']); ?></td>
                                            <td><?php echo $planDuration; ?></td>
                                            <td class="remaining-time" 
                                                data-user-id="<?php echo $user['id']; ?>" 
                                                data-remaining="<?php echo $user['remaining_time']; ?>">
                                                <?php echo formatRemainingTime($user['remaining_time']); ?>
                                            </td>
                                            <td><?php echo $user['created_at']; ?></td>
                                            <td>
                                                <form method="POST" class="mb-1">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <select name="new_plan_id" class="form-control form-control-sm mb-1" <?php echo $isExpired ? 'disabled' : ''; ?>>
                                                        <?php foreach ($plans as $plan): ?>
                                                            <option value="<?php echo $plan['id']; ?>" <?php echo $plan['id'] == $user['plan_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($plan['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-info btn-sm w-100" <?php echo $isExpired ? 'disabled' : ''; ?>>Change Plan</button>
                                                </form>
                                                <form method="POST">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm w-100">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No users found for this router.</p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
// JavaScript countdown, directly using remaining_time from DB
document.querySelectorAll('.remaining-time').forEach(td => {
    let remaining = parseInt(td.dataset.remaining);
    const userId = td.dataset.userId;

    const interval = setInterval(() => {
        if (remaining > 0) remaining--;

        let d = Math.floor(remaining / 86400);
        let h = Math.floor((remaining % 86400) / 3600);
        let m = Math.floor((remaining % 3600) / 60);
        let s = remaining % 60;
        td.textContent = `${d}d ${h}h ${m}m ${s}s`;

        // Mark expired
        if (remaining <= 0) {
            const row = document.getElementById('user-' + userId);
            row.style.backgroundColor = '#f8d7da';
            const select = row.querySelector('select');
            const button = row.querySelector('button');
            if (select) select.disabled = true;
            if (button) button.disabled = true;
            clearInterval(interval);
        }
    }, 1000);
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
