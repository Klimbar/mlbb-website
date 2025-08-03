<?php

// On a production server, it's recommended to turn off error display
ini_set('display_errors', 0);
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bootstrap_errors.log');

// Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Set the default timezone to UTC for consistency
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering
ob_start();

// Core files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/api_helpers.php';

// Function to check if a user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
