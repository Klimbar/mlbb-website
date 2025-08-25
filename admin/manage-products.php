<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    error_log("User role check failed. Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'));
    header("Location: " . BASE_URL . "/");
    exit();
}

// Ensure we have a CSRF token
generateCSRFToken(); // Always ensure we have a valid token
$page_csrf_token = $_SESSION['csrf_token'];

$db = new Database();
$message = '';

function handleImageUpload($file, &$image_path, $old_image_path = null) {
    // Validate that the file array contains all required keys
    if (!isset($file['name'], $file['tmp_name'], $file['error'], $file['type'], $file['size'])) {
        throw new Exception('Invalid file upload data.');
    }
    
    // Proceed only if a file has been uploaded
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        // If there's an old image and it's a file, delete it
        if ($old_image_path && file_exists(__DIR__ . '/../' . $old_image_path) && is_file(__DIR__ . '/../' . $old_image_path)) {
            if (!unlink(__DIR__ . '/../' . $old_image_path)) {
                error_log("Failed to delete old image: " . $old_image_path);
            }
        }

        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create uploads directory.');
            }
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
        if (in_array($file['type'], $allowed_types)) {
            $image_path = 'assets/uploads/' . uniqid() . '-' . basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $image_path)) {
                throw new Exception('Failed to move uploaded file.');
            }
        } else {
            throw new Exception('Invalid file type: ' . $file['type']);
        }
    } elseif (isset($file) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        // An error other than 'no file uploaded' occurred
        throw new Exception('File upload error with code: ' . $file['error']);
    }
    // If no file was uploaded (UPLOAD_ERR_NO_FILE), we simply do nothing.
    // The existing image path will be preserved.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The validateCSRFToken function now handles token regeneration
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Invalid CSRF token. Please try again.</div>';
    } else {
        try {
            if (isset($_POST['update_single_product'])) {
                $productId = (int)$_POST['update_single_product'];
                $product_data = $_POST['products'][$productId] ?? null;

                if ($product_data) {
                    $name = trim($product_data['name'] ?? '');
                    $description = trim($product_data['description'] ?? '');
                    $sellingPrice = filter_var($product_data['selling_price'], FILTER_VALIDATE_FLOAT);
                    $isOutOfStock = isset($product_data['is_out_of_stock']) ? 1 : 0;

                    // Validate inputs
                    if ($sellingPrice === false) {
                        $message = '<div class="alert alert-danger">Invalid selling price for product ID ' . $productId . '.</div>';
                    } else if ($sellingPrice < 0) {
                        $message = '<div class="alert alert-danger">Selling price cannot be negative for product ID ' . $productId . '.</div>';
                    } else if (empty($name)) {
                        $message = '<div class="alert alert-danger">Product name is required for product ID ' . $productId . '.</div>';
                    } else {
                        $image_path = $product_data['existing_image'] ?? '';
                        if (isset($_FILES['products']['error'][$productId]['image']) && $_FILES['products']['error'][$productId]['image'] !== UPLOAD_ERR_NO_FILE) {
                            $old_image_query = $db->query("SELECT image FROM products WHERE id = ?", [$productId]);
                            $old_image_path = $old_image_query->fetch_assoc()['image'] ?? null;

                            $file = [
                                'name' => $_FILES['products']['name'][$productId]['image'],
                                'type' => $_FILES['products']['type'][$productId]['image'],
                                'tmp_name' => $_FILES['products']['tmp_name'][$productId]['image'],
                                'error' => $_FILES['products']['error'][$productId]['image'],
                                'size' => $_FILES['products']['size'][$productId]['image'],
                            ];
                            handleImageUpload($file, $image_path, $old_image_path);
                        }

                        // Execute the update and check if it was successful
                        try {
                            $update_result = $db->query(
                                "UPDATE products SET name = ?, description = ?, image = ?, selling_price = ?, is_out_of_stock = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?",
                                [$name, $description, $image_path, $sellingPrice, $isOutOfStock, $productId]
                            );
                            
                            // For UPDATE statements, we now get the affected rows count directly
                            if (is_int($update_result)) {
                                if ($update_result > 0) {
                                    $message = '<div class="alert alert-success">Product updated successfully!</div>';
                                } else {
                                    $message = '<div class="alert alert-info">No changes were made to the product.</div>';
                                }
                                $cacheFile = __DIR__ . '/../cache/products.json';
                                if (file_exists($cacheFile)) {
                                    if (!unlink($cacheFile)) {
                                        error_log("Warning: Could not delete cache file: " . $cacheFile);
                                    }
                                }
                            } else {
                                $message = '<div class="alert alert-danger">Failed to update product. Please check server logs.</div>';
                            }
                        } catch (Exception $e) {
                            error_log("Database error updating product ID " . $productId . ": " . $e->getMessage());
                            $message = '<div class="alert alert-danger">Database error occurred. Please check server logs.</div>';
                        }
                    }
                }
            }
            
            if (isset($_POST['update_all_products'])) {
                $all_products_data = $_POST['products'] ?? [];
                $db->begin_transaction();
                
                foreach ($all_products_data as $id => $data) {
                    $name = trim($data['name'] ?? '');
                    $description = trim($data['description'] ?? '');
                    $sellingPrice = filter_var($data['selling_price'], FILTER_VALIDATE_FLOAT);
                    $isOutOfStock = isset($data['is_out_of_stock']) ? 1 : 0;
                    $productId = (int)$id;

                    // Validate inputs
                    if ($sellingPrice === false) {
                        error_log("Invalid selling price for product ID " . $productId);
                        continue; // Skip this product
                    } else if ($sellingPrice < 0) {
                        error_log("Negative selling price for product ID " . $productId);
                        continue; // Skip this product
                    } else if (empty($name)) {
                        error_log("Empty name for product ID " . $productId);
                        continue; // Skip this product
                    }

                    $image_path = $data['existing_image'] ?? '';
                    if (isset($_FILES['products']['name'][$id]['image']) && $_FILES['products']['error'][$id]['image'] === UPLOAD_ERR_OK) {
                        $old_image_query = $db->query("SELECT image FROM products WHERE id = ?", [$productId]);
                        $old_image_path = $old_image_query->fetch_assoc()['image'] ?? null;

                        $file = [
                            'name' => $_FILES['products']['name'][$id]['image'],
                            'type' => $_FILES['products']['type'][$id]['image'],
                            'tmp_name' => $_FILES['products']['tmp_name'][$id]['image'],
                            'error' => $_FILES['products']['error'][$id]['image'],
                            'size' => $_FILES['products']['size'][$id]['image'],
                        ];
                        handleImageUpload($file, $image_path, $old_image_path);
                    }
                    
                    try {
                        $db->query(
                            "UPDATE products SET name = ?, description = ?, image = ?, selling_price = ?, is_out_of_stock = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?",
                            [$name, $description, $image_path, $sellingPrice, $isOutOfStock, $productId]
                        );
                    } catch (Exception $e) {
                        error_log("Database error: " . $e->getMessage());
                        throw new Exception("Failed to update product ID $productId: " . $e->getMessage());
                    }
                }
                
                $db->commit();
                $message = '<div class="alert alert-success">All products updated successfully!</div>';
                $cacheFile = __DIR__ . '/../cache/products.json';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
            }

            if (isset($_POST['add_custom_product'])) {
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $product_ids = trim($_POST['product_ids'] ?? '');
                $sellingPrice = filter_var($_POST['selling_price'], FILTER_VALIDATE_FLOAT);
                $image_path = '';

                // Validate inputs
                if ($sellingPrice === false) {
                    $message = '<div class="alert alert-danger">Invalid selling price.</div>';
                } else if ($sellingPrice < 0) {
                    $message = '<div class="alert alert-danger">Selling price cannot be negative.</div>';
                } else if (empty($name)) {
                    $message = '<div class="alert alert-danger">Product name is required.</div>';
                } else if (empty($product_ids)) {
                    $message = '<div class="alert alert-danger">Product IDs are required.</div>';
                } else {
                    if (isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        handleImageUpload($_FILES['image'], $image_path, null);
                    }

                    try {
                        $db->query(
                            "INSERT INTO custom_products (name, description, image, product_ids, selling_price) VALUES (?, ?, ?, ?, ?)",
                            [$name, $description, $image_path, $product_ids, $sellingPrice]
                        );
                        $message = '<div class="alert alert-success">Custom product added successfully!</div>';
                        $cacheFile = __DIR__ . '/../cache/products.json';
                        if (file_exists($cacheFile)) {
                            unlink($cacheFile);
                        }
                    } catch (Exception $e) {
                        error_log("Database error: " . $e->getMessage());
                        $message = '<div class="alert alert-danger">Database error occurred. Please check server logs.</div>';
                    }
                }
            }

            if (isset($_POST['update_custom_product'])) {
                $id = (int)$_POST['update_custom_product'];
                $product_data = $_POST['custom_products'][$id] ?? null;

                if ($product_data) {
                    $name = trim($product_data['name'] ?? '');
                    $description = trim($product_data['description'] ?? '');
                    $product_ids = trim($product_data['product_ids'] ?? '');
                    $sellingPrice = filter_var($product_data['selling_price'], FILTER_VALIDATE_FLOAT);
                    $isOutOfStock = isset($product_data['is_out_of_stock']) ? 1 : 0;
                    $image_path = $product_data['existing_image'] ?? '';

                    // Validate inputs
                    if ($sellingPrice === false) {
                        $message = '<div class="alert alert-danger">Invalid selling price for custom product ID ' . $id . '.</div>';
                    } else if ($sellingPrice < 0) {
                        $message = '<div class="alert alert-danger">Selling price cannot be negative for custom product ID ' . $id . '.</div>';
                    } else if (empty($name)) {
                        $message = '<div class="alert alert-danger">Product name is required for custom product ID ' . $id . '.</div>';
                    } else if (empty($product_ids)) {
                        $message = '<div class="alert alert-danger">Product IDs are required for custom product ID ' . $id . '.</div>';
                    } else {
                        if (isset($_FILES['custom_products']['error'][$id]['image']) && $_FILES['custom_products']['error'][$id]['image'] !== UPLOAD_ERR_NO_FILE) {
                            $old_image_query = $db->query("SELECT image FROM custom_products WHERE id = ?", [$id]);
                            $old_image_path = $old_image_query->fetch_assoc()['image'] ?? null;

                            $file = [
                                'name' => $_FILES['custom_products']['name'][$id]['image'],
                                'type' => $_FILES['custom_products']['type'][$id]['image'],
                                'tmp_name' => $_FILES['custom_products']['tmp_name'][$id]['image'],
                                'error' => $_FILES['custom_products']['error'][$id]['image'],
                                'size' => $_FILES['custom_products']['size'][$id]['image'],
                            ];
                            handleImageUpload($file, $image_path, $old_image_path);
                        }

                        try {
                            $db->query(
                                "UPDATE custom_products SET name = ?, description = ?, image = ?, product_ids = ?, selling_price = ?, is_out_of_stock = ? WHERE id = ?",
                                [$name, $description, $image_path, $product_ids, $sellingPrice, $isOutOfStock, $id]
                            );
                            $message = '<div class="alert alert-success">Custom product updated successfully!</div>';
                            $cacheFile = __DIR__ . '/../cache/products.json';
                            if (file_exists($cacheFile)) {
                                unlink($cacheFile);
                            }
                        } catch (Exception $e) {
                            error_log("Database error: " . $e->getMessage());
                            $message = '<div class="alert alert-danger">Database error occurred. Please check server logs.</div>';
                        }
                    }
                }
            }
            
            if (isset($_POST['update_all_custom_products'])) {
                $all_products_data = $_POST['custom_products'] ?? [];
                $db->begin_transaction();
                
                foreach ($all_products_data as $id => $data) {
                    $name = trim($data['name'] ?? '');
                    $description = trim($data['description'] ?? '');
                    $product_ids = trim($data['product_ids'] ?? '');
                    $sellingPrice = filter_var($data['selling_price'], FILTER_VALIDATE_FLOAT);
                    $isOutOfStock = isset($data['is_out_of_stock']) ? 1 : 0;
                    $productId = (int)$id;

                    // Validate inputs
                    if ($sellingPrice === false) {
                        error_log("Invalid selling price for custom product ID " . $productId);
                        continue; // Skip this product
                    } else if ($sellingPrice < 0) {
                        error_log("Negative selling price for custom product ID " . $productId);
                        continue; // Skip this product
                    } else if (empty($name)) {
                        error_log("Empty name for custom product ID " . $productId);
                        continue; // Skip this product
                    } else if (empty($product_ids)) {
                        error_log("Empty product IDs for custom product ID " . $productId);
                        continue; // Skip this product
                    }

                    $image_path = $data['existing_image'] ?? '';
                    if (isset($_FILES['custom_products']['error'][$id]['image']) && $_FILES['custom_products']['error'][$id]['image'] !== UPLOAD_ERR_NO_FILE) {
                        $old_image_query = $db->query("SELECT image FROM custom_products WHERE id = ?", [$id]);
                        $old_image_path = $old_image_query->fetch_assoc()['image'] ?? null;

                        $file = [
                            'name' => $_FILES['custom_products']['name'][$id]['image'],
                            'type' => $_FILES['custom_products']['type'][$id]['image'],
                            'tmp_name' => $_FILES['custom_products']['tmp_name'][$id]['image'],
                            'error' => $_FILES['custom_products']['error'][$id]['image'],
                            'size' => $_FILES['custom_products']['size'][$id]['image'],
                        ];
                        handleImageUpload($file, $image_path, $old_image_path);
                    }
                    
                    try {
                        $db->query(
                            "UPDATE custom_products SET name = ?, description = ?, image = ?, product_ids = ?, selling_price = ?, is_out_of_stock = ? WHERE id = ?",
                            [$name, $description, $image_path, $product_ids, $sellingPrice, $isOutOfStock, $productId]
                        );
                    } catch (Exception $e) {
                        error_log("Database error: " . $e->getMessage());
                        throw new Exception("Failed to update custom product ID $productId: " . $e->getMessage());
                    }
                }
                
                $db->commit();
                $message = '<div class="alert alert-success">All custom products updated successfully!</div>';
                $cacheFile = __DIR__ . '/../cache/products.json';
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
            }

            if (isset($_POST['delete_custom_product'])) {
                $id = (int)$_POST['delete_custom_product'];

                try {
                    // Fetch image path before deleting the product
                    $image_query = $db->query("SELECT image FROM custom_products WHERE id = ?", [$id]);
                    $image_path = $image_query->fetch_assoc()['image'] ?? null;

                    $db->query("DELETE FROM custom_products WHERE id = ?", [$id]);

                    // Delete image file if it exists
                    if ($image_path && file_exists(__DIR__ . '/../' . $image_path) && is_file(__DIR__ . '/../' . $image_path)) {
                        if (!unlink(__DIR__ . '/../' . $image_path)) {
                            error_log("Failed to delete custom product image: " . $image_path);
                        }
                    }

                    $message = '<div class="alert alert-success">Custom product deleted successfully!</div>';
                    $cacheFile = __DIR__ . '/../cache/products.json';
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                } catch (Exception $e) {
                    error_log("Database error deleting custom product ID " . $id . ": " . $e->getMessage());
                    $message = '<div class="alert alert-danger">Database error occurred while deleting custom product. Please check server logs.</div>';
                }
            }

        } catch (Exception $e) {
            if (isset($db) && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                $db->rollback();
            }
            error_log("Product update error: " . $e->getMessage());
            $message = '<div class="alert alert-danger">Error updating product: ' . $e->getMessage() . '</div>';
        }
    }
}

$products = $db->query("SELECT * FROM products ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
$custom_products = $db->query("SELECT * FROM custom_products ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    /* Ensure price inputs remain visible on all screen sizes */
    @media (max-width: 768px) {
        /* Force minimum width for all price inputs */
        .table-responsive .price-input {
            min-width: 100px !important;
        }
        
        /* Ensure input groups don't collapse */
        .table-responsive .input-group.flex-nowrap {
            min-width: 120px !important;
        }
    }
    
    @media (max-width: 576px) {
        .table-responsive .price-input {
            min-width: 80px !important;
        }
        
        .table-responsive .input-group.flex-nowrap {
            min-width: 100px !important;
        }
    }
    
    @media (max-width: 400px) {
        .table-responsive .price-input {
            min-width: 70px !important;
        }
        
        .table-responsive .input-group.flex-nowrap {
            min-width: 90px !important;
        }
    }
    
    /* Ensure inputs are always visible */
    .table-responsive .price-input {
        min-width: 80px;
    }
</style>

<div class="admin-main-content main-content">
    <div class="container-fluid">
    <h2><strong>Manage Custom Products</strong></h2>

    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3>Add New Custom Product</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $page_csrf_token ?>">
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label for="name">Product Name</label><input type="text" class="form-control" id="name" name="name" required></div></div>
                    <div class="col-md-3"><div class="form-group"><label for="description">Description</label><input type="text" class="form-control" id="description" name="description"></div></div>
                    <div class="col-md-3"><div class="form-group"><label for="image">Image</label><input type="file" class="form-control" id="image" name="image"></div></div>
                    <div class="col-md-3"><div class="form-group"><label for="product_ids">Product IDs</label><input type="text" class="form-control" id="product_ids" name="product_ids" required></div></div>
                    <div class="col-md-3"><div class="form-group"><label for="selling_price">Selling Price (₹)</label><input type="number" step="0.01" class="form-control price-input" id="selling_price" name="selling_price" required></div></div>
                </div>
                <button type="submit" name="add_custom_product" class="btn btn-primary mt-3">Add Custom Product</button>
            </form>
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $page_csrf_token ?>">
        <div class="card">
            <div class="card-header">
                <h3>Custom Product List</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive table-responsive-cards">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Image</th><th>Product IDs</th><th>Price</th><th>Out of Stock</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($custom_products as $product): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td data-label="Name"><input type="text" class="form-control" name="custom_products[<?php echo $product['id']; ?>][name]" value="<?php echo htmlspecialchars($product['name']); ?>"></td>
                                    <td data-label="Description"><input type="text" class="form-control" name="custom_products[<?php echo $product['id']; ?>][description]" value="<?php echo htmlspecialchars($product['description']); ?>"></td>
                                    <td data-label="Image">
                                        <input type="file" name="custom_products[<?php echo $product['id']; ?>][image]" class="form-control">
                                        <input type="hidden" name="custom_products[<?php echo $product['id']; ?>][existing_image]" value="<?php echo htmlspecialchars($product['image']); ?>">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= BASE_URL . '/' . htmlspecialchars($product['image']) ?>" alt="Product Image" style="max-width: 100px; margin-top: 10px;">
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Product IDs"><input type="text" class="form-control" name="custom_products[<?php echo $product['id']; ?>][product_ids]" value="<?php echo htmlspecialchars($product['product_ids']); ?>"></td>
                                    <td data-label="Price">
    <div class="input-group flex-nowrap">
        <span class="input-group-text">₹</span>
        <input type="number" step="0.01" class="form-control price-input" name="custom_products[<?php echo $product['id']; ?>][selling_price]" value="<?php echo htmlspecialchars($product['selling_price']); ?>">
    </div>
</td>
                                    <td data-label="Out of Stock">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="custom_is_out_of_stock_<?php echo $product['id']; ?>" name="custom_products[<?php echo $product['id']; ?>][is_out_of_stock]" <?php echo $product['is_out_of_stock'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="custom_is_out_of_stock_<?php echo $product['id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td data-label="Actions" class="table-actions">
                                        <button type="submit" name="update_custom_product" value="<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Update</button>
                                        <button type="submit" name="delete_custom_product" value="<?php echo $product['id']; ?>" class="btn btn-danger btn-sm delete-custom-product-btn" data-product-id="<?php echo $product['id']; ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-start gap-2">
            <button type="submit" name="update_all_custom_products" class="btn btn-success">Update All</button>
        </div>
    </form>
    
    <hr class="my-5">

    <h2><strong>Manage Regular Pack</strong></h2>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $page_csrf_token ?>">
        <div class="card">
            <div class="card-header">
                <h3>Product List</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive table-responsive-cards">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pack Name</th>
                                <th>Description</th>
                                <th>Image</th>
                                <th>API Cost (R$)</th>
                                <th>Selling Price (₹)</th>
                                <th>Out of Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td data-label="Pack Name"><input type="text" class="form-control" name="products[<?php echo $product['id']; ?>][name]" value="<?php echo htmlspecialchars($product['name']); ?>"></td>
                                    <td data-label="Description"><input type="text" class="form-control" name="products[<?php echo $product['id']; ?>][description]" value="<?php echo htmlspecialchars($product['description']); ?>"></td>
                                    <td data-label="Image">
                                        <input type="file" name="products[<?php echo $product['id']; ?>][image]" class="form-control">
                                        <input type="hidden" name="products[<?php echo $product['id']; ?>][existing_image]" value="<?php echo htmlspecialchars($product['image']); ?>">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= BASE_URL . '/' . htmlspecialchars($product['image']) ?>" alt="Product Image" style="max-width: 100px; margin-top: 10px;">
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="API Cost (R$)">R$<?php echo number_format($product['price'], 2); ?></td>
                                    <td data-label="Selling Price (₹)">
                                        <div class="input-group flex-nowrap">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" class="form-control price-input" name="products[<?php echo $product['id']; ?>][selling_price]" value="<?php echo htmlspecialchars($product['selling_price']); ?>">
                                        </div>
                                    </td>
                                    <td data-label="Out of Stock">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_out_of_stock_<?php echo $product['id']; ?>" name="products[<?php echo $product['id']; ?>][is_out_of_stock]" <?php echo $product['is_out_of_stock'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_out_of_stock_<?php echo $product['id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td data-label="Actions"><button type="submit" name="update_single_product" value="<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Update</button></td>
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
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-custom-product-btn');

            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    const productId = this.dataset.productId;
                    if (!confirm(`Are you sure you want to delete custom product ID ${productId}?`)) {
                        event.preventDefault(); // Prevent form submission if user cancels
                    }
                });
            });
        });
    </script>
</div>
</div>
