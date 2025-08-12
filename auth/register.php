<?php
ob_start(); // Start output buffering

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';


$errors = [];
$username = '';
$email = '';
$phone = '';
$otp_sent = false;
$otp_expires_at_js = 0; // Default to 0

// Check if we are returning to the page after an OTP has been sent
if (!empty($_SESSION['otp_email'])) {
    $email = $_SESSION['otp_email'];
    $username = $_SESSION['otp_username'] ?? ''; // Retrieve username from session
    $phone = str_replace('+91', '', $_SESSION['otp_phone'] ?? '');     // Retrieve phone from session and remove +91 prefix
    $otp_sent = true; 
    if (!empty($_SESSION['resend_timer_start_time'])) {
        // Calculate the client-side resend timer expiration based on a 60-second interval
        $otp_expires_at_js = $_SESSION['resend_timer_start_time'] + 60; 
    }
    // Force regeneration of CSRF token on page reload if OTP was sent
    generateCSRFToken(); // Call the function to ensure a fresh token is in session
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors['form'] = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $full_phone = '+91' . $phone;
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

        // Since this is a multi-step form, we assume all fields are present on final submission
        if (empty($username)) $errors['username'] = "Username is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Valid email is required";
        if (!preg_match('/^\+91[6-9]\d{9}$/', $full_phone)) $errors['phone'] = 'Invalid phone number format.';
        if (strlen($password) < 8) $errors['password'] = "Password must be at least 8 characters";
        if ($password !== $confirm_password) $errors['confirm_password'] = "Passwords do not match";

        if (empty($errors)) {
            if (!isset($_SESSION['otp_email']) || $_SESSION['otp_email'] !== $email) {
                $errors['form'] = "Session expired or email mismatch. Please start over.";
            } else {
                $db = new Database();
                $user_result = $db->query("SELECT id, is_verified FROM users WHERE email = ?", [$email]);
                $user = $user_result->fetch_assoc();

                if (!$user) $errors['form'] = "An error occurred. Please try again.";
                elseif ($user['is_verified']) $errors['email'] = "This email is already verified. Please login.";
                else {
                    $otp_result = $db->query("SELECT otp, otp_expires_at FROM user_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$user['id']]);
                    $otp_data = $otp_result->fetch_assoc();

                    if (empty($entered_otp)) $errors['otp'] = "OTP is required.";
                    elseif (!$otp_data || $otp_data['otp'] !== $entered_otp) $errors['otp'] = "Invalid OTP.";
                    elseif (strtotime($otp_data['otp_expires_at']) < time()) $errors['otp'] = "OTP has expired. Please resend.";
                }
            }
        }

        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query(
                "UPDATE users SET username = ?, password = ?, is_verified = TRUE WHERE email = ?",
                [$username, $hashed_password, $email]
            );
            $db->query("DELETE FROM user_otps WHERE user_id = ?", [$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';
            unset($_SESSION['otp_email']);

            header("Location: " . BASE_URL . "/");
            exit();
        } else {
            // If there are errors on final submission, we should probably stay on step 2
            $otp_sent = true;
        }
    }
}

?>

<div class="container main-content">
    <h2>Register</h2>
    
    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-danger"><p><?php echo htmlspecialchars($errors['form']); ?></p></div>
    <?php endif; ?>
    
    <form method="POST" id="registerForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <!-- Step 1: User Details -->
        <div id="step1">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control <?php if (isset($errors['username'])) echo 'is-invalid'; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username'] ?? ''); ?></div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control <?php if (isset($errors['email'])) echo 'is-invalid'; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></div>
                <small class="form-text text-muted">An OTP will be sent to this email for verification.</small>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" inputmode="numeric" pattern="[0-9]*" id="phone" name="phone" class="form-control <?php if (isset($errors['phone'])) echo 'is-invalid'; ?>" maxlength="10" value="<?php echo htmlspecialchars($phone); ?>" required>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone'] ?? ''); ?></div>
                </div>
            </div>

            <div id="step1-feedback" class="text-danger mb-2"></div>
            <button type="button" id="nextButton" class="btn btn-primary">Send OTP & Continue</button>
        </div>

        <!-- Step 2: OTP and Password -->
        <div id="step2" style="display: none;">
             <button type="button" id="backButton" class="btn btn-primary mb-3">Go Back</button>
             <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="mb-0">An OTP has been sent to <strong id="otp-email-display"></strong>. Please enter it below.</p>
            </div>
            <div class="mb-3" id="otpFieldGroup">
                <label for="otp" class="form-label">Enter OTP</label>
                <div class="input-group">
                    <input type="tel" id="otp" name="otp" class="form-control <?php if (isset($errors['otp'])) echo 'is-invalid'; ?>" required>
                    <button type="button" class="btn btn-primary" id="resendOtpButton" disabled>Resend OTP (<span id="countdown">60</span>s)</button>
                </div>
                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['otp'] ?? ''); ?></div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control <?php if (isset($errors['password'])) echo 'is-invalid'; ?>" required>
                <div id="password-feedback" class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['password'] ?? ''); ?></div>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php if (isset($errors['confirm_password'])) echo 'is-invalid'; ?>" required>
                <div id="confirm-password-feedback" class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['confirm_password'] ?? ''); ?></div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="show_password">
                <label class="form-check-label" for="show_password">Show Password</label>
            </div>
            
            <button type="submit" id="registerButton" class="btn btn-primary">Register</button>
        </div>
    </form>
    
    <p class="mt-3">Already have an account? <a href="<?php echo BASE_URL; ?>/auth/login">Login here</a></p>
</div>

<script nonce="<?php echo htmlspecialchars($nonce); ?>">
document.addEventListener('DOMContentLoaded', function () {
    // Form steps
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');

    // Step 1 elements
    const nextButton = document.getElementById('nextButton');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const step1Feedback = document.getElementById('step1-feedback');

    // Step 2 elements
    const otpInput = document.getElementById('otp');
    const resendOtpButton = document.getElementById('resendOtpButton');
    const registerButton = document.getElementById('registerButton');
    const otpEmailDisplay = document.getElementById('otp-email-display');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordFeedback = document.getElementById('password-feedback');
    const confirmPasswordFeedback = document.getElementById('confirm-password-feedback');

    const registerForm = document.getElementById('registerForm');
    let countdownInterval;

    const backButton = document.getElementById('backButton');

    let username = '';
    let phone = '';

    function goToStep(step) {
        if (step === 1) {
            username = usernameInput.value;
            phone = phoneInput.value;

            step1.style.display = 'block';
            step2.style.display = 'none';

            usernameInput.value = username;
            phoneInput.value = phone;

            // Clear the OTP session and refresh the CSRF token
            fetch('<?php echo BASE_URL; ?>/auth/clear_session_and_refresh_csrf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: registerForm.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.new_token) {
                    registerForm.querySelector('input[name="csrf_token"]').value = data.new_token;
                }
            });

        } else if (step === 2) {
            step1.style.display = 'none';
            step2.style.display = 'block';
            otpEmailDisplay.textContent = emailInput.value;
        }
    }

    function startCountdown(initialTimeLeft) {
        // Clear any existing interval before starting a new one
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        let timeLeft = initialTimeLeft || 60;
        resendOtpButton.disabled = true;

        // Ensure the span structure is present, then update its content
        let countdownSpan = document.getElementById('countdown');
        if (!countdownSpan) {
            // If the span was destroyed (e.g., by 'Sending...' message), recreate the full HTML
            resendOtpButton.innerHTML = `Resend OTP (<span id="countdown">${timeLeft}</span>s)`;
            countdownSpan = document.getElementById('countdown'); // Get reference to the newly created span
        } else {
            // If the span already exists, just update its text content
            countdownSpan.textContent = timeLeft;
        }

        countdownInterval = setInterval(() => {
            timeLeft--;
            if (countdownSpan) countdownSpan.textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                resendOtpButton.disabled = false;
                resendOtpButton.innerHTML = 'Resend OTP';
            }
        }, 1000);
    }

    phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    function sendOtp(event) {
        const clickedButton = event.currentTarget;
        clickedButton.disabled = true;
        const originalButtonText = clickedButton.innerHTML;
        clickedButton.innerHTML = 'Sending...';

        step1Feedback.textContent = ''; // Clear previous errors
        // --- Basic client-side validation ---
        if (!usernameInput.value) {
            step1Feedback.textContent = 'Username is required.';
            clickedButton.disabled = false;
            clickedButton.innerHTML = originalButtonText;
            return;
        }
        const emailValue = emailInput.value.trim(); // Trim spaces
        if (!emailValue || !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(emailValue)) {
            step1Feedback.textContent = 'Valid email is required.';
            clickedButton.disabled = false;
            clickedButton.innerHTML = originalButtonText;
            return;
        }
        if (!phoneInput.value.match(/^[6-9]\d{9}$/)) {
            step1Feedback.textContent = 'Please enter a valid phone number.';
            clickedButton.disabled = false;
            clickedButton.innerHTML = originalButtonText;
            return;
        }

        clickedButton.disabled = true; // Ensure button is disabled before fetch
        const fullPhone = '+91' + phoneInput.value;
        let otpSendSuccess = false; // Flag to track success

        fetch('<?php echo BASE_URL; ?>/auth/send_otp_ajax.php', { // Use relative path instead of absolute URL
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                username: usernameInput.value,
                email: emailInput.value,
                phone: fullPhone,
                csrf_token: registerForm.querySelector('input[name="csrf_token"]').value
            })
        })
        .then(response => {
            //console.log('Raw response:', response); // Log raw response
            return response.json();
        })
        .then(data => {
            //console.log('Parsed data:', data); // Log parsed data
            //console.log('data.success:', data.success); // Log data.success
            if (data.new_csrf_token) {
                registerForm.querySelector('input[name="csrf_token"]').value = data.new_csrf_token;
            }
            if (data.success) {
                otpSendSuccess = true;
                goToStep(2);
                startCountdown(60); // Removed setTimeout and its log
            } else {
                step1Feedback.textContent = data.message || 'An unknown error occurred.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            step1Feedback.textContent = 'An error occurred. Please try again.';
        })
        .finally(() => {
            // Only restore original text if OTP sending was NOT successful
            if (!otpSendSuccess) {
                clickedButton.disabled = false;
                clickedButton.innerHTML = originalButtonText; // Restore original text if not successful
            }
        });
    }

    nextButton.addEventListener('click', sendOtp);
    resendOtpButton.addEventListener('click', sendOtp); // Resend also calls the same function

    backButton.addEventListener('click', function() {
        goToStep(1);
    });

    if (<?php echo json_encode($otp_sent); ?>) {
        goToStep(2);
        // Calculate remaining time
        const otpExpiresAt = <?php echo json_encode($otp_expires_at_js); ?> * 1000; // Convert to milliseconds
        const currentTime = Date.now();
        let initialTimeLeft = Math.max(0, Math.ceil((otpExpiresAt - currentTime) / 1000)); // Time left in seconds

        if (initialTimeLeft > 0) {
            startCountdown(initialTimeLeft);
        } else {
            // OTP has expired, enable resend button immediately
            resendOtpButton.disabled = false;
            resendOtpButton.innerHTML = 'Resend OTP';
            // Optionally, display a message that OTP has expired
            // step1Feedback.textContent = 'Your previous OTP has expired. Please resend.';
        }
    }

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

    // Add real-time password validation listeners
    passwordInput.addEventListener('input', function() {
        validatePasswordComplexity();
        validateConfirmPassword(); // Re-validate confirm password when password changes
    });
    confirmPasswordInput.addEventListener('input', validateConfirmPassword);

    document.getElementById('show_password').addEventListener('click', function (e) {
        var passwordInput = document.getElementById('password');
        var confirmPasswordInput = document.getElementById('confirm_password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            confirmPasswordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
            confirmPasswordInput.type = 'password';
        }
    });
});
</script>