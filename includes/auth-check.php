<?php

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($user_id, $token) = explode(':', $_COOKIE['remember_me'], 2);

    if ($user_id && $token) {
        $db = new Database();
        $result = $db->query("SELECT * FROM remember_me_tokens WHERE user_id = ? AND expiry_date > NOW()", [$user_id]);
        $saved_tokens = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($saved_tokens as $saved_token) {
            if (hash_equals(hash('sha256', $token), $saved_token['token_hash'])) {
                $user_result = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
                $user = $user_result->fetch_assoc();

                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                }
                break; // Token found and validated, no need to check further
            }
        }
    }
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login");
    exit();
}
?>