<?php
session_start();
include_once 'database.php'; // your PDO connection

// Dummy cart items
$cartItems = [
    [
        'id' => 1,
        'name' => '3 Pieces Mango Set - Casual Fit',
        'color' => '#0d6efd',
        'quantity' => 3,
        'price' => 99,
        'image' => 'https://via.placeholder.com/200'
    ],
    [
        'id' => 2,
        'name' => 'Zara Cardigan - Regular Fit',
        'color' => '#6b3e26',
        'quantity' => 1,
        'price' => 40,
        'image' => 'https://via.placeholder.com/200'
    ]
];

// Total price
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}

// Handle buttons
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['chat_seller'])) {
        $item_id = $_POST['item_id'];
        $item_name = '';
        foreach ($cartItems as $item) {
            if ($item['id'] == $item_id) {
                $item_name = $item['name'];
                break;
            }
        }
        $message = "Buyer wants to chat about: $item_name";

        $stmt = $pdo->prepare("INSERT INTO tbl_chat_ukmart (sender_type, sender_name, message, message_type) VALUES (?, ?, ?, ?)");
        $stmt->execute(['buyer', 'Buyer', $message, 'text']);

        header("Location: chat_buyer.php");
        exit;
    }

    if (isset($_POST['back_status'])) {
        header("Location: status_order.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add to Cart - UKM E-Commerce</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {font-family: "Poppins", sans-serif; background:#f8f9fa; margin:0; padding:0;}
.container {max-width:1400px; margin:50px auto; padding:40px;}
h1 {font-size:36px; font-weight:700; text-align:center; margin-bottom:40px; color:#222;}
.cart-card {display:flex; background:#fff; padding:20px; margin-bottom:20px; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.1); align-items:center; justify-content:space-between;}
.cart-left {display:flex; align-items:center;}
.cart-left img {width:200px; height:200px; object-fit:cover; border-radius:12px;}
.item-info {margin-left:30px;}
.item-info h5 {font-size:22px; margin-bottom:10px;}
.item-info p {font-size:18px; margin:4px 0;}
.color-dot {display:inline-block; width:18px; height:18px; border-radius:50%; vertical-align:middle; margin-left:10px; border:1px solid #ccc;}
.cart-right {text-align:right;}
.price {font-size:22px; font-weight:700; color:#0d6efd; margin-bottom:10px;}
.btn-custom {display:inline-block; padding:14px 28px; font-size:18px; font-weight:700; color:#fff; background-color:#0d6efd; border:none; border-radius:12px; cursor:pointer; transition:0.3s;}
.btn-custom:hover {background-color:#0b5ed7;}
.total-box {text-align:right; margin-top:30px; font-size:24px; font-weight:700; color:#000;}
.back-btn {display:block; width:250px; margin:50px auto; padding:16px 32px; font-size:18px; font-weight:700; background:#6c757d; color:#fff; border:none; border-radius:10px; cursor:pointer; transition:0.3s;}
.back-btn:hover {background:#5a6268;}
@media(max-width:1200px){
    .cart-card {flex-direction:column; align-items:flex-start;}
    .cart-left {flex-direction:column; align-items:flex-start;}
    .cart-left img {width:100%; height:auto; margin-bottom:15px;}
    .item-info {margin-left:0;}
    .cart-right {width:100%; text-align:left; margin-top:15px;}
    .total-box {text-align:left;}
}
</style>
</head>
<body>
<div class="container">
    <h1>Cart Summary</h1>

    <?php foreach ($cartItems as $item): ?>
    <div class="cart-card">
        <div class="cart-left">
            <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>">
            <div class="item-info">
                <h5><?= $item['name'] ?></h5>
                <p>Color: <span class="color-dot" style="background-color:<?= $item['color'] ?>"></span></p>
                <p>Quantity: <?= $item['quantity'] ?></p>
            </div>
        </div>
        <div class="cart-right">
            <div class="price">RM<?= $item['price'] * $item['quantity'] ?></div>
            <form method="post">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" name="chat_seller" class="btn-custom">Chat with Seller</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="total-box">Total: RM<?= $totalPrice ?></div>

    <form method="post">
        <button type="submit" name="back_status" class="back-btn">Back to Status Order</button>
    </form>
</div>
</body>
</html>
