<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';


$db = new Database();
$result = $db->query("
    SELECT o.* 
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
", [$_SESSION['user_id']]);

$orders = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container main-content">
    <h2>My Order History</h2>
    
    <?php if (empty($orders)): ?>
        <p>You haven't placed any orders yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Player ID</th>
                        <th>Amount</th>
                        <th>Order Status</th>
                        <th>Payment Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['product_name']) ?></td>
                            <td><?= htmlspecialchars($order['player_id']) ?></td>
                            <td>₹<?= number_format($order['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' ? 'warning' : 'danger') ?>">
                            <?= ucfirst($order['order_status']) ?>
                        </span>
                            </td>
                            <td>
                                <?php
                                $payment_status_display = $order['payment_status'] ?? 'N/A'; // Default to N/A if NULL
                                $badge_class = 'secondary'; // Default badge color for N/A

                                if ($payment_status_display === 'paid') {
                                    $badge_class = 'success';
                                } elseif ($payment_status_display === 'pending') {
                                    $badge_class = 'warning';
                                } elseif ($payment_status_display === 'failed') {
                                    $badge_class = 'danger';
                                }
                                ?>
                                <span class="badge bg-<?= $badge_class ?>">
                                    <?= ucfirst($payment_status_display) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/orders/details?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

