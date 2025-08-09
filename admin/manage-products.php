<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
//require_once __DIR__ . '/../includes/auth-check.php';


// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/");
    exit();
}

$db = new Database();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Invalid CSRF token.</div>';
    } else {
        try {
            // --- Handle Update All ---
            if (isset($_POST['update_all_products'])) {
                $all_products_data = $_POST['products'] ?? [];
                $db->begin_transaction();
                
                foreach ($all_products_data as $id => $data) {
                    $name = trim($data['name'] ?? '');
                    $sellingPrice = (float)($data['selling_price'] ?? 0);
                    $isOutOfStock = isset($data['is_out_of_stock']) ? 1 : 0;
                    $productId = (int)$id;
                    
                    $db->query(
                        "UPDATE products SET name = ?, selling_price = ?, is_out_of_stock = ? WHERE id = ?",
                        [$name, $sellingPrice, $isOutOfStock, $productId]
                    );
                }
                
                $db->commit();
                $message = '<div class="alert alert-success">All products updated successfully!</div>';
            }

            // --- Handle Single Product Update ---
            if (isset($_POST['update_single_product'])) {
                $productId = (int)$_POST['update_single_product'];
                $product_data = $_POST['products'][$productId] ?? null;

                if ($product_data) {
                    $name = trim($product_data['name'] ?? '');
                    $sellingPrice = (float)($product_data['selling_price'] ?? 0);
                    $isOutOfStock = isset($product_data['is_out_of_stock']) ? 1 : 0;

                    $db->query(
                        "UPDATE products SET name = ?, selling_price = ?, is_out_of_stock = ? WHERE id = ?",
                        [$name, $sellingPrice, $isOutOfStock, $productId]
                    );
                    $message = '<div class="alert alert-success">Product updated successfully!</div>';
                } else {
                    throw new Exception("Could not find data for the selected product.");
                }
            }

            // Clear the product cache if any update was made
            if (isset($_POST['update_all_products']) || isset($_POST['update_single_product'])) {
                $cacheFile = __DIR__ . '/../cache/products.json';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
            }

        } catch (Exception $e) {
            if (isset($db) && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                $db->rollback();
            }
            $message = '<div class="alert alert-danger">Error updating product(s): ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch all products
$products = $db->query("SELECT * FROM products ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <div class="container">
    <h2>Manage Diamond Pack Details</h2>

    <?php echo $message; ?>

    

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td>
                                        <input type="text" class="form-control" name="products[<?php echo $product['id']; ?>][name]" value="<?php echo htmlspecialchars($product['name']); ?>">
                                    </td>
                                    <td>R$<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control" name="products[<?php echo $product['id']; ?>][selling_price]" value="<?php echo htmlspecialchars($product['selling_price']); ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_out_of_stock_<?php echo $product['id']; ?>" name="products[<?php echo $product['id']; ?>][is_out_of_stock]" <?php echo $product['is_out_of_stock'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_out_of_stock_<?php echo $product['id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="submit" name="update_single_product" value="<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Update</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-start gap-2">
            <button type="submit" name="update_all_products" class="btn btn-success">Update All</button>
            <a href="<?php echo BASE_URL; ?>/admin/update_products" class="btn btn-info">Fetch/Update Products from API</a>
        </div>
    </form>

    <?php
    if (isset($_SESSION['admin_message'])) {
        // Wrap the message in a div with a top margin to add spacing
        echo '<div class="mt-3">' . $_SESSION['admin_message'] . '</div>';
        unset($_SESSION['admin_message']); // Clear the message after displaying
    }
    ?>
</div> <!-- Close container -->
</div> <!-- Close main-content -->
