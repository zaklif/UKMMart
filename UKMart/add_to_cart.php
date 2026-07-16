<?php
session_start();
include('database.php');

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$user_id) {
    $_SESSION['error'] = "Please log in to add to cart.";
    header("Location: login.php");
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($product_id <= 0 || $quantity <= 0) {
    $_SESSION['error'] = "Invalid product or quantity.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// CHECK PRODUCT STOCK AVAILABILITY
$prod_stmt = $conn->prepare("SELECT fld_product_id, fld_product_name, fld_product_quantity, is_available 
                             FROM tbl_products_ukmart 
                             WHERE fld_product_id = ?");
$prod_stmt->execute([$product_id]);
$product = $prod_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = "Product not found.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Check if product is available
if ($product['fld_product_quantity'] <= 0 || $product['is_available'] == 0) {
    $_SESSION['error'] = "Sorry! '{$product['fld_product_name']}' is currently out of stock.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Check if requested quantity is available
if ($quantity > $product['fld_product_quantity']) {
    $_SESSION['error'] = "Only {$product['fld_product_quantity']} unit(s) available for '{$product['fld_product_name']}'.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Check if product already in cart
$stmt = $conn->prepare("SELECT * FROM tbl_cart WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user_id, $product_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Check if adding more would exceed stock
    $new_qty = $existing['quantity'] + $quantity;
    
    if ($new_qty > $product['fld_product_quantity']) {
        $_SESSION['error'] = "Cannot add {$quantity} more. Only {$product['fld_product_quantity']} unit(s) available (you already have {$existing['quantity']} in cart).";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Update quantity
    $update = $conn->prepare("UPDATE tbl_cart SET quantity = ?, created_at = NOW() WHERE cart_id = ?");
    $update->execute([$new_qty, $existing['cart_id']]);
    $_SESSION['success'] = "Cart updated! Now you have {$new_qty} unit(s) of '{$product['fld_product_name']}'.";
} else {
    // Insert new item
    $insert = $conn->prepare("INSERT INTO tbl_cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
    $insert->execute([$user_id, $product_id, $quantity]);
    $_SESSION['success'] = "'{$product['fld_product_name']}' added to cart!";
}

// Redirect back to previous page or cart
header("Location: cart.php");
exit;
?>