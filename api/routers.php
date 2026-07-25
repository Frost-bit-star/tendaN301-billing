<?php
header('Content-Type: application/json');

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// Require router ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Router ID is required"
    ]);
    exit;
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("
    SELECT id, name, ip, port, last_run, last_mode, last_sync
    FROM routers
    WHERE id = :id
");

$stmt->execute([':id' => $id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Router not found"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => $router
]);
