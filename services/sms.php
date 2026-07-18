<?php
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';
use KyPHP\KyPHP;

// Your StackVerify API key
define('STACKVERIFY_API_KEY', 'YOUR_API_KEY');

// Get raw JSON input from frontend
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['to'], $input['message'], $input['sender_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: to, message, sender_id']);
    exit;
}

$to = $input['to'];
$message = $input['message'];
$sender_id = $input['sender_id'];

try {
    $ky = new KyPHP();

    $response = $ky->post('https://stackverify.site/api/v1/sms/send')
                   ->header('Authorization', 'Bearer ' . STACKVERIFY_API_KEY)
                   ->header('Content-Type', 'application/json')
                   ->json([
                       'to' => $to,
                       'message' => $message,
                       'sender_id' => $sender_id
                   ])
                   ->sendJson();

    // Send response back to frontend
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
