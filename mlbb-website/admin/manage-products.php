<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';


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
    $isOutOfStock = isset($_POST['is_out_of_stock']) ? 1 : 0;

    try {
        $db->query(
            "UPDATE products SET name = ?, selling_price = ?, is_out_of_stock = ? WHERE id = ?",
            [$name, $sellingPrice, $isOutOfStock, $productId]
        );

        // Clear the product cache so changes appear immediately on the frontend
        $cacheFile = __DIR__ . '/../cache/products.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        $message = '<div class="alert alert-success">Product updated successfully! The cache has been cleared.</div>';

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
                            <th>Out of Stock</th>
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
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_out_of_stock_<?php echo $product['id']; ?>" name="is_out_of_stock" <?php echo $product['is_out_of_stock'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_out_of_stock_<?php echo $product['id']; ?>"></label>
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
        <a href="<?php echo BASE_URL; ?>/admin/update_products" class="btn btn-secondary">Fetch/Update Products from API</a>
    </div>

    <?php
    if (isset($_SESSION['admin_message'])) {
        echo $_SESSION['admin_message'];
        unset($_SESSION['admin_message']); // Clear the message after displaying
    }
    ?>
</div> <!-- Close container -->
</div> <!-- Close main-content -->
