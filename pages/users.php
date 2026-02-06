<?php
// Start output buffering
ob_start();

// Include header and sidebar
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';

// Connect to SQLite Database
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch all routers from the database
$routerStmt = $db->prepare("SELECT id, name FROM routers");
$routerStmt->execute();
$routers = $routerStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all plans for the dropdowns
$plansStmt = $db->prepare("SELECT * FROM plans");
$plansStmt->execute();
$plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle the form submission for plan change or renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Renew Plan or Change Plan
    if (isset($_POST['user_id']) && isset($_POST['new_plan_id'])) {
        $userId = $_POST['user_id'];
        $newPlanId = $_POST['new_plan_id'];

        // Fetch the new plan details
        $newPlanStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $newPlanStmt->execute([$newPlanId]);
        $newPlan = $newPlanStmt->fetch(PDO::FETCH_ASSOC);

        // Calculate new remaining time based on the new plan
        $durationInSeconds = 0;
        if ($newPlan['days'] > 0) {
            $durationInSeconds += $newPlan['days'] * 86400; // 1 day = 86400 seconds
        }
        if ($newPlan['hours'] > 0) {
            $durationInSeconds += $newPlan['hours'] * 3600; // 1 hour = 3600 seconds
        }
        if ($newPlan['minutes'] > 0) {
            $durationInSeconds += $newPlan['minutes'] * 60; // 1 minute = 60 seconds
        }

        // Update the billing table with the new plan and remaining time
        $updateStmt = $db->prepare("UPDATE billing SET plan_id = ?, remaining_time = ? WHERE id = ?");
        $updateStmt->execute([$newPlanId, $durationInSeconds, $userId]);

        // Redirect back to the users page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle User Deletion
    if (isset($_POST['delete_user_id'])) {
        $deleteUserId = $_POST['delete_user_id'];

        // Delete the user from the billing table
        $deleteStmt = $db->prepare("DELETE FROM billing WHERE id = ?");
        $deleteStmt->execute([$deleteUserId]);

        // Redirect back to the users page
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
                // Fetch users for the current router
                $stmt = $db->prepare("SELECT b.id, b.router_id, b.mac, b.name, b.phone_number, b.remaining_time, b.created_at, r.name AS router_name, p.name AS plan_name, p.days, p.hours, p.minutes 
                                      FROM billing b
                                      JOIN routers r ON b.router_id = r.id
                                      JOIN plans p ON b.plan_id = p.id
                                      WHERE b.router_id = ?");
                $stmt->execute([$router['id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <!-- Display user table for the current router -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>User Name</th>
                                    <th>Phone Number</th>
                                    <th>Plan</th>
                                    <th>Remaining Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): 
                                    // Calculate remaining time in a human-readable format (DD:HH:MM:SS)
                                    $remainingTime = $user['remaining_time'];
                                    $remainingTimeFormatted = gmdate("d:H:i:s", $remainingTime);
                                    $isExpired = $remainingTime <= 0;
                                ?>
                                    <tr style="background-color: <?php echo $isExpired ? 'red' : ''; ?>">
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($user['plan_name']); ?></td>
                                        <td><?php echo $remainingTimeFormatted; ?></td>
                                        <td>
                                            <!-- Change Plan Form -->
                                            <form method="POST" class="d-inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="new_plan_id" class="form-control">
                                                    <?php foreach ($plans as $plan): ?>
                                                        <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-info btn-sm mt-2">Change Plan</button>
                                            </form>
                                            <!-- Delete User Form -->
                                            <form method="POST" class="d-inline-block mt-2">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete User</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>
    </section>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php
// End output buffering and flush the output
ob_end_flush();
?>
