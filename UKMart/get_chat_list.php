<?php
session_start();
include_once 'db_connect.php';

header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$buyer_id = $_SESSION['user_id'];

try {
    // Create read status table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_chat_read_status (
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        product_id INT NOT NULL,
        last_read_at DATETIME NOT NULL,
        PRIMARY KEY (buyer_id, seller_id, product_id),
        INDEX idx_buyer (buyer_id),
        INDEX idx_conversation (buyer_id, seller_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Get all unique conversations for this buyer with last message
    $stmt = $conn->prepare("
        SELECT 
            c.seller_id,
            c.product_id,
            s.fld_seller_name as seller_name,
            p.fld_product_name as product_name,
            s.fld_profile_pic,   
            (SELECT message FROM tbl_chat_ukmart 
             WHERE buyer_id = c.buyer_id 
             AND seller_id = c.seller_id 
             AND product_id = c.product_id 
             ORDER BY timestamp DESC LIMIT 1) as last_message,
            (SELECT message_type FROM tbl_chat_ukmart 
             WHERE buyer_id = c.buyer_id 
             AND seller_id = c.seller_id 
             AND product_id = c.product_id 
             ORDER BY timestamp DESC LIMIT 1) as last_message_type,
            (SELECT timestamp FROM tbl_chat_ukmart 
             WHERE buyer_id = c.buyer_id 
             AND seller_id = c.seller_id 
             AND product_id = c.product_id 
             ORDER BY timestamp DESC LIMIT 1) as last_message_time
        FROM (
            SELECT DISTINCT buyer_id, seller_id, product_id 
            FROM tbl_chat_ukmart 
            WHERE buyer_id = ?
        ) c
        LEFT JOIN tbl_sellers_ukmart s ON c.seller_id = s.fld_seller_id
        LEFT JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([$buyer_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
   // Format the response
$formatted_conversations = [];
foreach ($conversations as $conv) {
    // Seller profile picture path (check multiple extensions)
    $profile_pic = "profile/default_user.png"; // default
    $extensions = ['png','jpg','jpeg','JPG','JPEG','PNG'];
    if (!empty($conv['fld_profile_pic'])) {
        $base = pathinfo($conv['fld_profile_pic'], PATHINFO_FILENAME);
        foreach ($extensions as $ext) {
            $file = "profile/$base.$ext";  // <-- corrected path
            if (file_exists($file)) {
                $profile_pic = $file;
                break;
            }
        }
    }

    // Calculate unread count: messages from seller sent after last_read_at
    // First, get the last_read_at timestamp
    $read_status_stmt = $conn->prepare("
        SELECT last_read_at 
        FROM tbl_chat_read_status 
        WHERE buyer_id = ? AND seller_id = ? AND product_id = ?
    ");
    $read_status_stmt->execute([$buyer_id, $conv['seller_id'], $conv['product_id']]);
    $read_status = $read_status_stmt->fetch(PDO::FETCH_ASSOC);
    $last_read_at = $read_status ? $read_status['last_read_at'] : '1970-01-01 00:00:00';
    
    // Count unread messages (messages from seller sent after last_read_at)
    // Use > with a 1 second buffer to account for timing precision
    $unread_stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM tbl_chat_ukmart
        WHERE buyer_id = ? 
        AND seller_id = ? 
        AND product_id = ?
        AND sender_type = 'seller'
        AND timestamp > DATE_SUB(?, INTERVAL 1 SECOND)
    ");
    $unread_stmt->execute([
        $buyer_id, 
        $conv['seller_id'], 
        $conv['product_id'],
        $last_read_at
    ]);
    $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = isset($unread_result['unread_count']) ? (int)$unread_result['unread_count'] : 0;

    $formatted_conversations[] = [
        'seller_id' => $conv['seller_id'],
        'product_id' => $conv['product_id'],
        'seller_name' => $conv['seller_name'] ?: 'Seller',
        'product_name' => $conv['product_name'] ?: 'Product',
        'last_message' => $conv['last_message'] ?: 'No messages yet',
        'last_message_type' => $conv['last_message_type'] ?: 'text',
        'last_message_time' => $conv['last_message_time'] ?: date('Y-m-d H:i:s'),
        'unread_count' => $unread_count,
        'profile_pic' => $profile_pic
    ];
}


    
    echo json_encode([
        'success' => true,
        'conversations' => $formatted_conversations,
        'total' => count($formatted_conversations)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>