<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

if (isset($_COOKIE['remember_me'])) {
    list($user_id, $token) = explode(':', $_COOKIE['remember_me'], 2);

    if ($user_id) {
        $db = new Database();
        $db->query("DELETE FROM remember_me_tokens WHERE user_id = ?", [$user_id]);
    }

    setcookie('remember_me', '', time() - 3600, "/"); // Expire the cookie
}

// Destroy session
session_destroy();
header("Location: " . BASE_URL . "/");
exit();
?>