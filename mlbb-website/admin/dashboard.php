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

// Get stats
$users = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc();
$orders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc();
$revenue = $db->query("SELECT SUM(amount) as total FROM orders WHERE status = 'completed'")->fetch_assoc();
$pending = $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, u.username 
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container">
    <h2>Admin Dashboard</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <p><?= $users['count'] ?></p>
        </div>
        
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p><?= $orders['count'] ?></p>
        </div>
        
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <p>$<?= number_format($revenue['total'] ?? 0, 2) ?></p>
        </div>
        
        <div class="stat-card">
            <h3>Pending Orders</h3>
            <p><?= $pending['count'] ?></p>
        </div>
    </div>
    
    <div class="recent-orders">
        <h3>Recent Orders</h3>
        
        <table class="order-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['username']) ?></td>
                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                        <td>$<?= number_format($order['amount'], 2) ?></td>
                        <td>
                            <span class="status-badge <?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <a href="/admin/orders.php" class="btn">View All Orders</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>