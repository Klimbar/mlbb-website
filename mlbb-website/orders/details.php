<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

$order_pk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();

$order = $db->query(
    "SELECT o.*, p.transaction_id, p.status as payment_status 
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?",
    [$order_pk_id, $_SESSION['user_id']]
)->fetch_assoc();

if (!$order) {
    header("Location: /orders/history.php");
    exit;
}
?>

<div class="container">
    <h2>Order Details</h2>
    
    <div class="order-details">
        <p><strong>Order ID:</strong> <?= htmlspecialchars($order['order_id']) ?></p>
        <p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
        <p><strong>Player ID:</strong> <?= htmlspecialchars($order['player_id']) ?></p>
        <p><strong>Zone ID:</strong> <?= htmlspecialchars($order['zone_id']) ?></p>
        <p><strong>Amount:</strong> $<?= number_format($order['amount'], 2) ?></p>
        <p><strong>Status:</strong> 
            <span class="status-badge <?= $order['status'] ?>">
                <?= ucfirst($order['status']) ?>
            </span>
        </p>
        
        <?php if ($order['transaction_id']): ?>
            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($order['transaction_id']) ?></p>
        <?php endif; ?>
        
        <?php if ($order['status'] === 'pending'): ?>
            <p>Your payment is being processed. Please wait or contact support.</p>
        <?php elseif ($order['status'] === 'completed'): ?>
            <div class="alert alert-success">
                <p>Your diamonds have been delivered to your account!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <a href="/orders/history.php" class="btn">Back to Order History</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>