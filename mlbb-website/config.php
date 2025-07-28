<?php
// Database and API Configuration
define('API_BASE_URL', 'https://www.smile.one');
define('API_KEY', 'c9f8acdf67c5bf361398fb53b94ac651');
define('API_UID', '2776108');
define('API_EMAIL', 'serdihinsales@gmail.com');

// Database configuration (if storing orders)
define('DB_HOST', 'localhost');
define('DB_USER', 'mlbb_user');
define('DB_PASS', 'your_password'); // <-- IMPORTANT: Change this!
define('DB_NAME', 'mlbb_db');
//Payment Gateway Configuration
define('PAYMENT_GATEWAY_URL', 'https://pay0.shop/api/create-order');
define('PAYMENT_STATUS_URL', 'https://pay0.shop/api/check-order-status');
define('PAYMENT_API_KEY', 'eb337579b41cd02407eb94358b177507');
define('PAYMENT_REDIRECT_URL', 'http://yourdomain.com/payments/callback.php'); // Update with your domain

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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