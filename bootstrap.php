<?php
// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load configuration
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Fallback BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://serdihin.com');
}

// Generate nonce
$nonce = base64_encode(random_bytes(16));
define('NONCE', $nonce);

// Detect API requests
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api') !== false || strpos($_SERVER['REQUEST_URI'], '/payments') !== false;

// Send security headers
if (!$isApiRequest && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $csp = "default-src 'self'; ";
    $csp .= "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://use.fontawesome.com 'nonce-{$nonce}'; ";
    $csp .= "style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://use.fontawesome.com 'unsafe-inline'; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://use.fontawesome.com; ";
    $csp .= "img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
    $csp .= "object-src 'none'; frame-ancestors 'none'; form-action 'self';";
    header("Content-Security-Policy: $csp");
}

// --- RACE-CONDITION SAFE REMEMBER ME CHECK ---
if (!$isApiRequest && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    require_once __DIR__ . '/includes/db.php';
    
    $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
    
    if (count($cookie_parts) === 2) {
        list($user_id, $token) = $cookie_parts;
        
        if ($user_id && $token) {
            $db = new Database();
            $token_hash_from_cookie = hash('sha256', $token);
            
            // Check if we already rotated this token recently (prevent race conditions)
            $session_key = 'token_rotated_' . $token_hash_from_cookie;
            if (isset($_SESSION[$session_key])) {
                // Token was already processed in this session - skip rotation
                return;
            }
            
            try {
                // Find the specific token
                $result = $db->query("SELECT * FROM remember_me_tokens WHERE user_id = ? AND token_hash = ? AND expiry_date > NOW()", 
                                   [$user_id, $token_hash_from_cookie]);
                $matched_token = $result->fetch_assoc();
                
                if ($matched_token) {
                    // Get user info
                    $user_result = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
                    $user = $user_result->fetch_assoc();
                    
                    if ($user) {
                        // Log the user in FIRST (before any token operations)
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Mark this token as processed to prevent race conditions
                        $_SESSION[$session_key] = time();
                        
                        // Only rotate token if it's older than 1 hour (reduces rotation frequency)
                        $token_age = time() - strtotime($matched_token['created_at']);
                        if ($token_age > 3600) { // 1 hour
                            
                            // Use database locking to prevent race conditions
                            $db->query("SELECT GET_LOCK('remember_me_rotation_$user_id', 10)");
                            
                            // Double-check token still exists (another tab might have rotated it)
                            $recheck = $db->query("SELECT id FROM remember_me_tokens WHERE id = ? AND expiry_date > NOW()", 
                                                [$matched_token['id']]);
                            
                            if ($recheck->fetch_assoc()) {
                                // Check device limit before creating new token
                                $device_count = $db->query("SELECT COUNT(*) as count FROM remember_me_tokens WHERE user_id = ? AND expiry_date > NOW()", 
                                                         [$user_id])->fetch_assoc()['count'];
                                
                                if ($device_count >= 3) {
                                    // Remove oldest tokens
                                    $db->query("DELETE FROM remember_me_tokens 
                                               WHERE user_id = ? AND expiry_date > NOW() 
                                               ORDER BY created_at ASC 
                                               LIMIT ?", [$user_id, $device_count - 2]);
                                }
                                
                                // Create new token
                                $new_token = bin2hex(random_bytes(32));
                                $new_token_hash = hash('sha256', $new_token);
                                $expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                                
                                // Insert new token
                                $db->query("INSERT INTO remember_me_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)", 
                                          [$user_id, $new_token_hash, $expiry_date]);
                                
                                // Delete old token
                                $db->query("DELETE FROM remember_me_tokens WHERE id = ?", [$matched_token['id']]);
                                
                                // Set new cookie (only if headers not sent)
                                if (!headers_sent()) {
                                    setcookie('remember_me', $user_id . ':' . $new_token, [
                                        'expires' => time() + (30 * 24 * 60 * 60),
                                        'path' => '/',
                                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                                        'httponly' => true,
                                        'samesite' => 'Strict'
                                    ]);
                                }
                            }
                            
                            // Release lock
                            $db->query("SELECT RELEASE_LOCK('remember_me_rotation_$user_id')");
                        }
                    } else {
                        // User not found - clear cookie
                        if (!headers_sent()) {
                            setcookie('remember_me', '', time() - 3600, '/', '', 
                                    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                        }
                    }
                } else {
                    // Token not found or expired - clear cookie
                    if (!headers_sent()) {
                        setcookie('remember_me', '', time() - 3600, '/', '', 
                                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    }
                }
                
            } catch (Exception $e) {
                // Log error but don't break the page
                error_log("Remember me error: " . $e->getMessage());
                
                // Clear problematic cookie
                if (!headers_sent()) {
                    setcookie('remember_me', '', time() - 3600, '/', '', 
                            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                }
            }
        }
    }
}
?>