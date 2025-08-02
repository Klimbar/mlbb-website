<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/db.php';

echo "--- Non-Pending Orders ---\n";

$db = new Database();

$non_pending_orders = $db->query("
    SELECT id, order_status, payment_status, created_at
    FROM orders
    WHERE order_status != 'pending'
    ORDER BY created_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

if (count($non_pending_orders) > 0) {
    echo "Found " . count($non_pending_orders) . " non-pending orders.\n";
    print_r($non_pending_orders);
} else {
    echo "No non-pending orders found.\n";
}
?>