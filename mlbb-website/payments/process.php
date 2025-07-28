<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';
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
    try {
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

        if (!isset($verify_response['status']) || $verify_response['status'] !== 200) {
            throw new Exception('Player verification failed: ' . ($verify_response['message'] ?? 'Unknown API error'));
        }
        $price_multiplier = (float)($verify_response['change_price'] ?? 1);

        // --- SERVER-SIDE PRICE VERIFICATION ---
        // 2. Fetch product list from the trusted API source
        $product_params = ['uid' => API_UID, 'email' => API_EMAIL, 'product' => 'mobilelegends', 'time' => time()];
        $product_params['sign'] = generateSign($product_params, API_KEY);
        $products_response = callApi('/smilecoin/api/productlist', $product_params);
        
        if ($products_response === null || !isset($products_response['data']['product'])) {
            throw new Exception('Could not retrieve product list from provider.');
        }
        $products = $products_response['data']['product'];

        // 3. Find the selected product to get its base price and name
        $selected_product = null;
        foreach ($products as $product) {
            if ($product['id'] == $product_id) { // API uses 'id' for productid
                $selected_product = $product;
                break;
            }
        }

        if ($selected_product === null) {
            throw new Exception('Invalid product ID.');
        }

        // 4. Use the trusted base price, apply the multiplier, and get the name
        $base_price = (float)$selected_product['price'];
        $product_name = $selected_product['spu']; // API uses 'spu' for product name
        $final_price = $base_price * $price_multiplier; // Calculate the final price
        $formatted_final_price = number_format($final_price, 2, '.', ''); // Format to a string with 2 decimal places

        // Create order in database
        $db = new Database();
        $order_id = 'MLBB' . time() . rand(100, 999);
        $db->query(
            "INSERT INTO orders (user_id, order_id, player_id, zone_id, product_id, product_name, amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$user_id, $order_id, $player_id, $zone_id, $product_id, $product_name, $formatted_final_price]
        );
        $internal_order_id = $db->getLastInsertId();

        // Prepare payment gateway request
        $post_data = [
            'user_token' => PAYMENT_API_KEY,
            'amount' => $formatted_final_price, // Use the formatted string amount
            'order_id' => $order_id,
            'redirect_url' => PAYMENT_REDIRECT_URL,
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
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result === null) {
            throw new Exception('Invalid response from payment gateway.');
        }

        if ($result['status'] === true && isset($result['result']['payment_url'])) {
            // Update order with payment URL
            $db->query(
                "UPDATE orders SET payment_url = ? WHERE id = ?",
                [$result['result']['payment_url'], $internal_order_id]
            );

            echo json_encode([
                'status' => 'success',
                'payment_url' => $result['result']['payment_url'],
                'order_id' => $order_id
            ]);
        } else {
            throw new Exception($result['message'] ?? 'Payment gateway error');
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Process Payment Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An internal error occurred while processing your order. Please try again later.']);
    }
}
?>