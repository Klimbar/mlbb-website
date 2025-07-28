<?php
// Start the session and load configuration. This must be done before any HTML output.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Initialize variables for last used IDs from the cookie for persistence across visits.
$last_player_id = $_COOKIE['last_player_id'] ?? '';
$last_zone_id = $_COOKIE['last_zone_id'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>
 
<div class="container">
    <!-- Show welcome message if logged in -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="alert alert-success">
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!
            (<a href="/auth/logout.php">Logout</a>)
        </div>
    <?php endif; ?>

    <!-- Main content -->
    <h1>Mobile Legends Diamond Top-Up</h1>

    <!-- Product Selection -->
    <div class="section">
        <h2>Select Diamond Package</h2>
        <div id="products" class="product-grid"></div>
    </div>

    <!-- Player Information (only shown if logged in) -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="section">
            <h2>Player Details</h2>
            <form id="playerForm">
                <div class="form-group">
                    <label for="userid">Player ID:</label>
                    <input type="text" id="userid" required value="<?= htmlspecialchars($last_player_id) ?>">
                </div>
                <div class="form-group">
                    <label for="zoneid">Zone ID:</label>
                    <input type="text" id="zoneid" required value="<?= htmlspecialchars($last_zone_id) ?>">
                </div>
                <div class="form-actions">
                    <button type="button" id="verifyBtn">Verify Player</button>
                    <?php if (!empty($last_player_id)): ?>
                        <button type="button" id="useLastBtn" class="btn-secondary">Use Last IDs</button>
                    <?php endif; ?>
                </div>
            </form>
            <div id="playerInfo" class="hidden"></div>
        </div>

        <!-- Payment Section -->
        <div class="section hidden" id="paymentSection">
            <div class="form-group">
                <label>Payment Method:</label>
                <div id="paymentMethodButtons" class="payment-methods">
                    <button type="button" class="payment-method-btn" data-value="pay0">Pay0 Gateway (UPI/Cards)</button>
                    <!-- Add other payment method buttons here -->
                </div>
            </div>
            <div id="selectedProduct"></div>
            <div>
                <button type="button" id="payNowBtn" class="btn">Pay Now</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Show login prompt if not logged in -->
        <div class="alert alert-info">
            Please <a href="/auth/login.php">login</a> to purchase diamonds.
        </div>
    <?php endif; ?>

    <!-- Order Status -->
    <div id="orderStatus" class="hidden"></div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
