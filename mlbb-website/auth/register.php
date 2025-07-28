<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /");
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
            
            header("Location: /");
            exit();
        }
    }
}
?>

<div class="container">
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
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn">Register</button>
    </form>
    
    <p>Already have an account? <a href="/auth/login.php">Login here</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
