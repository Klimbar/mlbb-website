<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

// Ensure this script can only be run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Enable error logging for cron job
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/cron_error.log');

// Log cleanup activity
function log_cleanup($message) {
    file_put_contents(__DIR__ . '/../../logs/cleanup.log', date('[Y-m-d H:i:s]') . " " . $message . PHP_EOL, FILE_APPEND);
}

log_cleanup("Starting cleanup...");

try {
    $db = new Database();

    // --- OTP Cleanup ---
    log_cleanup("Starting expired OTP cleanup...");
    $db->query("DELETE FROM user_otps WHERE otp_expires_at < NOW()");
    log_cleanup("Deleted " . $db->getAffectedRows() . " expired OTP records.");
    log_cleanup("Expired OTP cleanup completed.");

    // --- Incomplete Registration Cleanup ---
    log_cleanup("Starting incomplete registration cleanup...");

    // Calculate the cutoff time for incomplete registrations
    $incomplete_cutoff_time = date('Y-m-d H:i:s', strtotime('- ' . INCOMPLETE_REGISTRATION_CLEANUP_HOURS . ' hour'));

    // Find incomplete users (is_verified = FALSE AND password is NULL or empty) older than the cutoff time
    $incomplete_users_result = $db->query(
        "SELECT id FROM users WHERE is_verified = FALSE AND (password IS NULL OR password = '') AND created_at < ?",
        [$incomplete_cutoff_time]
    );

    $incomplete_user_ids_to_delete = [];
    if ($incomplete_users_result) {
        while ($row = $incomplete_users_result->fetch_assoc()) {
            $incomplete_user_ids_to_delete[] = $row['id'];
        }
    }

    if (empty($incomplete_user_ids_to_delete)) {
        log_cleanup("No incomplete registrations found for cleanup.");
    } else {
        $placeholders = implode(',', array_fill(0, count($incomplete_user_ids_to_delete), '?'));

        // Start transaction for atomicity
        $db->begin_transaction();

        try {
            // Delete associated OTPs for these incomplete users
            $db->query("DELETE FROM user_otps WHERE user_id IN ($placeholders)", $incomplete_user_ids_to_delete);
            log_cleanup("Deleted " . $db->getAffectedRows() . " OTP records for incomplete users.");

            // Delete incomplete users
            $db->query("DELETE FROM users WHERE id IN ($placeholders)", $incomplete_user_ids_to_delete);
            log_cleanup("Deleted " . $db->getAffectedRows() . " incomplete user records.");

            $db->commit();
            log_cleanup("Incomplete registration cleanup completed successfully.");

        } catch (Exception $e) {
            $db->rollback();
            log_cleanup("Error during incomplete registration cleanup transaction: " . $e->getMessage());
            error_log("Incomplete registration cleanup transaction failed: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    log_cleanup("Fatal error during cleanup: " . $e->getMessage());
    error_log("Fatal cleanup error: " . $e->getMessage());
}

log_cleanup("Cleanup script finished.");

?>