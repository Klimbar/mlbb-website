<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
session_start();

if (isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['new_token' => $_SESSION['csrf_token']]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No active session']);
}