<?php
// This script should be run once to set up the database.
// For security, delete this file after running it.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "Initializing database...\n";

try {
    $db = new Database();
    
    // Users table
    echo "Creating users table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Orders table
    echo "Creating orders table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_id VARCHAR(50) NOT NULL,
            player_id VARCHAR(50) NOT NULL,
            zone_id VARCHAR(50) NOT NULL,
            product_id VARCHAR(50) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            payment_method VARCHAR(50),
            payment_url VARCHAR(255),
            payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Payments table
    echo "Creating payments table...\n";
    $db->query("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            transaction_id VARCHAR(255),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'USD',
            payment_gateway VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            raw_response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )
    ");

    echo "Database initialization complete.\n";

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage());
}
?>