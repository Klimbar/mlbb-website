<?php
require_once 'config.php';
require_once 'includes/api_helpers.php';

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
            $params = getBaseApiParams() + ['product' => 'mobilelegends'];
            $params['sign'] = generateSign($params, API_KEY);

            $response = callApi('/smilecoin/api/productlist', $params);
            if ($response === null) {
                http_response_code(502); // Bad Gateway
                echo json_encode(['status' => 502, 'message' => 'Failed to retrieve product list from the provider.']);
                break;
            }

            echo json_encode($response);
            break;

        case 'verifyPlayer':
            $userid = trim($_POST['userid'] ?? '');
            $zoneid = trim($_POST['zoneid'] ?? '');
            $productid = trim($_POST['productid'] ?? '');

            // Enhanced input validation
            if (empty($userid) || empty($zoneid) || empty($productid)) {

                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Missing required parameters: userid, zoneid, or productid.']);
                break;
            }
            if (!ctype_digit($userid) || !ctype_digit($zoneid)) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Player ID and Zone ID must be numeric.']);
                break;
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
?>