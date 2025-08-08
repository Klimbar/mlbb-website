<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

/** @var Database $db */
$db = new Database();

// Check if this is a webhook (has status field) or redirect
$is_webhook = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']));

// Get order_id from appropriate source
$order_id = null;
if ($is_webhook) {
    $order_id = $_POST['order_id'] ?? null;
} else {
    $order_id = $_GET['order_id'] ?? $_REQUEST['order_id'] ?? null;
}

$webhook_utr = $_POST['utr'] ?? null;

if (!$order_id) {
    http_response_code(400);
    die("Invalid callback: Missing order_id.");
}

// Log webhook details if it's a webhook
if ($is_webhook) {
    $log_file = __DIR__ . '/../logs/webhook.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    $webhook_status = $_POST['status'] ?? 'N/A';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " | Webhook received | Order: " . $order_id . " | Status: " . $webhook_status . "\n", FILE_APPEND);
}

try {
    // Fetch our internal order details (without locking for now)
    $order = $db->query("SELECT * FROM orders WHERE order_id = ?", [$order_id])->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        echo "Order not found in our system.";
        exit;
    }
    $order_db_id = $order['id'];

    // If this is a user redirect and order is already completed, send them to details
    if (!$is_webhook && $order['order_status'] === 'completed') {
        header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
        exit;
    }

    // For webhooks, check if already processed to prevent double processing
    if ($is_webhook && $order['order_status'] === 'completed') {
        http_response_code(200);
        echo "OK: Order already processed.";
        exit;
    }

    // Verify payment status with Pay0 API
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Enhanced debugging
    error_log("=== PAY0 STATUS CHECK DEBUG ===");
    error_log("Order ID: " . $order_id);
    error_log("HTTP Code: " . $http_code);
    error_log("Raw response: " . $response);
    error_log("cURL Error: " . ($curl_error ?: 'none'));

    if ($curl_error) {
        error_log("Callback cURL Error for order $order_id: " . $curl_error);
        if ($is_webhook) {
            http_response_code(500);
            echo "Error communicating with payment gateway.";
            exit;
        } else {
            // For redirects, still show order page but log error
            header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
            exit;
        }
    }

    if ($http_code !== 200) {
        error_log("Pay0 API returned HTTP $http_code for order $order_id");
        if ($is_webhook) {
            http_response_code(500);
            echo "Payment gateway error.";
            exit;
        }
    }

    $gateway_result = json_decode($response, true);
    error_log("Decoded response: " . json_encode($gateway_result));

    if ($gateway_result === null) {
        error_log("Callback JSON Decode Error for order $order_id. HTTP: $http_code. Response: $response");
        if ($is_webhook) {
            http_response_code(500);
            echo "Invalid response from payment gateway.";
            exit;
        }
    }

    $gateway_txn_status = $gateway_result['result']['txnStatus'] ?? 'FAILED';
    error_log("Transaction status: " . $gateway_txn_status);
    error_log("============================");

    // Process payment based on status
    if (isset($gateway_result['status']) && $gateway_result['status'] === true && $gateway_txn_status === 'SUCCESS') {
        
        // Check for existing payment record to prevent duplicates
        $transaction_id = $gateway_result['result']['utr'] ?? $webhook_utr ?? 'N/A';
        $existing_payment = $db->query(
            "SELECT id FROM payments WHERE order_id = ? AND transaction_id = ? AND status = 'paid'",
            [$order_db_id, $transaction_id]
        )->fetch_assoc();

        if ($existing_payment) {
            error_log("Duplicate processing attempt for order ID: $order_db_id, Transaction: $transaction_id. Skipping.");
            if ($is_webhook) {
                http_response_code(200);
                echo "OK: Already processed.";
                exit;
            } else {
                header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
                exit;
            }
        }

        // Payment confirmed successful - record it
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, currency, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'INR', 'pay0', 'paid', ?)",
            [$order_db_id, $transaction_id, $order['amount'], json_encode($gateway_result)]
        );

        // FIXED: Update payment status to 'paid', keep order_status as 'pending' until fulfillment
        $db->query(
            "UPDATE orders SET payment_status = 'paid' WHERE id = ?",
            [$order_db_id]
        );

        error_log("DEBUG: Starting fulfillment for order " . $order_db_id);
        error_log("DEBUG: Order data: " . json_encode($order));

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

        error_log("DEBUG: Fulfillment params before sign: " . json_encode($fulfillment_params));
        $fulfillment_params['sign'] = generateSign($fulfillment_params, API_KEY);
        
        $fulfillment_response = callApi('/smilecoin/api/createorder', $fulfillment_params);
        error_log("DEBUG: Fulfillment response: " . json_encode($fulfillment_response));

        // FIXED: Update ONLY order_status based on fulfillment, keep payment_status as 'paid'
        if (isset($fulfillment_response['status']) && $fulfillment_response['status'] === 200) {
            $db->query(
                "UPDATE orders SET order_status = 'completed' WHERE id = ?",
                [$order_db_id]
            );
            error_log("Fulfillment success for order " . $order_db_id);
        } else {
            // FIXED: Keep payment_status as 'paid', set order_status to 'failed' for fulfillment failure
            $db->query(
                "UPDATE orders SET order_status = 'failed' WHERE id = ?",
                [$order_db_id]
            );
            error_log("CRITICAL: Fulfillment failed for paid order " . $order_db_id . ". Response: " . json_encode($fulfillment_response));
        }

    } elseif (isset($gateway_result['status']) && $gateway_result['status'] === true && $gateway_txn_status === 'PENDING') {
        
        // Payment still pending - only update payment status
        $db->query(
            "UPDATE orders SET payment_status = 'pending' WHERE id = ?",
            [$order_db_id]
        );
        error_log("Payment still pending for order " . $order_db_id);

    } else {
        
        // Payment failed - both should be failed
        $db->query(
            "UPDATE orders SET order_status = 'failed', payment_status = 'failed' WHERE id = ?",
            [$order_db_id]
        );
        
        $failed_amount = $gateway_result['result']['amount'] ?? $_POST['amount'] ?? $order['amount'];
        $failed_utr = $gateway_result['result']['utr'] ?? $webhook_utr ?? 'N/A';
        
        $db->query(
            "INSERT INTO payments (order_id, transaction_id, amount, currency, payment_gateway, status, raw_response) VALUES (?, ?, ?, 'INR', 'pay0', 'failed', ?)",
            [$order_db_id, $failed_utr, $failed_amount, json_encode($gateway_result)]
        );
        error_log("Payment failed for order " . $order_db_id);
    }

    // Response based on request type
    if ($is_webhook) {
        http_response_code(200);
        echo "OK: Callback processed.";
    } else {
        // Redirect user to order details
        header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
    }
    exit;

} catch (Exception $e) {
    error_log("Callback Exception for order $order_id: " . $e->getMessage());
    
    if ($is_webhook) {
        http_response_code(500);
        echo "Error: Internal server error.";
    } else {
        http_response_code(500);
        echo "An internal error occurred. Please contact support.";
    }
    exit;
}
?>