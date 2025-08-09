<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors['form'] = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($username)) {
            $errors['username'] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors['username'] = "Username must be at least 3 characters";
        } elseif (strlen($username) > 50) {
            $errors['username'] = "Username must be less than 50 characters";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Valid email is required";
        }
        
        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        }
        
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match";
        }
        
        // Only connect to the database if initial validations pass
        if (empty($errors)) {
            $db = new Database();
            $result = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            
            if ($result->num_rows > 0) {
                $errors['email'] = "An account with this email address already exists.";
            }
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
    
    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-danger">
            <p><?= htmlspecialchars($errors['form']) ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username" class="form-control <?php if (isset($errors['username'])) echo 'is-invalid'; ?>" value="<?= htmlspecialchars($username) ?>" required>
            <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control <?php if (isset($errors['email'])) echo 'is-invalid'; ?>" value="<?= htmlspecialchars($email) ?>" required>
            <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control <?php if (isset($errors['password'])) echo 'is-invalid'; ?>" required>
            <div id="password-feedback" class="invalid-feedback"></div>
            <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php if (isset($errors['confirm_password'])) echo 'is-invalid'; ?>" required>
            <div id="confirm-password-feedback" class="invalid-feedback"></div>
            <?php if (isset($errors['confirm_password'])): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['confirm_password']) ?></div>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    
    <p>Already have an account? <a href="<?php echo BASE_URL; ?>/auth/login">Login here</a></p>
</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordFeedback = document.getElementById('password-feedback');
    const confirmPasswordFeedback = document.getElementById('confirm-password-feedback');

    function validatePasswordComplexity() {
        const password = passwordInput.value;
        let errors = [];

        if (password.length === 0) { // Don't show errors if the field is empty
            passwordInput.setCustomValidity("");
            passwordFeedback.textContent = "";
            passwordFeedback.style.display = 'none';
            passwordInput.classList.remove('is-invalid');
            return;
        }

        if (password.length < 8) {
            errors.push("be at least 8 characters");
        }

        if (errors.length > 0) {
            passwordInput.setCustomValidity("Password does not meet requirements.");
            passwordFeedback.textContent = "Password must " + errors.join(', ') + ".";
            passwordFeedback.style.display = 'block';
            passwordInput.classList.add('is-invalid');
        } else {
            passwordInput.setCustomValidity("");
            passwordFeedback.textContent = "";
            passwordFeedback.style.display = 'none';
            passwordInput.classList.remove('is-invalid');
        }
    }

    function validateConfirmPassword() {
        // Only validate if there's input in the confirm password field
        if (confirmPasswordInput.value.length > 0) {
            if (passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match.');
                confirmPasswordFeedback.textContent = 'Passwords do not match.';
                confirmPasswordFeedback.style.display = 'block';
                confirmPasswordInput.classList.add('is-invalid');
            } else {
                confirmPasswordInput.setCustomValidity('');
                confirmPasswordFeedback.textContent = '';
                confirmPasswordFeedback.style.display = 'none';
                confirmPasswordInput.classList.remove('is-invalid');
            }
        } else {
            // If it's empty, it's not invalid, clear any previous message
            confirmPasswordInput.setCustomValidity('');
            confirmPasswordFeedback.textContent = '';
            confirmPasswordFeedback.style.display = 'none';
            confirmPasswordInput.classList.remove('is-invalid');
        }
    }

    passwordInput.addEventListener('input', function() {
        validatePasswordComplexity();
        validateConfirmPassword(); // Re-validate confirm password when password changes
    });
    confirmPasswordInput.addEventListener('input', validateConfirmPassword);
});
</script>