<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serdihin Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/favicon-32x32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/assets/apple-touch-icon.png?v=2">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/assets/site.webmanifest?v=2">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/assets/favicon.ico?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css?v=1.9.12">
    <script>
        window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
        window.isLoggedIn = <?php echo json_encode(isset($_SESSION['user_id'])); ?>;
        window.lastPlayerId = <?php echo json_encode($_COOKIE['last_player_id'] ?? ''); ?>;
        window.lastZoneId = <?php echo json_encode($_COOKIE['last_zone_id'] ?? ''); ?>;
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/">
            <img src="<?php echo BASE_URL; ?>/assets/serdihin_logo.webp?v=2" alt="Serdihin Store Logo" height="30" class="me-1">
            Serdihin Store
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <div class="hamburger-icon">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/">Home</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/orders/history">My Orders</a>
                </li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/dashboard">Admin</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link logout-link" href="<?php echo BASE_URL; ?>/auth/logout">Logout</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/auth/login">Login</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>