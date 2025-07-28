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

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header("Location: /admin/orders.php");
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
    header("Location: /admin/orders.php");
    exit();
}

// Handle status update
if ($_POST && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $db->query("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
    header("Location: /admin/order-details.php?id=" . $order_id);
    exit();
}
?>

<div class="container">
    <div class="order-details-header">
        <h2>Order Details - #<?= htmlspecialchars($order['order_id']) ?></h2>
        <a href="/admin/orders.php" class="btn btn-secondary">← Back to Orders</a>
    </div>
    
    <div class="order-details-grid">
        <div class="order-info">
            <h3>Order Information</h3>
            <div class="info-row">
                <label>Order ID:</label>
                <span><?= htmlspecialchars($order['order_id']) ?></span>
            </div>
            <div class="info-row">
                <label>Player ID:</label>
                <span><?= htmlspecialchars($order['player_id']) ?></span>
            </div>
            <div class="info-row">
                <label>Zone ID:</label>
                <span><?= htmlspecialchars($order['zone_id']) ?></span>
            </div>
            <div class="info-row">
                <label>Product:</label>
                <span><?= htmlspecialchars($order['product_name']) ?></span>
            </div>
            <div class="info-row">
                <label>Amount:</label>
                <span>$<?= number_format($order['amount'], 2) ?></span>
            </div>
            <div class="info-row">
                <label>Payment Method:</label>
                <span><?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <label>Payment Status:</label>
                <span class="status-badge <?= $order['payment_status'] ?>">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </div>
            <div class="info-row">
                <label>Order Date:</label>
                <span><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
            </div>
        </div>
        
        <div class="customer-info">
            <h3>Customer Information</h3>
            <div class="info-row">
                <label>Username:</label>
                <span><?= htmlspecialchars($order['username']) ?></span>
            </div>
            <div class="info-row">
                <label>Email:</label>
                <span><?= htmlspecialchars($order['email']) ?></span>
            </div>
        </div>
        
        <div class="status-update">
            <h3>Update Order Status</h3>
            <form method="POST" class="status-form">
                <div class="form-group">
                    <label for="status">Current Status:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="failed" <?= $order['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>
</div>

<style>
.order-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.order-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.order-info, .customer-info, .status-update {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.status-update {
    grid-column: span 2;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row label {
    font-weight: bold;
    color: #495057;
}

.status-form {
    display: flex;
    gap: 1rem;
    align-items: end;
}

.form-group {
    flex: 1;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .order-details-grid {
        grid-template-columns: 1fr;
    }
    
    .status-update {
        grid-column: span 1;
    }
    
    .order-details-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .status-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
