<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

/** @var Database $db */
$db = new Database();

// We will manually control the response code and output.
// This prevents any premature output from interfering with the logic.

$order_id = $_REQUEST['order_id'] ?? null;
$webhook_utr = $_POST['utr'] ?? null;
$is_webhook = ($_SERVER['REQUEST_METHOD'] === 'POST' && $webhook_utr !== null);

if (!$order_id) {
    http_response_code(400);
    die("Invalid callback: Missing order_id.");
}

// Use a transaction for the entire process to ensure atomicity.
// This prevents partial updates if something goes wrong.
$db->begin_transaction();

try {
    // 1. Securely verify the payment status with the gateway's API first.
    // This is crucial to ensure the callback isn't being spoofed.
    $status_check_data = [
        'user_token' => PAYMENT_API_KEY,
        'order_id' => $order_id
    ];

    $ch = curl_init(PAYMENT_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($status_check_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("cURL Error communicating with payment gateway: " . $curl_error);
    }

    $gateway_result = json_decode($response, true);

    if ($gateway_result === null || !isset($gateway_result['status'])) {
        throw new Exception("Invalid response from payment gateway.");
    }

    // 2. Find the order and LOCK THE ROW to prevent race conditions.
    // If another webhook for the same order arrives, it will wait here until the first one is finished.
    $order = $db->query("SELECT * FROM orders WHERE order_id = ? FOR UPDATE", [$order_id])->fetch_assoc();

    if (!$order) {
        // Order doesn't exist in our system. Acknowledge to stop retries.
        http_response_code(404);
        echo "OK: Order not found.";
        $db->commit(); // Commit to release any potential locks.
        exit;
    }
    $order_db_id = $order['id'];

    // 3. IDEMPOTENCY CHECK: This is the critical part to prevent duplicate processing.
    if ($order['order_status'] === 'completed') {
        // The order is already fulfilled. This is a duplicate webhook.
        $db->commit(); // Finalize the transaction to release the lock.

        if ($is_webhook) {
            // Acknowledge with a 200 OK to stop the gateway from sending more notifications.
            http_response_code(200);
            echo "OK: Order already processed.";
        } else {
            // This is a user being redirected. Send them to their completed order's details page.
            header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
        }
        exit; // Stop execution.
    }

    // 4. Process based on the verified status from the API call
    $gateway_txn_status = $gateway_result['result']['txnStatus'] ?? 'FAILED';

    if ($gateway_result['status'] === true && $gateway_txn_status === 'SUCCESS') {
        // Payment is confirmed successful. Fulfill the order.
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
            // Fulfillment successful, update order status to 'completed'.
            $db->query(
                "UPDATE orders SET order_status = 'completed', payment_status = 'paid', updated_at = NOW() WHERE id = ?",
                [$order_db_id]
            );
        } else {
            // Fulfillment failed, but payment was successful. Mark as 'failed' for manual review.
            $db->query(
                "UPDATE orders SET order_status = 'failed', payment_status = 'paid', updated_at = NOW() WHERE id = ?",
                [$order_db_id]
            );
            error_log("CRITICAL: Fulfillment failed for paid order " . $order_db_id . ". Response: " . json_encode($fulfillment_response));
        }

        // Log the successful payment transaction *after* attempting fulfillment.
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'paid', ?)",
            [$order_db_id, $gateway_result['result']['utr'] ?? $webhook_utr, $order['amount'], json_encode($gateway_result)]
        );

    } elseif ($gateway_result['status'] === true && $gateway_txn_status === 'PENDING') {
        // Payment is still pending, just update our status. No fulfillment yet.
        $db->query(
            "UPDATE orders SET payment_status = 'pending', updated_at = NOW() WHERE id = ?",
            [$order_db_id]
        );
    } else {
        // Payment failed according to the gateway.
        $db->query(
            "UPDATE orders SET order_status = 'failed', payment_status = 'failed', updated_at = NOW() WHERE id = ?",
            [$order_db_id]
        );
        // Log the failed payment attempt.
        $failed_amount = $gateway_result['result']['amount'] ?? $order['amount'];
        $failed_utr = $gateway_result['result']['utr'] ?? $webhook_utr;
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'pay0', 'failed', ?)",
            [$order_db_id, $failed_utr, $failed_amount, json_encode($gateway_result)]
        );
    }

    // 5. If we've reached here without errors, commit all database changes.
    $db->commit();

    // 6. Respond appropriately to the original caller.
    if ($is_webhook) {
        http_response_code(200);
        echo "OK: Callback processed.";
    } else {
        header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
    }
    exit;

} catch (Exception $e) {
    // An error occurred. Roll back any partial database changes.
    if ($db->inTransaction()) {
        $db->rollback();
    }
    error_log("Callback Exception for order $order_id: " . $e->getMessage());

    // Respond with a server error. This tells the payment gateway to retry the webhook later.
    http_response_code(500);
    // For a user, you might want to show a more friendly error page.
    if ($is_webhook) {
        echo "Error: Internal server error.";
    } else {
        // You could redirect to an error page.
        die("An internal error occurred. Please contact support and reference order ID: " . htmlspecialchars($order_id));
    }
}
