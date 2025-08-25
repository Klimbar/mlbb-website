<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';


// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();

// Get stats
$users = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc();
$todays_orders = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURRENT_DATE()")->fetch_assoc();
$todays_revenue = $db->query("SELECT SUM(amount) as total FROM orders WHERE order_status = 'completed' AND DATE(created_at) = CURRENT_DATE()")->fetch_assoc();
$pending = $db->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc();
$monthly_revenue = $db->query("SELECT SUM(amount) as total FROM orders WHERE order_status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc();
$monthly_orders = $db->query("SELECT COUNT(*) as count FROM orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc();

// Recent orders
$recentOrders = $db->query("
    SELECT o.*, u.username, o.payment_status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="admin-main-content main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?php echo BASE_URL; ?>/admin/orders?filter=today" class="btn btn-info">Today's Orders</a>
            <a href="<?php echo BASE_URL; ?>/admin/orders" class="btn btn-primary">View All Orders</a>
            <a href="<?php echo BASE_URL; ?>/admin/manage-products" class="btn btn-secondary">Manage Products</a>
        </div>
        <h2>Admin Dashboard</h2>
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text fs-3"><?= $users['count'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Today's Orders</h5>
                        <p class="card-text fs-3"><?= $todays_orders['count'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Today's Revenue</h5>
                        <p class="card-text fs-3">₹<?= number_format($todays_revenue['total'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Pending Orders</h5>
                        <p class="card-text fs-3"><?= $pending['count'] ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Orders</h5>
                        <p class="card-text fs-3"><?= $monthly_orders['count'] ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Revenue</h5>
                        <p class="card-text fs-3">₹<?= number_format($monthly_revenue['total'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h3>Recent Orders</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive table-responsive-cards">
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
                                    <td data-label="Order ID"><a href="order-details?id=<?= $order['id'] ?>"><?= htmlspecialchars($order['order_id']) ?></a></td>
                                    <td data-label="User"><?= htmlspecialchars($order['username']) ?></td>
                                    <td data-label="Product"><?= htmlspecialchars($order['product_name']) ?></td>
                                    <td data-label="Amount">₹<?= number_format($order['amount'], 2) ?></td>
                                    <td data-label="Order Status">
                                        <span class="badge bg-<?= $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' || $order['order_status'] === 'processing' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($order['order_status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Payment Status">
                                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($order['payment_status'] ?? 'Failed') ?>
                                        </span>
                                    </td>
                                    <td data-label="Date"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>