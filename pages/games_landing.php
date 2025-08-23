<?php 
require_once __DIR__ . '/../bootstrap.php';
$page_title ="Welcome to Serdihin"; 
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

    <div id="carouselExampleIndicators" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="<?php echo BASE_URL; ?>/assets/carousel/join-channel.webp" class="d-block w-100" alt="Join Channel">
            </div>
            <div class="carousel-item">
                <img src="<?php echo BASE_URL; ?>/assets/carousel/service-benefits.webp" class="d-block w-100" alt="Service Benefits">
            </div>
            <div class="carousel-item">
                <img src="<?php echo BASE_URL; ?>/assets/carousel/why-us.webp" class="d-block w-100" alt="Why Choose Us">
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>


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

    <h2 class="text-center mt-4 mb-4">Tools:</h2>
    <div class="card mb-4">
        <div class="card-body text-center">
            <a href="/region-check" class="btn btn-info" role="button">Region Checker</a>
        </div>
    </div>
</div>