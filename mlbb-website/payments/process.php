<?php
require_once __DIR__ . '/../bootstrap.php'; // Include bootstrap to handle sessions

// Clear any previous output
ob_clean();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php'; // We'll need this for API calls

header('Content-Type: application/json');

// API endpoints require a different authentication check than full pages.
// Instead of redirecting, we return a JSON error that the frontend can handle.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Authentication required. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    try {
        // Instantiate the database connection once.
        $db = new Database();

        $user_id = $_SESSION['user_id'];
        $product_id = $_POST['productid'] ?? '';
        $player_id = $_POST['userid'] ?? '';
        $zone_id = $_POST['zoneid'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';

        // --- SERVER-SIDE PLAYER AND PRICE VERIFICATION ---
        // 1. Verify the player and get any price adjustments. This is crucial.
        $verify_params = [
            'uid' => API_UID,
            'email' => API_EMAIL,
            'userid' => $player_id,
            'zoneid' => $zone_id,
            'product' => 'mobilelegends',
            'productid' => $product_id,
            'time' => time()
        ];
        $verify_params['sign'] = generateSign($verify_params, API_KEY);
        $verify_response = callApi('/smilecoin/api/getrole', $verify_params);

        if ($verify_response === null) {
            throw new Exception('Could not connect to the game provider to verify the player. Please try again later.');
        }

        if (!isset($verify_response['status']) || $verify_response['status'] !== 200) {
            error_log("Player verification failed: " . ($verify_response['message'] ?? 'Unknown API error'));
            throw new Exception('Player verification failed: ' . ($verify_response['message'] ?? 'Unknown API error'));
        }
        $price_multiplier = (float)($verify_response['change_price'] ?? 1);

        // --- SERVER-SIDE PRICE VERIFICATION ---
        // 2. Fetch the selected product's selling price and name from our local database
        $selected_product_db = $db->query("SELECT name, selling_price FROM products WHERE product_id = ?", [$product_id])->fetch_assoc();

        if ($selected_product_db === null) {
            error_log("Product not found in local database: " . $product_id);
            throw new Exception('Product not found in our system.');
        }

        // 3. Use the selling price from our database
        $base_price = (float)$selected_product_db['selling_price'];
        $product_name = $selected_product_db['name'];
        error_log("DEBUG: base_price = " . $base_price . ", price_multiplier = " . $price_multiplier);
        $final_price = $base_price * $price_multiplier; // Calculate the final price
        $formatted_final_price = number_format($final_price, 2, '.', ''); // Format to a string with 2 decimal places

        // Use a transaction to ensure atomicity. If any step fails, the entire operation is rolled back.
        $db->begin_transaction();

        // Generate a unique order ID *before* inserting.
        // Using a combination of a prefix, timestamp, and a random element for uniqueness.
        $order_id = 'SD' . time() . bin2hex(random_bytes(4));

        // Create the order in the database with the generated order_id
        $db->query(
            "INSERT INTO orders (user_id, player_id, zone_id, product_id, product_name, amount, order_status, order_id) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
            [$user_id, $player_id, $zone_id, $product_id, $product_name, $formatted_final_price, $order_id]
        );
        $internal_order_id = $db->getLastInsertId();

        // Prepare payment gateway request
        $post_data = [
            'user_token' => PAYMENT_API_KEY,
            'amount' => $formatted_final_price, // Use the formatted string amount
            'order_id' => $order_id,
            'redirect_url' => PAYMENT_REDIRECT_URL . '?order_id=' . $order_id,
            'customer_mobile' => '', // Add customer mobile if available
            'customer_name' => $_SESSION['username'],
            'remark1' => 'MLBB Diamonds',
            'remark2' => "Player: $player_id, Zone: $zone_id"
        ];

        // Call payment gateway
        $ch = curl_init(PAYMENT_GATEWAY_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result === null) {
            error_log("Invalid response from payment gateway. HTTP Code: " . $http_code . " Response: " . $response);
            $db->rollback(); // Rollback transaction on failure
            throw new Exception('Invalid response from payment gateway.');
        }

        if (isset($result['status']) && $result['status'] === true && isset($result['result']['payment_url'])) {
            // Update order with payment URL
            $db->query(
                "UPDATE orders SET payment_url = ? WHERE id = ?",
                [$result['result']['payment_url'], $internal_order_id]
            );
            $db->commit(); // Commit transaction on success

            echo json_encode([
                'status' => 'success',
                'payment_url' => $result['result']['payment_url'],
                'order_id' => $order_id
            ]);
            exit; // Terminate script after sending success response
        } else {
            error_log("Payment gateway error: " . ($result['message'] ?? 'Unknown error'));
            $db->rollback(); // Rollback transaction on failure
            throw new Exception($result['message'] ?? 'Payment gateway error');
        }
    } catch (Exception $e) {
        http_response_code(500);
        // Ensure rollback on any exception if a transaction was started
        if (isset($db) && $db->inTransaction()) $db->rollback();
        error_log("Process Payment Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred. Please try again later.']);
        exit; // Terminate script after sending error response
    }
}
?>