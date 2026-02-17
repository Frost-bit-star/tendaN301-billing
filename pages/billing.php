<?php
ob_start();

include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
require_once __DIR__ . '/../auth/config.php';

// ---------------------------
// Connect to SQLite Database
// ---------------------------
$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------------
// Fetch all routers
// ---------------------------
$routers = $db->query("SELECT id, name FROM routers")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center text-danger">Users with No Internet Access</h1>

            <?php foreach ($routers as $router): ?>
                <h2><?php echo htmlspecialchars($router['name']); ?></h2>

                <?php
                // Simplified query: Fetch users with internet_access = 0 for debugging
                $stmt = $db->prepare("
                    SELECT u.hostname, u.mac
                    FROM users u
                    WHERE u.router_id = ? AND u.internet_access = 0
                    ORDER BY u.hostname ASC
                ");
                $stmt->execute([$router['id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Debugging: Print out the query and fetched data
                echo '<pre>';
                print_r($users);
                echo '</pre>';
                ?>

                <?php if ($users): ?>
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Hostname</th>
                                        <th>MAC Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr style="background-color: #f8d7da;">
                                            <td><?php echo htmlspecialchars($user['hostname']); ?></td>
                                            <td><?php echo htmlspecialchars($user['mac']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No users with no internet access for this router.</p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
