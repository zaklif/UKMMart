<?php
session_start();
include_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$buyer_id = isset($_POST['buyer_id']) ? (int)$_POST['buyer_id'] : 0;
$seller_id = isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$sender_type = isset($_POST['sender_type']) ? $_POST['sender_type'] : 'buyer';

if (empty($message) || $buyer_id <= 0 || $seller_id <= 0 || $product_id <= 0) {
    echo "Invalid parameters";
    exit;
}

// Get CURRENT status and quantity (DON'T RESET THEM!)
$status_stmt = $conn->prepare("SELECT COALESCE(status, 'pending') as status, quantity 
                               FROM tbl_chat_ukmart 
                               WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                               ORDER BY timestamp DESC LIMIT 1");
$status_stmt->execute([$buyer_id, $seller_id, $product_id]);
$current_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
$current_status = $current_data ? $current_data['status'] : 'pending';
$current_quantity = $current_data ? $current_data['quantity'] : null;

// Insert message with EXISTING quantity and status
$stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
                       (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, quantity, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$sender_name = ($sender_type === 'seller') ? 'Seller' : 'Buyer';
$stmt->execute([$sender_type, $sender_name, $message, 'text', $buyer_id, $seller_id, $product_id, $current_quantity, $current_status]);

echo "OK";
?>