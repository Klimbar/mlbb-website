<?php
header('Content-Type: application/json');

$userId = isset($_GET['id']) ? $_GET['id'] : '';
$zoneId = isset($_GET['zone']) ? $_GET['zone'] : '';

// Server-side validation
$userId = trim($userId);
$zoneId = trim($zoneId);

// Validate User ID
if (empty($userId) || !ctype_digit($userId) || strlen($userId) < 2 || strlen($userId) > 15) {
    $error_message = 'Invalid User ID. It must be a number between 2 and 15 digits long.';
    error_log('Proxy Validation Error: ' . $error_message);
    echo json_encode(['error' => $error_message]);
    exit;
}

// Validate Zone ID
if (empty($zoneId) || !ctype_digit($zoneId) || strlen($zoneId) < 1 || strlen($zoneId) > 5) {
    $error_message = 'Invalid Zone ID. It must be a number between 1 and 5 digits long.';
    error_log('Proxy Validation Error: ' . $error_message);
    echo json_encode(['error' => $error_message]);
    exit;
}

// Convert to int after string validation
$userId = (int)$userId;
$zoneId = (int)$zoneId;

// Additional check for positive numbers
if ($userId <= 0 || $zoneId <= 0) {
    echo json_encode(['error' => 'User ID and Zone ID must be positive numbers.']);
    exit;
}

require_once __DIR__ . '/../config.php';
$apiKey = RAPIDAPI_KEY;
$apiUrl = "https://check-id-game1.p.rapidapi.com/api/game/cek-region-mlbb-m?id={$userId}&zone={$zoneId}";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add this line

$headers = [
    'x-rapidapi-host: check-id-game1.p.rapidapi.com',
    'x-rapidapi-key: ' . $apiKey,
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($httpcode == 200) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($data['data']['username']) && isset($data['data']['region'])) {
            $username = str_replace('+', ' ', $data['data']['username']); // Replace '+' with space
            echo json_encode([
                'username' => $username,
                'region' => $data['data']['region']
            ]);
        } else {
            $error_message = 'Invalid API response: Missing username or region.';
            error_log('Proxy API Error: ' . $error_message . ' Response: ' . $response);
            echo json_encode(['error' => 'Invalid API response.']);
        }
    } else {
        $error_message = 'Invalid JSON from API: ' . json_last_error_msg();
        error_log('Proxy API Error: ' . $error_message . ' Response: ' . $response);
        echo json_encode(['error' => 'Invalid API response.']);
    }
} else {
    $error_message = 'Failed to fetch data from API.';
    $api_response_data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($api_response_data['message'])) {
        if (strpos($api_response_data['message'], 'You have exceeded the rate limit per minute') !== false) {
            $error_message = 'Please wait a moment and try again.';
        } else {
            $error_message = 'An error occurred while fetching data.';
        }
    }
    error_log('Proxy API Error: ' . $error_message . ' Full Response: ' . $response);
    echo json_encode(['error' => $error_message]);
}