<?php
/**
 * This file contains common helper functions used across the application.
 */

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