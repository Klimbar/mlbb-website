<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';


$order_pk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();

$order = $db->query(
    'SELECT o.*, o.payment_status, o.order_status, p.transaction_id 
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?',
    [$order_pk_id, $_SESSION['user_id']]
)->fetch_assoc();

$transaction_id = $order['transaction_id'] ?? '';

if (!$order) {
    header("Location: " . BASE_URL . "/orders/history");
    exit;
}
?>

<div class="container main-content">
    <h2>Order Details</h2>
    
    <div class="card mb-4">
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Order ID:</strong>
                    <span><?php echo htmlspecialchars($order['order_id']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Product:</strong>
                    <span><?php echo htmlspecialchars($order['product_name']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Player ID:</strong>
                    <span><?php echo htmlspecialchars($order['player_id']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Zone ID:</strong>
                    <span><?php echo htmlspecialchars($order['zone_id']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Amount:</strong>
                    <span>₹<?php echo number_format($order['amount'], 2) ?></span>
                </li>
                
                <?php if (!empty($transaction_id)): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Transaction ID:</strong>
                    <span><?php echo htmlspecialchars($transaction_id) ?></span>
                </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Order Status:</strong>
                    <span class="badge bg-<?php echo $order['order_status'] === 'completed' ? 'success' : ($order['order_status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?php echo ucfirst($order['order_status']) ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Payment Status:</strong>
                    <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?php echo ucfirst(htmlspecialchars($order['payment_status'])) ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Order Date:</strong>
                    <span><?php echo date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                </li>
            </ul>
        </div>
    </div>
    
    <?php if ($order['order_status'] === 'pending' && $order['payment_status'] !== 'failed'): ?>
        <div class="alert alert-info mt-3">
            <p>Your payment is being processed. Please wait or contact support.</p>
        </div>
    <?php elseif ($order['order_status'] === 'completed'): ?>
        <div class="alert alert-success mt-3">
            <p>The order is successful and the product has been delivered to the account!</p>
        </div>
    <?php endif; ?>
    
    <a href="<?php echo BASE_URL; ?>/orders/history" class="btn btn-primary mt-3">Back to Order History</a>
</div>

