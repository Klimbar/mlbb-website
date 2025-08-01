<?php
require_once 'config.php';
require_once 'includes/api_helpers.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

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
            $db = new Database();
            $products = $db->query("SELECT product_id as id, name as spu, selling_price as price FROM products ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
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
?>