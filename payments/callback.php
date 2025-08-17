<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

// =================================================================
// Main Entry Point
// =================================================================

$is_webhook = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']));
$order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    die("Invalid callback: Missing order_id.");
}

if ($is_webhook) {
    handle_webhook($order_id);
} else {
    handle_redirect($order_id);
}

// =================================================================
// Core Logic Functions
// =================================================================

/**
 * Handles incoming webhooks from the payment gateway.
 */
function handle_webhook(string $order_id): void
{
    $webhook_id = $_POST['webhook_id'] ?? 'wh_' . time() . '_' . bin2hex(random_bytes(4));
    $webhook_status = $_POST['status'] ?? 'N/A';
    $webhook_utr = $_POST['utr'] ?? null;

    log_message("Webhook received for Order: $order_id | Status: $webhook_status | UTR: $webhook_utr");

    // Prevent duplicate webhook processing
    if (is_webhook_processed($webhook_id, $order_id)) {
        log_message("Webhook ID $webhook_id or a successful payment for order $order_id has already been processed. Skipping.");
        http_response_code(200);
        echo "OK: Webhook already processed.";
        exit;
    }

    process_payment_confirmation($order_id, $webhook_id, $webhook_utr, true);

    http_response_code(200);
    echo "OK: Webhook processed.";
}

/**
 * Handles the user being redirected back from the payment gateway.
 */
function handle_redirect(string $order_id): void
{
    log_message("User redirect for Order: $order_id");
    $order_db_id = get_order_db_id($order_id);

    if (!$order_db_id) {
        http_response_code(404);
        echo "Order not found.";
        exit;
    }

    // Process payment confirmation if the order is not yet completed
    $order = get_order_by_db_id($order_db_id);
    if ($order && $order['order_status'] === 'pending') {
    process_payment_confirmation($order_id, null, null, false);
}

    // Always redirect to the details page
    header("Location: " . BASE_URL . "/orders/details?id=$order_db_id");
    exit;
}

/**
 * The main function to process the payment confirmation.
 * This is where the locking, verification, and fulfillment happens.
 */
function process_payment_confirmation(string $order_id, ?string $webhook_id, ?string $webhook_utr, bool $is_webhook): void
{
    $db = new Database();
    try {
        $db->begin_transaction();

        // 1. Lock the order to prevent race conditions
        $order = $db->query("SELECT * FROM orders WHERE order_id = ? FOR UPDATE", [$order_id])->fetch_assoc();

        if (!$order) {
            throw new Exception("Order not found in our system.");
        }
        $order_db_id = $order['id'];

        // 2. Check if already completed
        if ($order['order_status'] === 'completed') {
            log_message("Order $order_id is already completed. Skipping processing.");
            $db->commit();
            return;
        }

        // 3. Set status to 'processing'
        update_order_status($db, $order_db_id, 'processing', $order['order_status'], 'paid', $order['payment_status'], 'Starting payment verification.');
        $db->query("UPDATE orders SET processing_started_at = NOW() WHERE id = ?", [$order_db_id]);

        // 4. Verify payment with the gateway
        $gateway_result = verify_payment_with_gateway($order_id);
        $gateway_txn_status = $gateway_result['result']['txnStatus'] ?? 'FAILED';
        $transaction_id = $gateway_result['result']['utr'] ?? $webhook_utr ?? 'N/A';

        if ($gateway_txn_status === 'SUCCESS') {
            // 5. Process successful payment
            process_successful_payment($db, $order, $transaction_id, $webhook_id, $gateway_result);
        } else {
            // 6. Handle failed or pending payment
            update_order_status($db, $order_db_id, 'failed', 'processing', 'failed', 'paid', "Payment status from gateway: $gateway_txn_status");
            log_message("Payment for order $order_id failed or is pending. Status: $gateway_txn_status");
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        log_message("CRITICAL ERROR processing order $order_id: " . $e->getMessage(), 'error');
        if ($is_webhook) {
            http_response_code(500);
            echo "Error: Internal server error.";
            exit;
        }
    }
}

/**
 * Handles the logic for a successful payment.
 */
function process_successful_payment(Database $db, array $order, string $transaction_id, ?string $webhook_id, array $gateway_result): void
{
    $order_db_id = $order['id'];

    // Check for duplicate payment record
    $existing_payment = $db->query("SELECT id FROM payments WHERE transaction_id = ? AND status = 'paid'", [$transaction_id])->fetch_assoc();
    if ($existing_payment) {
        log_message("Duplicate payment for transaction $transaction_id already recorded. Skipping.");
        return;
    }

    // Record the successful payment
    $db->query(
        "INSERT INTO payments (order_id, transaction_id, amount, currency, payment_gateway, status, raw_response, webhook_id) VALUES (?, ?, ?, 'INR', 'pay0', 'paid', ?, ?)",
        [$order_db_id, $transaction_id, $order['amount'], json_encode($gateway_result), $webhook_id]
    );
    update_order_status($db, $order_db_id, 'processing', 'processing', 'paid', 'paid', 'Payment recorded successfully.');

    // Fulfill the order
    $fulfillment_response = fulfill_order($order);

    if (isset($fulfillment_response['status']) && $fulfillment_response['status'] === 200) {
        update_order_status($db, $order_db_id, 'completed', 'processing', 'paid', 'paid', 'Fulfillment successful.');
        log_message("Fulfillment success for order " . $order['order_id']);
    } else {
        $response_json = json_encode($fulfillment_response);
        $error_message = "CRITICAL: Fulfillment failed. Response: " . $response_json;
        update_order_status($db, $order_db_id, 'failed', 'processing', 'paid', 'paid', $error_message);
        log_message("CRITICAL: Fulfillment failed for paid order " . $order['order_id'] . ". Response: " . $response_json, 'error');
    }
}


// =================================================================
// Helper & Database Functions
// =================================================================

/**
 * Verifies the payment status with the payment gateway.
 */
function verify_payment_with_gateway(string $order_id): array
{
    $status_check_data = ['user_token' => PAYMENT_API_KEY, 'order_id' => $order_id];
    $ch = curl_init(PAYMENT_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($status_check_data));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        throw new Exception("Failed to verify payment with gateway. HTTP: $http_code");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from payment gateway.");
    }
    return $result;
}

/**
 * Calls the external API to fulfill the order.
 */
function fulfill_order(array $order): array
{
    $params = [
        'uid' => API_UID,
        'email' => API_EMAIL,
        'userid' => $order['player_id'],
        'zoneid' => $order['zone_id'],
        'product' => 'mobilelegends',
        'productid' => $order['product_id'],
        'time' => time()
    ];
    $params['sign'] = generateSign($params, API_KEY);
    return callApi('/smilecoin/api/createorder', $params);
}



function get_order_db_id(string $order_id): ?int
{
    $db = new Database();
    $result = $db->query("SELECT id FROM orders WHERE order_id = ?", [$order_id])->fetch_assoc();
    return $result['id'] ?? null;
}

function get_order_by_db_id(int $order_db_id): ?array
{
    $db = new Database();
    return $db->query("SELECT * FROM orders WHERE id = ?", [$order_db_id])->fetch_assoc();
}

function is_webhook_processed(string $webhook_id, string $order_id): bool
{
    $db = new Database();
    
    // Check for the webhook ID
    $result = $db->query("SELECT id FROM payments WHERE webhook_id = ?", [$webhook_id])->fetch_assoc();
    if ($result !== null) {
        return true;
    }

    // Check for a successful payment for the order
    $order_db_id = get_order_db_id($order_id);
    if ($order_db_id) {
        $payment = $db->query("SELECT id FROM payments WHERE order_id = ? AND status = 'paid'", [$order_db_id])->fetch_assoc();
        if ($payment !== null) {
            return true;
        }
    }

    return false;
}

function log_message(string $message, string $level = 'info'): void
{
    $log_file = __DIR__ . '/../logs/callback.log';
    $formatted_message = date('Y-m-d H:i:s') . " | " . strtoupper($level) . " | " . $message . "\n";
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

?>