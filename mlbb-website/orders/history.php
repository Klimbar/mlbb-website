<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$db = new Database();
$result = $db->query("
    SELECT o.*, p.transaction_id, p.status as payment_status, p.created_at as payment_date
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
", [$_SESSION['user_id']]);

$orders = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container">
    <h2>My Order History</h2>
    
    <?php if (empty($orders)): ?>
        <p>You haven't placed any orders yet.</p>
    <?php else: ?>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Product</th>
                    <th>Player ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
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
                        <td>$<?= number_format($order['amount'], 2) ?></td>
                        <td>
                            <span class="status-badge <?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($order['payment_status']): ?>
                                <span class="payment-status <?= $order['payment_status'] ?>">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                        <td>
                            <a href="/orders/details.php?id=<?= $order['id'] ?>" class="btn btn-sm">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>