<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

// Handle remember me token (only delete current device's token)
if (isset($_COOKIE['remember_me'])) {
    $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
    
    if (count($cookie_parts) === 2) {
        list($user_id, $token) = $cookie_parts;
        
        if ($user_id && $token) {
            $db = new Database();
            $token_hash = hash('sha256', $token);
            
            // Only delete THIS device's token, not all tokens for the user
            $db->query("DELETE FROM remember_me_tokens WHERE user_id = ? AND token_hash = ?", 
                      [$user_id, $token_hash]);
        }
    }
    
    // Clear the remember me cookie
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Destroy session
session_destroy();

// Redirect to home page
header("Location: " . BASE_URL . "/");
exit();
?>