<?php
// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use HTTPS
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config.php';

// Fallback BASE_URL if not set in config.php (prevents 500 errors)
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://serdihin.com');
}

// Generate a secure nonce
$nonce = base64_encode(random_bytes(16));
define('NONCE', $nonce);

// Detect API or JSON responses
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api') !== false || strpos($_SERVER['REQUEST_URI'], '/payments') !== false;

// Send security headers only for normal HTML requests
if (!$isApiRequest && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

 // --- Content Security Policy (CSP) ---
$csp = "default-src 'self'; ";

// Script sources (include nonce and CDNs)
$csp .= "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://use.fontawesome.com 'nonce-{$nonce}'; ";

// Style sources (Google Fonts, Bootstrap, Font Awesome)
$csp .= "style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://use.fontawesome.com 'unsafe-hashes'; ";

// Font sources
$csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://use.fontawesome.com; ";

// Image sources
$csp .= "img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";

// Other restrictions
$csp .= "object-src 'none'; frame-ancestors 'none'; form-action 'self';";

header("Content-Security-Policy: $csp");

}

//======================================================================
// GLOBAL HELPER FUNCTIONS
//======================================================================

/**
 * Generates a CSRF token and stores it in the session if it doesn't exist.
 * This should be used to populate hidden form fields.
 *
 * @return string The CSRF token.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a given CSRF token against the one stored in the session.
 *
 * @param string $token The token from the form to validate.
 * @return bool True if the token is valid, false otherwise.
 */
function validateCSRFToken($token) {
    // Ensure the session token exists and the submitted token is not empty before comparing
    return isset($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Checks if a user is currently logged in by seeing if a user_id is set in the session.
 *
 * @return bool True if the user is logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks for a "remember me" cookie and logs the user in if the token is valid.
 * This should be called on every page load before rendering content.
 */
function check_remember_me() {
    // Only check if the user is not already logged in and the cookie exists.
    if (!is_logged_in() && isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        $token_hash = hash('sha256', $token);

        $db = new Database();
        // Combine token and user lookup into a single, more efficient query.
        $result = $db->query(
            "SELECT u.id, u.username, u.role 
             FROM remember_me_tokens r
             JOIN users u ON r.user_id = u.id
             WHERE r.token_hash = ? AND r.expiry_date > NOW()",
            [$token_hash]
        );
        $user = $result->fetch_assoc();

        if ($user) {
            // Token is valid and user exists, log the user in.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
        } else {
            // If token is invalid or user doesn't exist, clear the now-useless cookie.
            setcookie('remember_me', '', time() - 3600, '/');
        }
    }
}
?>
