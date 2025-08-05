<?php
// config.php
// This file contains all the configuration constants for the application.

// --- Base URL ---
define('BASE_URL', 'https://10a192074637.ngrok-free.app');

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'mlbb_user');
define('DB_PASS', 'your_password'); // IMPORTANT: Replace with your actual database password
define('DB_NAME', 'mlbb_db');

// --- Game/Top-up API Configuration ---
define('API_BASE_URL', 'https://www.smile.one');
define('API_KEY', 'c9f8acdf67c5bf361398fb53b94ac651');
define('API_UID', '2776108');
define('API_EMAIL', 'serdihinsales@gmail.com');

// --- SMTP Configuration ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'serdihinsales@gmail.com');
define('SMTP_PASSWORD', 'ovfheudbtctnqmon');
define('SMTP_FROM_EMAIL', 'serdihinsales@gmail.com');
define('SMTP_FROM_NAME', 'Serdihin');

// --- Payment Gateway Configuration ---
define('PAYMENT_API_KEY', 'eb337579b41cd02407eb94358b177507');
define('PAYMENT_GATEWAY_URL', 'https://pay0.shop/api/create-order');
define('PAYMENT_STATUS_URL', 'https://pay0.shop/api/check-order-status');
define('PAYMENT_REDIRECT_URL', 'https://serdihin.com/payments/callback');


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

function is_logged_in() {
    return isset($_SESSION['user_id']);
}
