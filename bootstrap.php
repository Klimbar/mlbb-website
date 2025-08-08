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
$csp .= "style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://use.fontawesome.com 'unsafe-inline'; ";

// Font sources
$csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://use.fontawesome.com; ";

// Image sources
$csp .= "img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";

// Other restrictions
$csp .= "object-src 'none'; frame-ancestors 'none'; form-action 'self';";

header("Content-Security-Policy: $csp");

}
?>
