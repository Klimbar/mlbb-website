<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';
//require_once __DIR__ . '/../includes/auth-check.php';


// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header("Location: " . BASE_URL . "/admin/orders");
    exit();
}

// Get order details
$order = $db->query("
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
", [$order_id])->fetch_assoc();

if (!$order) {
    header("Location: " . BASE_URL . "/admin/orders");
    exit();
}

// Handle status update
if ($_POST) {
    $old_order_status = $order['order_status'];
    $old_payment_status = $order['payment_status'];

    if (isset($_POST['update_status'])) {
        $new_status = $_POST['order_status'];
        update_order_status($db, $order_id, $new_status, $old_order_status, $old_payment_status, $old_payment_status, 'Admin manual update');
        header("Location: " . BASE_URL . "/admin/order-details.php?id=" . $order_id);
        exit();
    }
    if (isset($_POST['update_payment_status'])) {
        $new_payment_status = $_POST['payment_status'];
        update_order_status($db, $order_id, $old_order_status, $old_order_status, $new_payment_status, $old_payment_status, 'Admin manual update');
        header("Location: " . BASE_URL . "/admin/order-details.php?id=" . $order_id);
        exit();
    }
}
?>

<div class="container main-content">
    <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center mb-4">
        <h2 class="mb-2 mb-sm-0">Order Details - <?= htmlspecialchars($order['order_id']) ?></h2>
        <a href="<?php echo BASE_URL; ?>/admin/orders" class="btn btn-primary">← Back to Orders</a>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Order Information</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Order ID:</label>
                        <span><?= htmlspecialchars($order['order_id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Player ID:</label>
                        <span><?= htmlspecialchars($order['player_id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Zone ID:</label>
                        <span><?= htmlspecialchars($order['zone_id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Product:</label>
                        <span><?= htmlspecialchars($order['product_name']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Amount:</label>
                        <span>₹<?= number_format($order['amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Payment Status:</label>
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                            <?= ucfirst($order['payment_status'] ?? 'Failed') ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Order Status:</label>
                        <span class="badge bg-<?= $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' || $order['order_status'] === 'processing' ? 'warning' : 'danger') ?>">
                            <?= ucfirst($order['order_status']) ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <label class="fw-bold text-secondary">Order Date:</label>
                        <span><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Customer Information</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <label class="fw-bold text-secondary">Username:</label>
                        <span><?= htmlspecialchars($order['username']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <label class="fw-bold text-secondary">Email:</label>
                        <span><?= htmlspecialchars($order['email']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Update Payment Status</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-end">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="flex-grow-1">
                            <label for="payment_status" class="form-label">Current Status:</label>
                            <select name="payment_status" id="payment_status" class="form-select">
                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <button type="submit" name="update_payment_status" class="btn btn-success">Update Status</button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Update Order Status</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-end">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="flex-grow-1">
                            <label for="order_status" class="form-label">Current Status:</label>
                            <select name="order_status" id="order_status" class="form-select">
                                <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="completed" <?= $order['order_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="failed" <?= $order['order_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-success">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>