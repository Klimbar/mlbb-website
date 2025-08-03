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
            $cacheFile = __DIR__ . '/cache/products.json';
            $cacheDuration = 300; // Cache for 5 minutes (300 seconds)

            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
                // Serve from cache
                $products = json_decode(file_get_contents($cacheFile), true);
            } else {
                // Fetch from DB and cache
                $db = new Database();
                $products = $db->query("SELECT product_id as id, name as spu, selling_price as price, is_out_of_stock FROM products ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
                file_put_contents($cacheFile, json_encode($products));
            }
            echo json_encode(['status' => 200, 'message' => 'success', 'data' => ['product' => $products]]);
            break;

        case 'verifyPlayer':
            $userid = trim($_POST['userid'] ?? '');
            $zoneid = trim($_POST['zoneid'] ?? '');
            $productid = trim($_POST['productid'] ?? '');

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
            $productid = !empty($productid) ? $productid : '22590';

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
