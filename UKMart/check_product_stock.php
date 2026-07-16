<?php
include('db_connect.php');

// This file returns JSON data about product stock status
// It will be called periodically by JavaScript to check for updates

header('Content-Type: application/json');

try {
    // Get all products with their current stock status
    $stmt = $conn->prepare("
        SELECT 
            fld_product_id,
            fld_product_name,
            fld_product_quantity,
            is_available,
            CASE 
                WHEN fld_product_quantity <= 0 OR is_available = 0 THEN 1 
                ELSE 0 
            END as is_sold_out
        FROM tbl_products_ukmart
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a simple array mapping product IDs to stock status
    $stock_data = [];
    foreach ($products as $product) {
        $stock_data[$product['fld_product_id']] = [
            'quantity' => (int)$product['fld_product_quantity'],
            'is_available' => (int)$product['is_available'],
            'is_sold_out' => (bool)$product['is_sold_out'],
            'name' => $product['fld_product_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $stock_data,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stock data',
        'timestamp' => time()
    ]);
}
?>