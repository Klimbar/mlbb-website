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