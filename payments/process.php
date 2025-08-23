<?php
// Log all incoming requests to a file for debugging
$log_file = __DIR__ . '/../logs/process.log';
$log_data = "---\\n"          . "Time: " . date('Y-m-d H:i:s') . "\\n"
          . "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\\n"
          . "POST Data: " . json_encode($_POST) . "\\n\\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

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
    if (empty($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    try {
        // Instantiate the database connection once.
        $db = new Database();

        $user_id = $_SESSION['user_id'];
        $product_id_string = $_POST['productid'] ?? '';
        $player_id = $_POST['userid'] ?? '';
        $zone_id = $_POST['zoneid'] ?? '';

        if (strpos($product_id_string, '&') !== false) {
            $db->begin_transaction();

            // Fetch the custom product details directly
            $custom_product_db = $db->query("SELECT name, selling_price FROM custom_products WHERE product_ids = ?", [$product_id_string])->fetch_assoc();

            if ($custom_product_db === null) {
                throw new Exception('Custom product not found: ' . $product_id_string);
            }

            $product_name = $custom_product_db['name'];
            $total_price = (float)$custom_product_db['selling_price'];

            // Player verification (only needs to be done once for the bundle)
            $any_product_id = explode('&', $product_id_string)[0];
            $verify_params = [
                'uid' => API_UID,
                'email' => API_EMAIL,
                'userid' => $player_id,
                'zoneid' => $zone_id,
                'product' => 'mobilelegends',
                'productid' => $any_product_id, // Use one ID for verification
                'time' => time()
            ];
            $verify_params['sign'] = generateSign($verify_params, API_KEY);
            $verify_response = callApi('/smilecoin/api/getrole', $verify_params);

            if ($verify_response === null || !isset($verify_response['status']) || $verify_response['status'] !== 200) {
                throw new Exception('Player verification failed for custom bundle');
            }

            $price_multiplier = (float)($verify_response['change_price'] ?? 1);
            $total_price *= $price_multiplier;
            
            $order_id = 'SD' . time() . bin2hex(random_bytes(4));

            $db->query(
                "INSERT INTO orders (user_id, player_id, zone_id, product_id, product_name, amount, order_status, order_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
                [$user_id, $player_id, $zone_id, $product_id_string, $product_name, $total_price, $order_id]
            );
            $internal_order_id = $db->getLastInsertId();

            $post_data = [
                'user_token' => PAYMENT_API_KEY,
                'amount' => number_format($total_price, 2, '.', ''),
                'order_id' => $order_id,
                'currency' => 'INR',
                'redirect_url' => PAYMENT_REDIRECT_URL . '?order_id=' . $order_id,
                'customer_mobile' => '',
                'customer_name' => $_SESSION['username'],
                'remark1' => 'MLBB Diamonds',
                'remark2' => "Player: $player_id, Zone: $zone_id"
            ];

            $ch = curl_init(PAYMENT_GATEWAY_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($result === null || !isset($result['status']) || $result['status'] !== true || !isset($result['result']['payment_url'])) {
                $db->rollback();
                throw new Exception($result['message'] ?? 'Payment gateway error');
            }

            $db->query("UPDATE orders SET payment_url = ? WHERE id = ?", [$result['result']['payment_url'], $internal_order_id]);
            $db->commit();

            echo json_encode(['status' => 'success', 'payment_url' => $result['result']['payment_url'], 'order_id' => $order_id]);
            exit;

        } else {
            $verify_params = [
                'uid' => API_UID,
                'email' => API_EMAIL,
                'userid' => $player_id,
                'zoneid' => $zone_id,
                'product' => 'mobilelegends',
                'productid' => $product_id_string,
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

            $selected_product_db = $db->query("SELECT name, selling_price FROM products WHERE product_id = ?", [$product_id_string])->fetch_assoc();

            if ($selected_product_db === null) {
                error_log("Product not found in local database: " . $product_id_string);
                throw new Exception('Product not found in our system.');
            }

            $base_price = (float)$selected_product_db['selling_price'];
            $product_name = $selected_product_db['name'];
            $final_price = $base_price * $price_multiplier;
            $formatted_final_price = number_format($final_price, 2, '.', '');

            $db->begin_transaction();

            $order_id = 'SD' . time() . bin2hex(random_bytes(4));

            $db->query(
                "INSERT INTO orders (user_id, player_id, zone_id, product_id, product_name, amount, order_status, order_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
                [$user_id, $player_id, $zone_id, $product_id_string, $product_name, $formatted_final_price, $order_id]
            );
            $internal_order_id = $db->getLastInsertId();

            $post_data = [
                'user_token' => PAYMENT_API_KEY,
                'amount' => $formatted_final_price,
                'order_id' => $order_id,
                'currency' => 'INR',
                'redirect_url' => PAYMENT_REDIRECT_URL . '?order_id=' . $order_id,
                'customer_mobile' => '',
                'customer_name' => $_SESSION['username'],
                'remark1' => 'MLBB Diamonds',
                'remark2' => "Player: $player_id, Zone: $zone_id"
            ];

            $ch = curl_init(PAYMENT_GATEWAY_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($result === null) {
                error_log("Invalid response from payment gateway. HTTP Code: " . $http_code . " Response: " . $response);
                $db->rollback();
                throw new Exception('Invalid response from payment gateway.');
            }

            if (isset($result['status']) && $result['status'] === true && isset($result['result']['payment_url'])) {
                $db->query("UPDATE orders SET payment_url = ? WHERE id = ?", [$result['result']['payment_url'], $internal_order_id]);
                $db->commit();

                echo json_encode(['status' => 'success', 'payment_url' => $result['result']['payment_url'], 'order_id' => $order_id]);
                exit;
            } else {
                error_log("Payment gateway error: " . ($result['message'] ?? 'Unknown error'));
                $db->rollback();
                throw new Exception($result['message'] ?? 'Payment gateway error');
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        if (isset($db) && $db->inTransaction()) $db->rollback();
        error_log("Process Payment Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred. Please try again later.']);
        exit;
    }
}
?>