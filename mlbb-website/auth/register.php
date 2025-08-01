<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';


// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors = ["Invalid request. Please try again."];
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        $errors = [];
        
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } elseif (strlen($username) > 50) {
            $errors[] = "Username must be less than 50 characters";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        }
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        // Check if email exists
        $db = new Database();
        $result = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        
        if (empty($errors)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $db->query(
                "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
                [$username, $email, $hashed_password]
            );
            
            $_SESSION['user_id'] = $db->getLastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            
            header("Location: " . BASE_URL . "/");
            exit();
        }
    }
}
?>

<div class="container main-content">
    <h2>Register</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    
    <p>Already have an account? <a href="<?php echo BASE_URL; ?>/auth/login">Login here</a></p>
</div>