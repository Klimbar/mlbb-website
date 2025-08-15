<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
//require_once __DIR__ . '/../includes/auth-check.php';


// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();

// --- Pagination Logic ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Number of orders per page
$offset = ($page - 1) * $limit;

// Get total number of orders for calculating total pages
$total_orders_result = $db->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc();
$total_orders = $total_orders_result['count'];
$total_pages = ceil($total_orders / $limit);

$orders = $db->query("
    SELECT o.*, u.username
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
", [$limit, $offset])->fetch_all(MYSQLI_ASSOC);
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
                    <td><a href="<?php echo BASE_URL; ?>/admin/order-details?id=<?= $order['id'] ?>"><?= htmlspecialchars($order['order_id']) ?></a></td>
                    <td><?= htmlspecialchars($order['username']) ?></td>
                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                    <td>₹<?= number_format($order['amount'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' || $order['order_status'] === 'processing' ? 'warning' : 'danger') ?>">
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

        <!-- Pagination Controls -->
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo BASE_URL; ?>/admin/orders?page=<?= $page - 1 ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>
                    </li>
                <?php endif; ?>

                <?php
                $window = 2; // Number of links to show on each side
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)):
                ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>"><a class="page-link" href="<?php echo BASE_URL; ?>/admin/orders?page=<?= $i ?>"><?= $i ?></a></li>
                <?php
                    elseif ($i == $page - $window - 1 || $i == $page + $window + 1):
                ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php
                    endif;
                endfor;
                ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo BASE_URL; ?>/admin/orders?page=<?= $page + 1 ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>
