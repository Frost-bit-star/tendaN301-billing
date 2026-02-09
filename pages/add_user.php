<?php
// Start output buffering
ob_start();

include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';

// Retrieve URL parameters (these represent the selected user)
$routerId = isset($_GET['router_id']) ? $_GET['router_id'] : '';
$macAddress = isset($_GET['paid_mac']) ? $_GET['paid_mac'] : '';
$planId = isset($_GET['plan_id']) ? $_GET['plan_id'] : '';

// Handle validation
if (empty($routerId) || empty($macAddress) || empty($planId)) {
    echo "<p class='text-danger'>Missing required parameters. Please check the URL and try again.</p>";
    exit;
}

// Connect to SQLite Database
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create the 'billing' table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS billing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    router_id INTEGER NOT NULL,
    mac TEXT NOT NULL,
    plan_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    phone_number TEXT NOT NULL,
    remaining_time INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id),
    UNIQUE(mac, router_id)
)");

// Fetch router details from the database
$routerStmt = $db->prepare("SELECT name FROM routers WHERE id = ?");
$routerStmt->execute([$routerId]);
$router = $routerStmt->fetch(PDO::FETCH_ASSOC);

// Fetch plan details from the database
$planStmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
$planStmt->execute([$planId]);
$plan = $planStmt->fetch(PDO::FETCH_ASSOC);

// Check if router and plan exist
if (!$router || !$plan) {
    echo "<p class='text-danger'>Invalid router or plan data. Please check the URL and try again.</p>";
    exit;
}

// Calculate plan duration in seconds
$durationInSeconds = ($plan['days'] ?? 0) * 86400 + ($plan['hours'] ?? 0) * 3600 + ($plan['minutes'] ?? 0) * 60;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phoneNumber = $_POST['phone_number'];

    try {
        // 1️⃣ Insert into billing table
        $stmt = $db->prepare("INSERT INTO billing (router_id, mac, plan_id, name, phone_number, remaining_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$routerId, $macAddress, $planId, $name, $phoneNumber, $durationInSeconds]);

        // 2️⃣ Update the users table to mark the user as paid (internet_access = 1)
        $stmt2 = $db->prepare("UPDATE users SET internet_access = 1 WHERE mac = ? AND router_id = ?");
        $stmt2->execute([$macAddress, $routerId]);

        // Optional: if user doesn't exist in 'users' yet, insert them with internet_access = 1
        if ($stmt2->rowCount() === 0) {
            $stmt3 = $db->prepare("
                INSERT INTO users (hostname, ip, mac, router_id, internet_access, connected_at)
                VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
            ");
            $stmt3->execute([
                'unknown',        // hostname
                '',               // ip
                $macAddress,
                $routerId
            ]);
        }

        // Redirect back to dashboard after successful insert
        header("Location: /dashboard");
        exit;

    } catch (PDOException $e) {
        echo "<p class='text-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">Add User</h1>

            <!-- Router, MAC Address, and Plan Information -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h4>Router Name: <?php echo htmlspecialchars($router['name']); ?></h4>
                    <p><strong>MAC Address: </strong><?php echo htmlspecialchars($macAddress); ?></p>
                    <p><strong>Plan Name: </strong><?php echo htmlspecialchars($plan['name']); ?></p>
                    <p><strong>Plan Duration: </strong>
                        <?php
                            echo ($plan['days'] ?? 0) . " days " . ($plan['hours'] ?? 0) . " hours " . ($plan['minutes'] ?? 0) . " minutes";
                        ?>
                    </p>
                </div>
            </div>

            <!-- Form to add user -->
            <div class="card shadow">
                <div class="card-body">
                    <form action="" method="POST">
                        <!-- Hidden inputs for router, mac, and plan details -->
                        <input type="hidden" name="router_id" value="<?php echo htmlspecialchars($routerId); ?>">
                        <input type="hidden" name="paid_mac" value="<?php echo htmlspecialchars($macAddress); ?>">
                        <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($planId); ?>">
                        <input type="hidden" name="remaining_time" value="<?php echo $durationInSeconds; ?>">

                        <!-- User details -->
                        <div class="form-group">
                            <label for="userName">Name</label>
                            <input type="text" name="name" id="userName" class="form-control" placeholder="Enter Name" required>
                        </div>

                        <div class="form-group">
                            <label for="userPhone">Phone Number</label>
                            <input type="text" name="phone_number" id="userPhone" class="form-control" placeholder="Enter Phone Number" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Save User</button>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php
// End output buffering and flush the output
ob_end_flush();
?>
