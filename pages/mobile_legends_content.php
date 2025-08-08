<?php
$page_title = 'Mobile Legends: Bang Bang';
?>
<style>
.welcome-banner {
    background-color: var(--primary-accent);
    color: var(--card-bg); /* Using a light color from your theme for consistency */
    border: none;
}

.welcome-banner .welcome-text {
    font-size: 1.1rem;
}

</style>
<div class="container main-content">
    <!-- Show welcome message if logged in -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="welcome-banner p-3 mb-4 rounded shadow-sm d-flex justify-content-center align-items-center">
            <span class="welcome-text">👋 Welcome back, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</span>
        </div>
    <?php endif; ?>
  <!-- Main content -->
    <h1 id="page-title">Mobile Legends: Bang Bang</h1>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Account Details</h2>
                </div>
                <div class="card-body">
                    <form id="playerForm">
                        <div class="mb-3">
                            <label for="userid" class="form-label">Player ID:</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" id="userid" class="form-control" required value="" placeholder="Enter Game ID">
                        </div>
                        <div class="mb-3">
                            <label for="zoneid" class="form-label">Zone ID:</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" id="zoneid" class="form-control" required value="" placeholder="Enter Server ID">
                        </div>
                        <div class="d-grid gap-2 button-group-width">
                            <?php if (!empty($_COOKIE['last_player_id'])): ?>
                                <button type="button" id="useLastBtn" class="btn btn-secondary mb-2">Last Used IDs</button>
                            <?php endif; ?>
                            <button type="button" id="verifyBtn" class="btn btn-primary">Verify Player</button>
                        </div>
                        <div id="playerVerificationError" class="error hidden"></div>
                    </form>
                    <div id="playerInfo" class="mt-3"></div>
                    <!-- Order Status -->
                    <div id="orderStatus" class="hidden"></div>
                </div>
            </div>
        </div>
    </div>

  

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Select Diamond Package</h2>
                </div>
                <div class="card-body">
                    <div id="category-cards-container" class="row row-cols-4 g-2 mb-3"></div>
                    <hr class="my-4">
                    <div id="products" class="row row-cols-2 row-cols-md-2 row-cols-lg-2 g-4"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Section (only shown if logged in) -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 d-none" id="paymentSection">
                <div class="card-header">
                    <h2>Payment</h2>
                </div>
                <div class="card-body">
                    <div id="selectedProduct" class="mb-3"></div>
                    <div class="d-grid">
                        <button type="button" id="payNowBtn" class="btn btn-success">Pay Now</button>
                    </div>
                    <div id="paymentErrorStatus" class="hidden mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
