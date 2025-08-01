<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/header.php';

// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $productId = $_POST['product_id'];
    $name = $_POST['name'];
    $sellingPrice = $_POST['selling_price'];

    try {
        $db->query(
            "UPDATE products SET name = ?, selling_price = ? WHERE id = ?",
            [$name, $sellingPrice, $productId]
        );
        $message = '<div class="alert alert-success">Product updated successfully!</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error updating product: ' . $e->getMessage() . '</div>';
    }
}

// Fetch all products
$products = $db->query("SELECT * FROM products ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <div class="container">
    <h2>Manage Diamond Pack Prices</h2>

    <?php echo $message; ?>

    

    <div class="card">
        <div class="card-header">
            <h3>Product List</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pack Name</th>
                            <th>API Cost (R$)</th>
                            <th>Selling Price (₹)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <form method="POST" action="">
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($product['name']); ?>">
                                    </td>
                                    <td>R$<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" name="selling_price" value="<?php echo htmlspecialchars($product['selling_price']); ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="update_product" class="btn btn-primary btn-sm">Update</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <a href="<?php echo BASE_URL; ?>/admin/update_products.php" class="btn btn-secondary">Fetch/Update Products from API</a>
    </div>

    <?php
    if (isset($_SESSION['admin_message'])) {
        echo $_SESSION['admin_message'];
        unset($_SESSION['admin_message']); // Clear the message after displaying
    }
    ?>
</div> <!-- Close container -->
</div> <!-- Close main-content -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
