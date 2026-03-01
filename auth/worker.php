<?php
header('Content-Type: application/json');

try {
    // -----------------------
    // DATABASE CONNECTION
    // -----------------------
    $db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if we are being asked to get the expired users count via curl
    if (isset($_GET['get_expired_count']) && $_GET['get_expired_count'] == 'true') {
        // Fetch the number of expired users
        $stmt = $db->prepare("
            SELECT COUNT(*) as expired_count
            FROM users
            WHERE internet_access = 0
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the count as JSON
        echo json_encode([
            'success' => true,
            'expired_count' => $result['expired_count']
        ]);
        exit;  // Stop further script execution
    }

    // -----------------------
    // SELECT USERS WHOSE TIME HAS EXPIRED
    // -----------------------
    $stmt = $db->prepare("
        SELECT * FROM users 
        JOIN billing ON users.mac = billing.mac AND users.router_id = billing.router_id
        WHERE billing.end_at <= strftime('%s','now') AND users.internet_access = 1
    ");
    $stmt->execute();
    $expiredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($expiredUsers)) {
        // -----------------------
        // UPDATE EXPIRED USERS (Mark as "depleted" by setting internet_access = 0)
        // -----------------------
        foreach ($expiredUsers as $user) {
            $updateStmt = $db->prepare("
                UPDATE users
                SET internet_access = 0
                WHERE id = ?
            ");
            $updateStmt->execute([$user['id']]);
        }

        // -----------------------
        // LOG SUCCESS FOR THIS RUN
        // -----------------------
        echo json_encode([
            'success' => true,
            'message' => count($expiredUsers) . ' users marked as depleted',
            'expired_users' => $expiredUsers
        ]);
    } else {
        // No expired users, log that
        echo json_encode(['success' => true, 'message' => 'No expired users found']);
    }

    // -----------------------
    // WAIT FOR 1 MINUTE BEFORE NEXT CHECK
    // -----------------------
    sleep(60);  // Sleep for 1 minute (adjust as needed)

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
