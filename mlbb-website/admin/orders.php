<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: /");
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

<div class="container">
    <h2>Order Management</h2>
    
    <table class="order-table">
        <!-- Table headers -->
        <thead>
            <tr>
                <th>Order ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        
        <!-- Order rows -->
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $order['order_id'] ?></td>
                <td><?= htmlspecialchars($order['username']) ?></td>
                <td><?= htmlspecialchars($order['product_name']) ?></td>
                <td>$<?= number_format($order['amount'], 2) ?></td>
                <td>
                    <span class="status-badge <?= $order['status'] ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </td>
                <td>
                    <a href="/admin/order-details.php?id=<?= $order['id'] ?>" class="btn btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
