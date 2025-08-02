<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

// This script should be run by a cron job every minute.

$db = new Database();

// Find pending orders older than 5 minutes using the database's clock
$orders_to_fail = $db->query("
    SELECT id
    FROM orders
    WHERE (order_status = 'pending' OR order_status = 'failed')
    AND (payment_status IS NULL OR payment_status = 'pending')
    AND created_at < NOW() - INTERVAL 5 MINUTE
")->fetch_all(MYSQLI_ASSOC);

if (count($orders_to_fail) > 0) {
    $order_ids = array_column($orders_to_fail, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    
    $db->query("
        UPDATE orders
        SET order_status = 'failed', payment_status = 'failed'
        WHERE id IN ($placeholders)
    ", $order_ids);

    echo "Updated " . count($order_ids) . " orders to 'failed'.
";
} else {
    echo "No pending orders to update.
";
}
?>