<?php
session_start();
include_once 'database.php'; // your PDO connection

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$user_id) {
    die("Please log in to view your cart.");
}

// Fetch cart items with product info AND STOCK STATUS
$stmt = $conn->prepare("
SELECT c.cart_id, c.quantity, c.created_at,
       p.fld_product_id, p.fld_product_name, 
       p.fld_product_price, p.fld_product_image, p.fld_product_category,
       p.fld_seller_id, p.fld_product_quantity, p.is_available,
       CASE 
           WHEN p.fld_product_quantity <= 0 OR p.is_available = 0 THEN 'out_of_stock'
           WHEN c.quantity > p.fld_product_quantity THEN 'exceeds_stock'
           ELSE 'available'
       END as stock_status
FROM tbl_cart c
JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
WHERE c.user_id = ?
ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove item
    if (isset($_POST['remove_item'])) {
        $cart_id = $_POST['remove_item'];
        $del = $conn->prepare("DELETE FROM tbl_cart WHERE cart_id = ?");
        $del->execute([$cart_id]);
        header("Location: cart.php"); // refresh page
        exit;
    }

    // Chat seller logic...
}

// Calculate total price
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['fld_product_price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cart - UKM E-Commerce</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
:root {
  --primary-blue: #2563eb;
  --primary-blue-dark: #1e40af;
  --primary-blue-light: #3b82f6;
  --accent-orange: #f97316;
  --accent-green: #10b981;
  --bg-light: #f8fafc;
  --bg-white: #ffffff;
  --text-dark: #1e293b;
  --text-gray: #64748b;
  --border-color: #e2e8f0;
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
  --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.2);
  --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.25);
}

body {
    font-family: "Poppins", sans-serif;
    background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
    margin: 0;
    padding: 0;
    color: var(--text-dark);
}
.main-wrapper {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 15px;
}
.cart-shell {
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    border-radius: 24px;
    box-shadow: var(--shadow-xl);
    border: 2px solid var(--primary-blue-light);
    padding: 40px 35px;
    position: relative;
    overflow: hidden;
}
.cart-shell::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 25px;
    gap: 10px;
}
.cart-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.cart-header span {
    font-size: 14px;
    color: var(--text-gray);
}

/* Cart item card */
.cart-card {
    display: flex;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    padding:25px;
    margin-bottom:20px;
    border-radius:18px;
    border:2px solid var(--border-color);
    box-shadow: var(--shadow-md);
    align-items:center;
    justify-content:space-between;
    gap: 20px;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
}
.cart-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.4s ease;
}
.cart-card:hover::before {
    transform: scaleX(1);
}
.cart-card:hover {
    transform: translateY(-6px) scale(1.01);
    border-color: var(--primary-blue);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
}

/* Left section */
.cart-left {
    display:flex;
    align-items:center;
    gap:20px;
}
.cart-left img {
    width:160px;
    height:160px;
    object-fit:cover;
    border-radius:16px;
    border: 3px solid var(--primary-blue-light);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    transition: transform 0.4s ease;
}
.cart-card:hover .cart-left img {
    transform: scale(1.05) rotate(2deg);
}
.item-info h5 {
    font-size:20px;
    font-weight:600;
    margin-bottom:6px;
    color: var(--text-dark);
}
.item-category {
    display:inline-block;
    font-size:13px;
    padding:4px 10px;
    border-radius:999px;
    background:#eff6ff;
    color: var(--primary-blue);
    margin-bottom:6px;
    font-weight: 600;
}
.item-meta {
    font-size:14px;
    color: var(--text-gray);
}
.item-meta span {
    font-weight:600;
    color: var(--text-dark);
}

/* Right section */
.cart-right {
    text-align:right;
    min-width: 210px;
}
.price {
    font-size:22px;
    font-weight:700;
    color: var(--text-dark);
    margin-bottom:10px;
}
.price small {
    font-size:13px;
    color: var(--text-gray);
    font-weight:400;
}

/* Buttons */
.btn-cart {
    display:inline-block;
    padding:10px 16px;
    font-size:14px;
    font-weight:600;
    border-radius:999px;
    border:none;
    cursor:pointer;
    transition:0.2s;
}
.btn-chat {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color:#fff;
    margin-right:8px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    font-weight: 700;
}
.btn-chat:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
}
.btn-remove {
    background:#fee2e2;
    color:#b91c1c;
}
.btn-remove:hover {
    background:#fca5a5;
}

/* Total box */
.total-box {
    margin-top:30px;
    padding:25px 30px;
    border-radius:18px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 3px solid var(--primary-blue);
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap: wrap;
    gap: 10px;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
}
.total-box .label {
    font-size:1.3rem;
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.total-box .amount {
    font-size:2rem;
    font-weight:800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Back button */
.back-btn {
    display:block;
    width:250px;
    margin:30px auto 0;
    padding:15px 25px;
    font-size:16px;
    font-weight:700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color:#fff;
    border:none;
    border-radius:12px;
    cursor:pointer;
    transition: all 0.3s ease;
    text-align:center;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.back-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
}

/* Empty cart */
.empty-text {
    text-align:center;
    padding:50px 20px;
    font-size:18px;
    color: var(--text-gray);
}

/* Responsive */
@media(max-width:992px){
    .cart-card {
        flex-direction:column;
        align-items:flex-start;
    }
    .cart-right {
        text-align:left;
        width:100%;
    }
}
@media(max-width:576px){
    .cart-shell {
        padding:25px 18px;
    }
    .cart-left img {
        width:120px;
        height:120px;
    }
}
/* Stock warning badges */
.stock-warning {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    z-index: 10;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.stock-warning.out-of-stock {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.stock-warning.exceeds-stock {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.cart-unavailable {
    opacity: 0.7;
    filter: grayscale(50%);
}

.cart-unavailable:hover {
    transform: translateY(-2px) scale(1.0);
}
</style>
</head>
<body>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<div class="main-wrapper">
    <div class="cart-shell">
        <div class="cart-header">
            <h1>Your Cart</h1>
            <span>
                <?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?> in cart
            </span>
        </div>

        <?php if (empty($cartItems)): ?>
    <div class="empty-text">
        Your cart is empty. Start exploring products and add something you like!
    </div>
<?php else: ?>
    <?php 
    $totalPrice = 0;
    $hasUnavailableItems = false;
    
    foreach ($cartItems as $item):
        $base = pathinfo($item['fld_product_image'], PATHINFO_FILENAME);
        $extensions = ['jpg','jpeg','png','JPG','JPEG','PNG'];
        $imgPath = "images/default.png";
        
        foreach ($extensions as $ext) {
            $file = "images/$base.$ext";
            if (file_exists($file)) {
                $imgPath = $file;
                break;
            }
        }
        
        $lineTotal = $item['fld_product_price'] * $item['quantity'];
        $is_unavailable = ($item['stock_status'] !== 'available');
        
        if ($is_unavailable) {
            $hasUnavailableItems = true;
        } else {
            $totalPrice += $lineTotal; // Only count available items
        }
    ?>
    <div class="cart-card <?= $is_unavailable ? 'cart-unavailable' : '' ?>">
        <?php if ($item['stock_status'] === 'out_of_stock'): ?>
            <div class="stock-warning out-of-stock">
                <i class="bi bi-x-circle-fill"></i> OUT OF STOCK
            </div>
        <?php elseif ($item['stock_status'] === 'exceeds_stock'): ?>
            <div class="stock-warning exceeds-stock">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                Only <?= $item['fld_product_quantity'] ?> available (you have <?= $item['quantity'] ?> in cart)
            </div>
        <?php endif; ?>
        
        <div class="cart-left">
            <img src="<?= htmlspecialchars($imgPath) ?>" 
                 alt="<?= htmlspecialchars($item['fld_product_name']) ?>"
                 style="<?= $is_unavailable ? 'filter: grayscale(100%); opacity: 0.5;' : '' ?>">
            <div class="item-info">
                <h5><?= htmlspecialchars($item['fld_product_name']) ?></h5>
                <div class="item-category">
                    <?= htmlspecialchars($item['fld_product_category']) ?>
                </div>
                <div class="item-meta">
                    Quantity in cart: <span><?= (int)$item['quantity'] ?></span>
                </div>
                <?php if ($item['stock_status'] === 'available'): ?>
                    <div class="item-meta text-success">
                        <i class="bi bi-check-circle"></i> Stock available: <span><?= $item['fld_product_quantity'] ?></span>
                    </div>
                <?php endif; ?>
                <div class="item-meta">
                    Unit price: <span>RM<?= number_format($item['fld_product_price'], 2) ?></span>
                </div>
            </div>
        </div>

        <div class="cart-right">
            <div class="price" style="<?= $is_unavailable ? 'opacity: 0.5;' : '' ?>">
                RM<?= number_format($lineTotal, 2) ?>
                <small>(Subtotal)</small>
            </div>

            <?php if ($item['stock_status'] === 'available'): ?>
                <?php if (!empty($item['fld_product_id']) && !empty($item['fld_seller_id'])): ?>
                    <a href="chatbuyer.php?product_id=<?= $item['fld_product_id'] ?>&seller_id=<?= $item['fld_seller_id'] ?>&cart_quantity=<?= $item['quantity'] ?>&from_cart=1" 
                       class="btn-cart btn-chat"><i class="bi bi-chat-dots"></i> Chat Seller</a>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn-cart btn-remove" style="background: #fca5a5; cursor: not-allowed;" disabled>
                    <i class="bi bi-x-circle"></i> Unavailable
                </button>
            <?php endif; ?>

            <form method="post" style="display:inline-block; margin-top:6px;">
                <input type="hidden" name="remove_item" value="<?= $item['cart_id'] ?>">
                <button type="submit" class="btn-cart btn-remove">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($hasUnavailableItems): ?>
        <div class="alert alert-warning" style="margin-top: 20px; border-radius: 12px; border-left: 5px solid #f59e0b;">
            <i class="bi bi-exclamation-triangle-fill"></i> 
            <strong>Note:</strong> Some items in your cart are unavailable. Please remove them before proceeding to chat with sellers.
        </div>
    <?php endif; ?>

    <div class="total-box">
        <div class="label">Total payable</div>
        <div class="amount">RM<?= number_format($totalPrice, 2) ?></div>
    </div>
<?php endif; ?>

        <a href="buyer.php" class="back-btn" style="display: inline-block; margin-top: 20px; text-decoration: none;">
            <i class="bi bi-arrow-left-circle-fill"></i> Back to Home
        </a>
    </div>
</div>
</body>
</html>
