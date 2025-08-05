<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

error_log('reset_password.php: Script started.', 0);

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
error_log('reset_password.php: Received token: ' . $token, 0);

if (!$token) {
    $_SESSION['error_message'] = 'Invalid password reset token.';
    error_log('reset_password.php: No token received.', 0);
    header('Location: ' . BASE_URL . '/auth/forgot_password');
    exit;
}

$db = new Database();
$result = $db->query('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()', [$token]);
$reset_request = $result->fetch_assoc();

if (!$reset_request) {
    $_SESSION['error_message'] = 'Invalid or expired password reset token.';
    error_log('reset_password.php: Token not found or expired in DB for token: ' . $token, 0);
    header('Location: ' . BASE_URL . '/auth/forgot_password');
    exit;
}
error_log('reset_password.php: Token found and valid for email: ' . $reset_request['email'], 0);

$page_title = 'Reset Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('reset_password.php: POST request received.', 0);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $_SESSION['error_message'] = 'Please fill in all fields.';
        error_log('reset_password.php: Password fields empty.', 0);
    } elseif ($password !== $confirm_password) {
        $_SESSION['error_message'] = 'Passwords do not match.';
        error_log('reset_password.php: Passwords do not match.', 0);
    } elseif (strlen($password) < 8) {
        $_SESSION['error_message'] = 'Password must be at least 8 characters long.';
        error_log('reset_password.php: Password too short.', 0);
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $db->query('UPDATE users SET password = ? WHERE email = ?', [$hashed_password, $reset_request['email']]);
        error_log('reset_password.php: User password updated for email: ' . $reset_request['email'], 0);

        $db->query('DELETE FROM password_resets WHERE email = ?', [$reset_request['email']]);
        error_log('reset_password.php: Password reset entry deleted for email: ' . $reset_request['email'], 0);

        $_SESSION['success_message'] = 'Your password has been reset successfully. You can now log in.';
        header('Location: ' . BASE_URL . '/auth/login');
        exit;
    }
}

?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-5">
                <div class="card-body">
                    <h2 class="card-title text-center">Reset Password</h2>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                    <?php endif; ?>
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">Passwords do not match.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    function validatePasswords() {
        if (confirmPassword.value.length > 0) {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match.');
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
            }
        } else {
            confirmPassword.setCustomValidity('');
            confirmPassword.classList.remove('is-invalid');
        }
    }

    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
});
</script>
