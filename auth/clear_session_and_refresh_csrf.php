<?php
require_once __DIR__ . '/../bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && validateCSRFToken($_POST['csrf_token'])) {
        unset($_SESSION['otp_email']);
        echo json_encode(['success' => true, 'new_token' => generateCSRFToken()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
