<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';


// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();
$orders = $db->query("
    SELECT o.*, u.username
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container main-content">
    <h2>Order Management</h2>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <!-- Table headers -->
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Order Status</th>
                    <th>Payment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            
            <!-- Order rows -->
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_id']) ?></td>
                    <td><?= htmlspecialchars($order['username']) ?></td>
                    <td><?= htmlspecialchars($order['product_name']) ?></td>
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
                    <td>
                        <a href="<?php echo BASE_URL; ?>/admin/order-details?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


