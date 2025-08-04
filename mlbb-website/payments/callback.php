<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

try {
    // 1. Get the order_id from the request (works for both GET and POST)
    // Also, determine if this is a server-to-server webhook call or a user redirect.
    // We'll assume a webhook is a POST request containing the 'utr' (Unique Transaction Reference).
    $order_id = $_REQUEST['order_id'] ?? null;
    $webhook_utr = $_POST['utr'] ?? null;
    $is_webhook = ($_SERVER['REQUEST_METHOD'] === 'POST' && $webhook_utr !== null);

    if (!$order_id) {
        http_response_code(400);
        die("Invalid callback: Missing order_id.");
    }


    $db = new Database();

    // 2. Securely verify the payment status with the gateway's API
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
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error) {
        error_log("Callback cURL Error for order $order_id: " . $curl_error);
        http_response_code(500);
        die("Error communicating with payment gateway.");
    }

    $result = json_decode($response, true);

    if ($result === null) {
        error_log("Callback JSON Decode Error for order $order_id. HTTP: $http_code. Response: $response");
        http_response_code(500);
        die("Invalid response from payment gateway.");
    }

    // 3. Fetch our internal order details
    $order = $db->query("SELECT * FROM orders WHERE order_id = ?", [$order_id])->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        die("Order not found in our system.");
    }
    $order_db_id = $order['id'];

    // Prevent re-processing a completed order
    if ($order['order_status'] === 'completed') {
        header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
        exit;
    }

    // 4. Process based on the verified status from the API call
    if (isset($result['status']) && $result['status'] === true && isset($result['result']['txnStatus']) && $result['result']['txnStatus'] === 'SUCCESS') {
        // Payment is confirmed successful.
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'paid', ?)",
            [$order_db_id, $result['result']['utr'] ?? $webhook_utr, $order['amount'], json_encode($result)]
        );

        // Fulfill the order
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
            $db->query(
                "UPDATE orders SET order_status = 'completed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
        } else {
            $db->query(
                "UPDATE orders SET order_status = 'failed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
            error_log("CRITICAL: Fulfillment failed for order " . $order_db_id . ". Response: " . json_encode($fulfillment_response));
        }

    } elseif (isset($result['status']) && $result['status'] === true && isset($result['result']['txnStatus']) && $result['result']['txnStatus'] === 'PENDING') {
        $db->query(
            "UPDATE orders SET payment_status = 'pending' WHERE id = ?",
            [$order_db_id]
        );

    } else {
        // Payment failed
        $db->query(
            "UPDATE orders SET order_status = 'failed', payment_status = 'failed' WHERE id = ?",
            [$order_db_id]
        );
        $failed_amount = $result['result']['amount'] ?? $_POST['amount'] ?? $order['amount'];
        $failed_utr = $result['result']['utr'] ?? $webhook_utr;
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'failed', ?)",
            [$order_db_id, $failed_utr, $failed_amount, json_encode($result)]
        );
    }

    // 5. Respond appropriately
    if ($is_webhook) {
        // Acknowledge the webhook call successfully
        http_response_code(200);
        echo "OK: Callback processed.";
    } else {
        // Redirect the user to their order details page
        header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
    }
    exit; // Always exit after responding

} catch (Exception $e) {
    error_log("Callback Exception: " . $e->getMessage());
    http_response_code(500);
    echo "An internal error occurred. Please contact support.";
}
?>