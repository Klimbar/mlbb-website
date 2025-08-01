<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php'; // For BASE_URL if needed for logging/redirects

// This script is intended to be run via cron or manually by an admin.
// It synchronizes order_status with payment_status for pending orders.

try {
    $db = new Database();

    // Find orders where order_status is 'pending' but payment_status is 'failed'
    $orders_to_update = $db->query("
        SELECT o.id
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.order_status = 'pending'
          AND p.status = 'failed'
    ")->fetch_all(MYSQLI_ASSOC);

    if (!empty($orders_to_update)) {
        foreach ($orders_to_update as $order) {
            $db->query("
                UPDATE orders
                SET order_status = 'failed'
                WHERE id = ?
            ", [$order['id']]);
        }
        echo "Updated " . count($orders_to_update) . " order(s) to 'failed' status.\n";
    } else {
        echo "No pending orders with failed payments found to update.\n";
    }

} catch (Exception $e) {
    echo "Error synchronizing order statuses: " . $e->getMessage() . "\n";
}

?>