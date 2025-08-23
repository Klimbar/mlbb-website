<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = 'Forgot Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for security
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        header('Location: ' . BASE_URL . '/auth/forgot_password');
        exit;
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Invalid email format.';
    } else {
        $db = new Database();
        $user = $db->query('SELECT id FROM users WHERE email = ?', [$email])->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db->query(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?',
                [$email, $token, $expires_at, $token, $expires_at]
            );

            // Use the pretty URL defined in routes.php for consistency
            $reset_link = BASE_URL . '/auth/reset_password?token=' . $token;
            
            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;

                //Recipients
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "Hello,<br><br>You are receiving this email because we received a password reset request for your account.<br><br>Please click on the following link to reset your password: <a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>If you did not request a password reset, no further action is required.<br><br>Regards,<br>Your Website Team";
                $mail->AltBody = "Hello,\n\nYou are receiving this email because we received a password reset request for your account.\n\nPlease click on the following link to reset your password: " . $reset_link . "\n\nIf you did not request a password reset, no further action is required.\n\nRegards,\nSerdihin Team";

                $mail->send();
                $_SESSION['success_message'] = 'A password reset link has been sent to ' . htmlspecialchars($email) . '.';
            } catch (Exception $e) {
                // Log the error for debugging, but show a generic message to the user.
                error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
                $_SESSION['error_message'] = "Message could not be sent. Please try again later.";
            }
        } else {
            $_SESSION['error_message'] = 'Email not found. Please check your email address and try again.';
        }
    }
    // Redirect to clear POST data and display messages
    header('Location: ' . BASE_URL . '/auth/forgot_password');
    exit;
}
?>

<div class="page-content-wrapper">
    <div class="main-content">
        <div class="container auth-container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-5">
                        <div class="card-body">
                            <h2 class="card-title text-center">Forgot Password</h2>
                            <p class="text-center">Enter your email address and we will send you a link to reset your password.</p>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Send Password Reset Link</button>
                            </form>
                            <?php if (isset($_SESSION['success_message'])):
 ?>
                                <div class="alert alert-success mt-3"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['error_message'])):
 ?>
                                <div class="alert alert-danger mt-3"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const submitButton = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function() {
        // Disable the button to prevent multiple clicks
        submitButton.disabled = true;
        // Change button text to show a loading state with a Bootstrap spinner
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    });
});
</script>
