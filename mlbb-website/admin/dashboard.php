<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();

// Get stats
$users = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc();
$orders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc();
$revenue = $db->query("SELECT SUM(amount) as total FROM orders WHERE order_status = 'completed'")->fetch_assoc();
$pending = $db->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, u.username, o.payment_status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container main-content">
    <h2>Admin Dashboard</h2>
    
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text fs-3"><?= $users['count'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Orders</h5>
                    <p class="card-text fs-3"><?= $orders['count'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <p class="card-text fs-3">₹<?= number_format($revenue['total'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Pending Orders</h5>
                    <p class="card-text fs-3"><?= $pending['count'] ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h3>Recent Orders</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Product</th>
                            <th>Amount</th>
                            <th>Order Status</th>
                            <th>Payment Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><a href="order-details.php?id=<?= $order['id'] ?>"><?= htmlspecialchars($order['order_id']) ?></a></td>
                                <td><?= htmlspecialchars($order['username']) ?></td>
                                <td><?= htmlspecialchars($order['product_name']) ?></td>
                                <td>₹<?= number_format($order['amount'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($order['order_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($order['payment_status'] ?? 'Failed') ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/orders.php" class="btn btn-primary mt-3">View All Orders</a>
            <a href="<?php echo BASE_URL; ?>/admin/manage-products.php" class="btn btn-secondary mt-3">Manage Products</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>