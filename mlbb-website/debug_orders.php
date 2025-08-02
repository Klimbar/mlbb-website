<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/db.php';

echo "--- Time Debug ---\n";

// Set timezone to UTC for consistency
date_default_timezone_set('UTC');

$php_time = date('Y-m-d H:i:s');
echo "PHP (UTC) Time:         " . $php_time . "\n";

$db = new Database();
$mysql_time = $db->query("SELECT NOW()")->fetch_row()[0];
echo "MySQL Server Time:      " . $mysql_time . "\n";

$five_minutes_ago_db = $db->query("SELECT NOW() - INTERVAL 5 MINUTE")->fetch_row()[0];
echo "Cutoff Time (DB):       " . $five_minutes_ago_db . "\n";

echo "\n--- All Pending Orders ---\n";

$pending_orders = $db->query("
    SELECT id, order_status, payment_status, created_at
    FROM orders
    WHERE order_status = 'pending'
    ORDER BY created_at ASC
")->fetch_all(MYSQLI_ASSOC);

if (count($pending_orders) > 0) {
    echo "Found " . count($pending_orders) . " pending orders.\n";
    print_r($pending_orders);
} else {
    echo "No pending orders found.\n";
}
?>