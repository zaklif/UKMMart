<?php
session_start();
include('database.php');

if (!isset($_SESSION['user_id'])) {
    die("Please log in");
}

$buyer_id = $_SESSION['user_id'];
$seller_id = isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($seller_id <= 0 || $product_id <= 0 || $quantity <= 0) {
    die("Invalid order parameters");
}

// CHECK IF ORDER ALREADY PLACED (PREVENT DUPLICATE ORDERS)
$order_check = $conn->prepare("SELECT COUNT(*) FROM tbl_chat_ukmart 
                               WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                               AND message_type = 'order_placement'");
$order_check->execute([$buyer_id, $seller_id, $product_id]);
$order_already_exists = $order_check->fetchColumn() > 0;

if ($order_already_exists) {
    $_SESSION['error'] = "You have already placed an order for this product!";
    header("Location: chatbuyer.php?product_id=$product_id&seller_id=$seller_id");
    exit;
}

// Validate quantity against stock
$prod_stmt = $conn->prepare("SELECT fld_product_quantity, fld_product_name, fld_product_price FROM tbl_products_ukmart WHERE fld_product_id = ?");
$prod_stmt->execute([$product_id]);
$product = $prod_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found");
}

if ($quantity > $product['fld_product_quantity']) {
    $_SESSION['error'] = "Cannot order $quantity units. Only {$product['fld_product_quantity']} available.";
    header("Location: chatbuyer.php?product_id=$product_id&seller_id=$seller_id");
    exit;
}

// Check if chat conversation already exists (any messages between buyer and seller for this product)
$check_stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_chat_ukmart 
                              WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
$check_stmt->execute([$buyer_id, $seller_id, $product_id]);
$chat_exists = $check_stmt->fetchColumn() > 0;

// Get current status (if chat exists)
$current_status = 'pending';
if ($chat_exists) {
    $status_stmt = $conn->prepare("SELECT COALESCE(status, 'pending') as status FROM tbl_chat_ukmart 
                                   WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                   ORDER BY timestamp DESC LIMIT 1");
    $status_stmt->execute([$buyer_id, $seller_id, $product_id]);
    $status_row = $status_stmt->fetch(PDO::FETCH_ASSOC);
    if ($status_row) {
        $current_status = $status_row['status'];
    }
}

// If chat doesn't exist, create initial greeting message
if (!$chat_exists) {
    $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
                           (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, quantity, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['buyer', 'Buyer', 'Hello! I am interested in this product.', 'text', 
                   $buyer_id, $seller_id, $product_id, NULL, 'pending']);
}

// Insert order placement message (THIS IS THE KEY - ALWAYS INSERT, NEVER UPDATE)
$total_price = $product['fld_product_price'] * $quantity;
$stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
                       (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, quantity, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$message = "🛒 ORDER PLACED: $quantity unit(s) of {$product['fld_product_name']}. Total: RM " . number_format($total_price, 2);
$stmt->execute(['buyer', 'Buyer', $message, 'order_placement', $buyer_id, $seller_id, $product_id, $quantity, $current_status]);

// REDUCE CART QUANTITY OR REMOVE FROM CART
// Check if this product is in the cart
$cart_check = $conn->prepare("SELECT cart_id, quantity as cart_quantity FROM tbl_cart WHERE user_id = ? AND product_id = ?");
$cart_check->execute([$buyer_id, $product_id]);
$cart_item = $cart_check->fetch(PDO::FETCH_ASSOC);

if ($cart_item) {
    $cart_quantity = (int)$cart_item['cart_quantity'];
    $cart_id = $cart_item['cart_id'];
    
    if ($quantity >= $cart_quantity) {
        // Remove entire cart item if order quantity >= cart quantity
        $remove_cart = $conn->prepare("DELETE FROM tbl_cart WHERE cart_id = ?");
        $remove_cart->execute([$cart_id]);
    } else {
        // Reduce cart quantity by order quantity
        $new_cart_quantity = $cart_quantity - $quantity;
        $update_cart = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE cart_id = ?");
        $update_cart->execute([$new_cart_quantity, $cart_id]);
    }
    
    // Store flag to update cart badge via sessionStorage
    $_SESSION['cart_updated'] = true;
}

$_SESSION['success'] = "Order placed successfully! Waiting for seller's payment QR code.";
// Add cart_updated flag to URL so JavaScript can detect it
header("Location: chatbuyer.php?product_id=$product_id&seller_id=$seller_id&cart_updated=1");
exit;
?>