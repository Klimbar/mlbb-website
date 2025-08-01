<?php
try {
    $log_file = __DIR__ . '/callback_debug.log';
    $log_message = "Timestamp: " . date('Y-m-d H:i:s') . " | Full GET: " . print_r($_GET, true) . " | Full POST: " . print_r($_POST, true) . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);

    // 1. Get the order_id from the request (works for both GET and POST)
    $order_id = $_REQUEST['order_id'] ?? null;
    file_put_contents($log_file, "Order ID: " . $order_id . "\n", FILE_APPEND);

    // The webhook might send other data, but we only trust the order_id and verify everything else ourselves.

    $webhook_utr = $_POST['utr'] ?? null;

    if (!$order_id) {
        http_response_code(400);
        die("Invalid callback: Missing order_id.");
    }

    $db = new Database();

    // 2. Securely verify the payment status with the gateway's API
    $is_test_mode = isset($_GET['test']) && $_GET['test'] === 'true';

    if ($is_test_mode) {
        // --- TEST MODE ---
        // In test mode, we skip the live API call and simulate a successful response.
        $result = [
            'status' => true,
            'result' => [
                'txnStatus' => 'SUCCESS',
                'utr' => 'test-transaction-' . time(), // Generate a unique test transaction ID
                'amount' => null // Amount will be fetched from the order later
            ]
        ];
        file_put_contents($log_file, "Test Mode Result: " . print_r($result, true) . "\n", FILE_APPEND);
    } else {
        // --- LIVE MODE ---
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
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents($log_file, "cURL Response: " . $response . "\n", FILE_APPEND);
        file_put_contents($log_file, "cURL Error: " . $curl_error . "\n", FILE_APPEND);
        file_put_contents($log_file, "HTTP Code: " . $http_code . "\n", FILE_APPEND);

        $result = json_decode($response, true);
        file_put_contents($log_file, "Decoded Result: " . print_r($result, true) . "\n", FILE_APPEND);
    }

    // 3. Fetch our internal order details
    $order = $db->query("SELECT * FROM orders WHERE order_id = ?", [$order_id])->fetch_assoc();
    file_put_contents($log_file, "Fetched Order: " . print_r($order, true) . "\n", FILE_APPEND);

    if (!$order) {
        http_response_code(404);
        die("Order not found in our system.");
    }
    $order_db_id = $order['id'];

    // Prevent re-processing a completed order (Idempotency)
    // We only stop if the order is already 'completed'. A 'failed' or 'pending' order
    // could potentially be updated to 'completed' by a late-arriving success notification.
    if ($order['order_status'] === 'completed') {
        header("Location: " . BASE_URL . "/orders/details.php?id=$order_db_id");
        exit;
    }

    // 4. Process based on the verified status from the API call
    if ($result['status'] === true && $result['result']['txnStatus'] === 'SUCCESS') {
        // Payment is confirmed successful by the gateway.
        // Record the successful payment transaction using the amount from our database for consistency.
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'paid', ?)",
            [$order_db_id, $result['result']['utr'] ?? $webhook_utr, $order['amount'], json_encode($result)]
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
        file_put_contents($log_file, "Fulfillment Response: " . print_r($fulfillment_response, true) . "\n", FILE_APPEND);

        if (isset($fulfillment_response['status']) && $fulfillment_response['status'] === 200) {
            // Fulfillment successful
            $db->query(
                "UPDATE orders SET order_status = 'completed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
        } else {
            // Fulfillment failed, but payment was successful. Mark for manual review.
            $db->query(
                "UPDATE orders SET order_status = 'failed', payment_status = 'paid' WHERE id = ?",
                [$order_db_id]
            );
            // Log this critical error for admin attention
            file_put_contents($log_file, "CRITICAL: Fulfillment failed for order " . $order_db_id . ". Response: " . print_r($fulfillment_response, true) . "\n", FILE_APPEND);
        }

    } elseif ($result['status'] === true && $result['result']['txnStatus'] === 'PENDING') {
        // Payment is pending, do not change order_status from pending
        $db->query(
            "UPDATE orders SET payment_status = 'pending' WHERE id = ?",
            [$order_db_id]
        );
        file_put_contents($log_file, "Payment Pending for order " . $order_db_id . ". Result: " . print_r($result, true) . "\n", FILE_APPEND);

    } else {
        // Payment failed or was not confirmed by the gateway
        $db->query(
            "UPDATE orders SET order_status = 'failed', payment_status = 'failed' WHERE id = ?",
            [$order_db_id]
        );
        // Record the failed payment attempt for auditing. Use API response if available, otherwise webhook data.
        $failed_amount = $result['result']['amount'] ?? $_POST['amount'] ?? $order['amount'];
        $failed_utr = $result['result']['utr'] ?? $webhook_utr;
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'failed', ?)",
            [$order_db_id, $failed_utr, $failed_amount, json_encode($result)]
        );
        file_put_contents($log_file, "Payment Failed for order " . $order_db_id . ". Result: " . print_r($result, true) . "\n", FILE_APPEND);
    }

    // 5. Redirect user to their order details page
    header("Location: " . BASE_URL . "/orders/details.php?id=$order_db_id");
    exit;
} catch (Exception $e) {
    file_put_contents($log_file, "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "An internal error occurred.";
}
?>