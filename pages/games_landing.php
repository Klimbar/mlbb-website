<?php $page_title ="Welcome to Serdihin"; ?>
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
    <h1 class="text-center mb-4">Available Games:</h1>
    <div class="row justify-content-center">
        <div class="col-lg-3 col-md-4 col-6 mb-4">
            <a href="<?php echo BASE_URL; ?>/mobile_legends" class="card-link">
                <div class="card h-100 shadow-sm game-card" style="cursor: pointer;">
                    <img src="<?php echo BASE_URL; ?>/assets/mlbb_card.webp" class="card-img-top" alt="Mobile Legends: Bang Bang">
                    <div class="card-body text-center">
                        <h5 class="card-title">Mobile Legends: Bang Bang</h5>
                    </div>
                </div>
            </a>
        </div>
        <!-- Future games can be added here -->
    </div>
</div>
