<?php
// This script is intended to be run from the command line or as a cron job.
// It cleans up expired "remember me" tokens from the database.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $db = new Database();
    
    // SQL to delete expired tokens
    $sql = "DELETE FROM remember_me_tokens WHERE expiry_date <= NOW()";
    
    $statement = $db->prepare($sql);
    $statement->execute();
    
    $deleted_rows = $statement->affected_rows;
    
    echo "Cleanup complete. Deleted " . $deleted_rows . " expired tokens." . PHP_EOL;

} catch (Exception $e) {
    // Log the error to the standard error stream
    fwrite(STDERR, "Error during token cleanup: " . $e->getMessage() . PHP_EOL);
    exit(1); // Exit with a non-zero status code to indicate failure
}

exit(0); // Success
?>