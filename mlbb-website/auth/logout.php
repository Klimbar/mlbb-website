<?php
require_once __DIR__ . '/../bootstrap.php';

// Clear the "Remember Me" cookie and database token
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);

    // Delete the token from the database
    $db = new Database();
    $db->query("DELETE FROM remember_me_tokens WHERE token_hash = ?", [$token_hash]);

    // Unset the cookie by setting its expiration in the past
    setcookie('remember_me', '', time() - 3600, '/');
}

// Destroy session
session_destroy();
header("Location: " . BASE_URL . "/");
exit();
?>