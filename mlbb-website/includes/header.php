<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MLBB Diamond Top-Up</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="/">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/orders/history.php">My Orders</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="/admin/dashboard.php">Admin</a>
            <?php endif; ?>
            <a href="/auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="/auth/login.php">Login</a>
            <a href="/auth/register.php">Register</a>
        <?php endif; ?>
    </nav>
