<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/api_helpers.php';

// This script handles both user redirection (GET) and server-to-server webhooks (POST).
// The source of truth for the transaction status is always a direct call to the payment gateway's status check API.

try {
    // 1. Get the order_id from the request (works for both GET and POST)
    $order_id = $_REQUEST['order_id'] ?? null;

    // The webhook might send other data, but we only trust the order_id and verify everything else ourselves.
    $webhook_utr = $_POST['utr'] ?? null;

    if (!$order_id) {
        http_response_code(400);
        die("Invalid callback: Missing order_id.");
    }

    $db = new Database();

    // 2. Securely verify the payment status with the gateway's API
    // Note: In a real-world scenario, you should also verify the callback source (e.g., via a signature or IP check)
    // to prevent spoofing.
    $post_data = [
        'user_token' => PAYMENT_API_KEY,
        'order_id' => $order_id
    ];

    $ch = curl_init(PAYMENT_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // 3. Fetch our internal order details
    $order = $db->query("SELECT * FROM orders WHERE order_id = ?", [$order_id])->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        die("Order not found in our system.");
    }
    $order_db_id = $order['id'];

    // Prevent re-processing a completed or failed order (Idempotency)
    if ($order['status'] !== 'pending') {
        // Order already processed, just redirect.
        header("Location: /orders/details.php?id=$order_db_id");
        exit;
    }

    // 4. Process based on the verified status from the API call
    if ($result['status'] === true && $result['result']['txnStatus'] === 'SUCCESS') {
        // Payment is confirmed successful by the gateway.
        // Record the successful payment transaction.
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'paid', ?)",
            [$order_db_id, $result['result']['utr'] ?? $webhook_utr, $result['result']['amount'], json_encode($result)]
        );

        // Fulfill the order by calling the Smile One API
        $fulfillment_params = [
            'uid' => API_UID,
            'email' => API_EMAIL,
            'userid' => $order['player_id'],
            'zoneid' => $order['zone_id'],
            'product' => 'mobilelegends',
            'productid' => $order['product_id'],
            'time' => time()
        ];
        $fulfillment_params['sign'] = generateSign($fulfillment_params, API_KEY);

        $fulfillment_response = callApi('/smilecoin/api/createorder', $fulfillment_params);

        if (isset($fulfillment_response['status']) && $fulfillment_response['status'] === 200) {
            // Fulfillment successful
            $db->query(
                "UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
        } else {
            // Fulfillment failed, but payment was successful. Mark for manual review.
            $db->query(
                "UPDATE orders SET status = 'failed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
            // Log this critical error for admin attention
            error_log("Fulfillment failed for order ID $order_id. Smile One API response: " . json_encode($fulfillment_response));
        }

    } else {
        // Payment failed or was not confirmed by the gateway
        $db->query(
            "UPDATE orders SET status = 'failed', payment_status = 'failed' WHERE id = ?",
            [$order_db_id]
        );
        // Record the failed payment attempt for auditing. Use API response if available, otherwise webhook data.
        $failed_amount = $result['result']['amount'] ?? $_POST['amount'] ?? $order['amount'];
        $failed_utr = $result['result']['utr'] ?? $webhook_utr;
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'failed', ?)",
            [$order_db_id, $failed_utr, $failed_amount, json_encode($result)]
        );
    }

    // 5. Redirect user to their order details page
    header("Location: /orders/details.php?id=$order_db_id");
    exit;
} catch (Exception $e) {
    error_log("Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo "An internal error occurred.";
}
?>