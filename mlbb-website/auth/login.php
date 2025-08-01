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
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        $db = new Database();
        $result = $db->query("SELECT * FROM users WHERE email = ?", [$email]);
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: " . BASE_URL . "/");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<div class="container main-content">
    <h2>Login</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    
    <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/auth/register">Register here</a></p>
</div>