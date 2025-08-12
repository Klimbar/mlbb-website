<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid request. Please try again.";
        header("Location: " . BASE_URL . "/auth/login");
        exit();
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $db = new Database();
    $result = $db->query("SELECT * FROM users WHERE email = ?", [$email]);
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        if (!$user['is_verified']) {
            $_SESSION['error_message'] = "Your account is not verified. Please register again.";
            header("Location: " . BASE_URL . "/auth/login");
            exit();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if (isset($_POST['remember_me'])) {
            // Check current device count for this user
            $device_count_result = $db->query("SELECT COUNT(*) as count FROM remember_me_tokens WHERE user_id = ? AND expiry_date > NOW()", [$user['id']]);
            $device_count = $device_count_result->fetch_assoc()['count'];
            
            if ($device_count >= 3) {
                // Remove oldest tokens to make room for the new one
                $tokens_to_remove = $device_count - 2; // Keep 2, so new one makes 3
                $db->query("DELETE FROM remember_me_tokens 
                           WHERE user_id = ? AND expiry_date > NOW() 
                           ORDER BY id ASC 
                           LIMIT ?", [$user['id'], $tokens_to_remove]);
            }

            // Create remember me token
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days from now
            
            $db->query("INSERT INTO remember_me_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)", 
                      [$user['id'], $token_hash, $expiry_date]);
            
            setcookie('remember_me', $user['id'] . ':' . $token, [
                'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Auto-detect HTTPS
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Strict' // CSRF protection
            ]);
        }

        // Redirect to the home page on successful login
        header("Location: " . BASE_URL . "/");
        exit();
    } else {
        $_SESSION['error_message'] = "Invalid email or password";
        header("Location: " . BASE_URL . "/auth/login");
        exit();
    }
} else {
    // If not a POST request, redirect away
    header("Location: " . BASE_URL . "/auth/login");
    exit();
}
?>