<?php

header('Content-Type: application/json');

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$callback =
    $data['Body']['stkCallback'] ?? null;

if (!$callback) {

    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted'
    ]);

    exit;
}

$resultCode =
    (int)($callback['ResultCode'] ?? -1);

$resultDescription =
    $callback['ResultDesc'] ?? '';

$result = [
    'success' => $resultCode === 0,

    'result_code' =>
        $resultCode,

    'result_description' =>
        $resultDescription,

    'checkout_request_id' =>
        $callback['CheckoutRequestID'] ?? null,

    'merchant_request_id' =>
        $callback['MerchantRequestID'] ?? null,
];

if ($resultCode === 0) {

    $items =
        $callback['CallbackMetadata']['Item'] ?? [];

    $metadata = [];

    foreach ($items as $item) {

        if (isset($item['Name'])) {

            $metadata[$item['Name']] =
                $item['Value'] ?? null;
        }
    }

    $result['amount'] =
        $metadata['Amount'] ?? null;

    $result['receipt'] =
        $metadata['MpesaReceiptNumber'] ?? null;

    $result['transaction_date'] =
        $metadata['TransactionDate'] ?? null;

    $result['phone'] =
        $metadata['PhoneNumber'] ?? null;
}

echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Accepted'
]);
