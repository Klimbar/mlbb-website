<?php
require_once 'bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/api_helpers.php';

// Set content type to JSON and clean buffer
header('Content-Type: application/json');
ob_end_clean();

$action = $_GET['action'] ?? '';

function getBaseApiParams(): array {
    return [
        'uid' => API_UID,
        'email' => API_EMAIL,
        'time' => time()
    ];
}

try {
    switch ($action) {
        case 'getProducts':
            $cacheDir = __DIR__ . '/cache';
            $cacheFile = $cacheDir . '/products.json';
            $cacheDuration = 300; // Cache for 5 minutes (300 seconds)
            $products = null;

            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
                // Serve from cache
                $cachedData = @file_get_contents($cacheFile);
                if ($cachedData !== false) {
                    $products = json_decode($cachedData, true);
                }
            }

            if ($products === null) {
                // Fetch from DB and cache
                $db = new Database();
                $regular_products = $db->query("SELECT product_id as id, name as spu, description, image, selling_price as price, is_out_of_stock FROM products")->fetch_all(MYSQLI_ASSOC);
                $custom_products = $db->query("SELECT product_ids as id, name as spu, description, image, selling_price as price, is_out_of_stock FROM custom_products")->fetch_all(MYSQLI_ASSOC);
                
                $all_products = array_merge($regular_products, $custom_products);
                
                usort($all_products, function($a, $b) {
                    return $a['price'] <=> $b['price'];
                });

                $products = $all_products; // Assign the sorted combined array
                
                // Ensure cache directory exists and is writable
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0755, true);
                }
                // Attempt to write to cache only if directory is writable
                if (is_writable($cacheDir) && file_put_contents($cacheFile, json_encode($products)) === false) {
                    error_log("API Warning: Could not write to product cache file: " . $cacheFile);
                }
            }
            echo json_encode(['status' => 200, 'message' => 'success', 'data' => ['product' => $products]]);
            break;

        case 'verifyPlayer':
            $userid = trim($_POST['userid'] ?? '');
            $zoneid = trim($_POST['zoneid'] ?? '');
            $productid_string = trim($_POST['productid'] ?? '');

            // Enhanced input validation
            if (empty($userid) || empty($zoneid)) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Missing required parameters: userid or zoneid.']);
                break;
            }
            if (!ctype_digit($userid) || !ctype_digit($zoneid)) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Player ID and Zone ID must be numeric.']);
                break;
            }

            // Use default productid if not provided
            $productid = !empty($productid_string) ? $productid_string : '22590';

            // If it's a custom product, use the first ID for verification
            if (strpos($productid, '&') !== false) {
                $product_ids = explode('&', $productid);
                $productid = $product_ids[0];
            }

            $params = getBaseApiParams() + [
                'userid' => $userid,
                'zoneid' => $zoneid,
                'product' => 'mobilelegends',
                'productid' => $productid
            ];
            $params['sign'] = generateSign($params, API_KEY);

            $response = callApi('/smilecoin/api/getrole', $params);

            if ($response === null) {
                http_response_code(502); // Bad Gateway
                echo json_encode(['status' => 502, 'message' => 'Failed to verify player with the provider.']);
                break;
            }
            echo json_encode($response);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    // Log the detailed error for debugging, but don't expose it to the client.
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'An internal server error occurred.']);
}
