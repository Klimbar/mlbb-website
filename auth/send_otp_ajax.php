<?php
error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/otp_email_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS headers
header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token.');
    }

    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }

    if (!preg_match('/^\+91[6-9]\d{9}$/', $phone)) {
        throw new Exception('Invalid phone number format.');
    }

    if (empty($username)) {
        throw new Exception('Username is required.');
    }

    $db = new Database();
    $user_result = $db->query("SELECT id, is_verified FROM users WHERE email = ? OR phone = ?", [$email, $phone]);
    $user = $user_result->fetch_assoc();

    if ($user && $user['is_verified']) {
        $response['success'] = false;
        $response['message'] = 'An account with this email or phone number already exists. Please Login.';
        $response['new_csrf_token'] = generateCSRFToken();
        echo json_encode($response);
        exit();
    }

    // If user exists but is NOT verified, update their details
    if ($user && !$user['is_verified']) {
        $db->query("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?", [$username, $email, $phone, $user['id']]);
        $user_id = $user['id']; // Use existing user ID
    } else { // User does not exist, so insert new user
        $db->query("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)", [$username, $email, $phone, '']);
        $user_id = $db->getLastInsertId();
    }

    // Now, generate and send OTP for either updated unverified user or newly created user
    $otp_data = generateAndStoreOtp($user_id);
    $otp = $otp_data['otp'];
    $otp_expires_at = $otp_data['expires_at'];

    if (sendOtpEmail($email, $otp)) {
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_username'] = $username;
        $_SESSION['otp_phone'] = $phone;
        $_SESSION['otp_expires_at'] = $otp_expires_at;
        $_SESSION['resend_timer_start_time'] = time();
        $response['success'] = true;
        $response['message'] = 'OTP sent to your email.';
    } else {
        throw new Exception('Failed to send OTP email.');
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['success'] = false;
    $response['message'] = 'Server error occurred. Please try again.';
    error_log('OTP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

$response['new_csrf_token'] = generateCSRFToken();
echo json_encode($response);


