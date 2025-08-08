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

    // Add explicit server-side validation for the email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format provided.";
        header("Location: " . BASE_URL . "/auth/login");
        exit();
    }
    
    $db = new Database();
    $result = $db->query("SELECT * FROM users WHERE email = ?", [$email]);
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        session_regenerate_id(true); // Regenerate session ID on login for security

        // Handle "Remember Me" functionality
        if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days from now

            $db->query(
                "INSERT INTO remember_me_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)",
                [$user['id'], $token_hash, $expiry_date]
            );

            // Set the secure cookie
            setcookie('remember_me', $token, [
                'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                'path' => '/',
                'secure' => true,   // Only send over HTTPS
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Lax' // Helps prevent CSRF
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