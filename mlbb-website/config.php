<?php

// Base URL
// This will dynamically determine the base URL including protocol and domain.
// IMPORTANT: When you deploy to a production server with a fixed domain,
// you should replace this dynamic calculation with your actual domain:
define('BASE_URL', 'https://af3843166d09.ngrok-free.app');


// Database and API Configuration
define('API_BASE_URL', 'https://www.smile.one');
define('API_KEY', 'c9f8acdf67c5bf361398fb53b94ac651');
define('API_UID', '2776108');
define('API_EMAIL', 'serdihinsales@gmail.com');

// Database configuration (if storing orders)
define('DB_HOST', 'localhost');
define('DB_USER', 'mlbb_user');
define('DB_PASS', 'your_password'); 
define('DB_NAME', 'mlbb_db');
//Payment Gateway Configuration
define('PAYMENT_GATEWAY_URL', 'https://pay0.shop/api/create-order');
define('PAYMENT_STATUS_URL', 'https://pay0.shop/api/check-order-status');
define('PAYMENT_API_KEY', 'eb337579b41cd02407eb94358b177507');
define('PAYMENT_REDIRECT_URL', BASE_URL . '/orders/history'); // Redirect user to order history after payment


// CSRF Token functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

?>