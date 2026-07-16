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
$seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$seller_id || !$product_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

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
    
    // Get the latest message timestamp in this conversation to mark all messages up to that point as read
    $latest_msg_stmt = $conn->prepare("
        SELECT MAX(timestamp) as latest_timestamp 
        FROM tbl_chat_ukmart 
        WHERE buyer_id = ? AND seller_id = ? AND product_id = ?
    ");
    $latest_msg_stmt->execute([$buyer_id, $seller_id, $product_id]);
    $latest_msg = $latest_msg_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Use the latest message timestamp, or current time if no messages exist
    $last_read_at = $latest_msg && $latest_msg['latest_timestamp'] 
        ? $latest_msg['latest_timestamp'] 
        : date('Y-m-d H:i:s');
    
    // Update or insert last_read_at timestamp
    $stmt = $conn->prepare("
        INSERT INTO tbl_chat_read_status (buyer_id, seller_id, product_id, last_read_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE last_read_at = ?
    ");
    $stmt->execute([$buyer_id, $seller_id, $product_id, $last_read_at, $last_read_at]);
    
    echo json_encode(['success' => true, 'message' => 'Messages marked as read', 'last_read_at' => $last_read_at]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

