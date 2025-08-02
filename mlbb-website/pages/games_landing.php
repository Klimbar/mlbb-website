<?php
$page_title = 'Games';
?>

<div class="container main-content">
    <h1 class="text-center mb-4">Available Games</h1>
    <div class="row justify-content-center">
        <div class="col-lg-3 col-md-4 col-6 mb-4">
            <a href="<?php echo BASE_URL; ?>/mobile_legends" class="card-link">
                <div class="card h-100 shadow-sm game-card" style="cursor: pointer;">
                    <img src="<?php echo BASE_URL; ?>/assets/mlbb_card.jpeg" class="card-img-top" alt="Mobile Legends: Bang Bang">
                    <div class="card-body text-center">
                        <h5 class="card-title">Mobile Legends: Bang Bang</h5>
                        <p class="card-text">Top-up Diamonds</p>
                    </div>
                </div>
            </a>
        </div>
        <!-- Future games can be added here -->
    </div>
</div>