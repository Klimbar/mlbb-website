<?php
// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 86400); // 24 hours (prevents session expiring during active use)
ini_set('session.gc_maxlifetime', 86400);  // 24 hours

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

// --- REMEMBER ME AUTHENTICATION CHECK ---
// Run auth check on all pages except API requests
if (!$isApiRequest && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    require_once __DIR__ . '/includes/db.php';
    
    $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
    
    if (count($cookie_parts) === 2) {
        list($user_id, $token) = $cookie_parts;
        
        if ($user_id && $token) {
            $db = new Database();
            $token_hash_from_cookie = hash('sha256', $token);
            
            // Find the specific token
            $result = $db->query("SELECT * FROM remember_me_tokens WHERE user_id = ? AND expiry_date > NOW()", [$user_id]);
            $saved_tokens = $result->fetch_all(MYSQLI_ASSOC);
            
            $matched_token = null;
            foreach ($saved_tokens as $saved_token) {
                if (hash_equals($saved_token['token_hash'], $token_hash_from_cookie)) {
                    $matched_token = $saved_token;
                    break;
                }
            }
            
            if ($matched_token) {
                // Get user info first
                $user_result = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
                $user = $user_result->fetch_assoc();
                
                if ($user) {
                    // Log the user in first
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Generate new token
                    $new_token = bin2hex(random_bytes(32));
                    $new_token_hash = hash('sha256', $new_token);
                    $expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                    
                    // Start transaction to ensure atomicity
                    $db->query("START TRANSACTION");
                    
                    try {
                        // Check and limit devices to 3 before creating new token
                        $device_count = $db->query("SELECT COUNT(*) as count FROM remember_me_tokens WHERE user_id = ? AND expiry_date > NOW()", [$user['id']])->fetch_assoc()['count'];
                        
                        if ($device_count >= 3) {
                            // Remove oldest tokens to make room (keep 2, so new one makes 3)
                            $db->query("DELETE FROM remember_me_tokens 
                                       WHERE user_id = ? AND expiry_date > NOW() 
                                       ORDER BY id ASC 
                                       LIMIT ?", [$user['id'], $device_count - 2]);
                        }
                        
                        // Insert new token first
                        $db->query("INSERT INTO remember_me_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)", 
                                  [$user['id'], $new_token_hash, $expiry_date]);
                        
                        // Delete old token only after new one is created
                        $db->query("DELETE FROM remember_me_tokens WHERE id = ?", [$matched_token['id']]);
                        
                        // Commit transaction
                        $db->query("COMMIT");
                        
                        // Set new cookie
                        setcookie('remember_me', $user['id'] . ':' . $new_token, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                            'httponly' => true,
                            'samesite' => 'Strict'
                        ]);
                        
                    } catch (Exception $e) {
                        // Rollback on error
                        $db->query("ROLLBACK");
                        error_log("Remember me token rotation failed: " . $e->getMessage());
                        
                        // Clear invalid cookie
                        setcookie('remember_me', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                            'httponly' => true,
                            'samesite' => 'Strict'
                        ]);
                    }
                } else {
                    // User not found, clear cookie
                    setcookie('remember_me', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
            } else {
                // Invalid token, clear cookie
                setcookie('remember_me', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
        }
    }
}
?>