<?php
session_start();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = $_POST['product_id'];
    $pname = $_POST['product_name'];
    $price = $_POST['product_price'];
    $img = $_POST['product_image'];
    $qty = max(1, (int)$_POST['quantity']);

    if (isset($_SESSION['cart'][$pid])) {
        $_SESSION['cart'][$pid]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][$pid] = [
            'id' => $pid,
            'name' => $pname,
            'price' => $price,
            'image' => $img,
            'quantity' => $qty
        ];
    }

    echo json_encode(['cart' => $_SESSION['cart']]);
    exit;
}
?>
