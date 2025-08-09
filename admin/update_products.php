<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $db = new Database();
    $db->query("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            api_pack_name VARCHAR(255) NULL,
            price DECIMAL(10, 2) NOT NULL,
            selling_price DECIMAL(10, 2) NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Check if 'api_pack_name' column exists before trying to add it.
    // This prevents errors on subsequent runs of the script.
    $column_check = $db->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'api_pack_name'
    ")->fetch_assoc();

    if ($column_check['count'] == 0) {
        $db->query("ALTER TABLE products ADD COLUMN api_pack_name VARCHAR(255) NULL AFTER name");
    }

require_once __DIR__ . '/../includes/api_helpers.php';

function getBaseApiParams(): array {
    return [
        'uid' => API_UID,
        'email' => API_EMAIL,
        'time' => time()
    ];
}

$params = getBaseApiParams() + ['product' => 'mobilelegends'];
$params['sign'] = generateSign($params, API_KEY);

$response = callApi('/smilecoin/api/productlist', $params);

if ($response && isset($response['data']['product'])) {
    $products = $response['data']['product'];
    // $db = new Database(); // Already initialized above

    foreach ($products as $product) {
        $db->query("
            INSERT INTO products (product_id, name, api_pack_name, price, selling_price)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                api_pack_name = VALUES(api_pack_name),
                price = VALUES(price)
        ", [
            $product['id'],
            $product['spu'], // for 'name'
            $product['spu'], // for 'api_pack_name'
            $product['price'],
            $product['price'] // Default selling_price to price
        ]);
    }

    // Clear the product cache so changes appear immediately on the frontend
    $cacheFile = __DIR__ . '/../cache/products.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    $_SESSION['admin_message'] = '<div class="alert alert-success">Products updated successfully! The product cache has been cleared.</div>';
} else {
    $_SESSION['admin_message'] = '<div class="alert alert-danger">Failed to fetch products from the API. The cache was not cleared.</div>';
}
} catch (Exception $e) {
    $_SESSION['admin_message'] = '<div class="alert alert-danger">Error updating products: ' . $e->getMessage() . '</div>';
}

header('Location: ' . BASE_URL . '/admin/manage-products');
exit();
