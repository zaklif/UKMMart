<?php
session_start();
include_once 'db_connect.php';

header('Content-Type: application/json');

// Verify user is logged in as seller
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get seller_id
try {
    $stmt = $conn->prepare("SELECT fld_seller_id FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
    $stmt->execute([$user_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seller) {
        echo json_encode(['success' => false, 'error' => 'Not a seller']);
        exit;
    }
    
    $seller_id = $seller['fld_seller_id'];
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

try {
    // Get all unique conversations grouped by product
    $stmt = $conn->prepare("
        SELECT 
            c.product_id,
            p.fld_product_name as product_name,
            p.fld_product_image,
            COUNT(DISTINCT c.buyer_id) as buyer_count,
            (SELECT COUNT(*) FROM tbl_chat_ukmart 
             WHERE seller_id = c.seller_id 
             AND product_id = c.product_id 
             AND sender_type = 'buyer'
             AND timestamp > COALESCE(
                 (SELECT MAX(last_read_at) FROM tbl_chat_read_status 
                  WHERE seller_id = c.seller_id AND product_id = c.product_id),
                 '1970-01-01 00:00:00'
             )) as unread_count,
            (SELECT message FROM tbl_chat_ukmart 
             WHERE seller_id = c.seller_id 
             AND product_id = c.product_id 
             ORDER BY timestamp DESC LIMIT 1) as last_message,
            (SELECT timestamp FROM tbl_chat_ukmart 
             WHERE seller_id = c.seller_id 
             AND product_id = c.product_id 
             ORDER BY timestamp DESC LIMIT 1) as last_message_time
        FROM (
            SELECT DISTINCT seller_id, product_id, buyer_id
            FROM tbl_chat_ukmart 
            WHERE seller_id = ?
        ) c
        LEFT JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
        GROUP BY c.product_id
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([$seller_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each product, get buyers
    $formatted_products = [];
    foreach ($products as $product) {
        // Get buyers for this product
        $buyer_stmt = $conn->prepare("
            SELECT DISTINCT
                c.buyer_id,
                u.fld_user_name as buyer_name,
                (SELECT COUNT(*) FROM tbl_chat_ukmart 
                 WHERE seller_id = ? 
                 AND product_id = ? 
                 AND buyer_id = c.buyer_id
                 AND sender_type = 'buyer'
                 AND timestamp > COALESCE(
                     (SELECT last_read_at FROM tbl_chat_read_status 
                      WHERE seller_id = ? AND product_id = ? AND buyer_id = c.buyer_id),
                     '1970-01-01 00:00:00'
                 )) as unread_count,
                (SELECT message FROM tbl_chat_ukmart 
                 WHERE seller_id = ? 
                 AND product_id = ? 
                 AND buyer_id = c.buyer_id
                 ORDER BY timestamp DESC LIMIT 1) as last_message,
                (SELECT timestamp FROM tbl_chat_ukmart 
                 WHERE seller_id = ? 
                 AND product_id = ? 
                 AND buyer_id = c.buyer_id
                 ORDER BY timestamp DESC LIMIT 1) as last_message_time
            FROM tbl_chat_ukmart c
            JOIN tbl_user_ukmart u ON c.buyer_id = u.fld_user_id
            WHERE c.seller_id = ? AND c.product_id = ?
            GROUP BY c.buyer_id
            ORDER BY last_message_time DESC
        ");
        
        $buyer_stmt->execute([
            $seller_id, $product['product_id'],
            $seller_id, $product['product_id'], $seller_id, $product['product_id'],
            $seller_id, $product['product_id'],
            $seller_id, $product['product_id']
        ]);
        $buyers = $buyer_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Product image path
        $product_img = "images/default.png";
        if (!empty($product['fld_product_image'])) {
            $extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
            $base = pathinfo($product['fld_product_image'], PATHINFO_FILENAME);
            foreach ($extensions as $ext) {
                $file = "images/{$base}.{$ext}";
                if (file_exists($file)) {
                    $product_img = $file;
                    break;
                }
            }
        }
        
        $formatted_products[] = [
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'] ?: 'Product',
            'product_image' => $product_img,
            'buyer_count' => (int)$product['buyer_count'],
            'unread_count' => (int)$product['unread_count'],
            'last_message' => $product['last_message'] ?: 'No messages yet',
            'last_message_time' => $product['last_message_time'] ?: date('Y-m-d H:i:s'),
            'buyers' => array_map(function($b) {
                return [
                    'buyer_id' => $b['buyer_id'],
                    'buyer_name' => $b['buyer_name'] ?: 'Buyer',
                    'unread_count' => (int)$b['unread_count'],
                    'last_message' => $b['last_message'] ?: 'No messages yet',
                    'last_message_time' => $b['last_message_time'] ?: date('Y-m-d H:i:s')
                ];
            }, $buyers)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $formatted_products,
        'total' => count($formatted_products)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
