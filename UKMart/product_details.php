<?php
include('db_connect.php');
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Basic user context (align with buyer/seller navbar)
$user_name = 'Guest';
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller') {
        $stmtUser = $conn->prepare("SELECT fld_seller_name AS name FROM tbl_sellers_ukmart WHERE fld_seller_id = :uid");
    } else {
        $stmtUser = $conn->prepare("SELECT fld_user_name AS name FROM tbl_user_ukmart WHERE fld_user_id = :uid");
    }
    $stmtUser->bindParam(':uid', $uid);
    $stmtUser->execute();
    $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($rowUser && !empty($rowUser['name'])) {
        $user_name = $rowUser['name'];
    }

    $cart_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = :uid");
    $cart_stmt->bindParam(':uid', $uid);
    $cart_stmt->execute();
    $rowCart = $cart_stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = isset($rowCart['total']) ? (int)$rowCart['total'] : 0;
}

if (!isset($_GET['name'])) die("Product not found.");

$name = $_GET['name'];

// Fetch product
$stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_product_name = :name");
$stmt->bindParam(':name', $name);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) die("Product not found.");

// Check stock availability
$is_available = ($product['fld_product_quantity'] > 0 && $product['is_available'] == 1);
$stock_quantity = (int)$product['fld_product_quantity'];

// Product image
$file = $product['fld_product_image'];
$img_path = "images/$file"; // use original file
if (!file_exists($img_path)) {
    $base = pathinfo($file, PATHINFO_FILENAME);
    if (file_exists("images/$base.jpg")) $img_path = "images/$base.jpg";
    elseif (file_exists("images/$base.png")) $img_path = "images/$base.png";
    else $img_path = "images/default.png";
}
$img = $img_path;



// Fetch seller info
$seller_id = $product['fld_seller_id'];
$seller_stmt = $conn->prepare("SELECT * FROM tbl_sellers_ukmart WHERE fld_seller_id = :id");
$seller_stmt->bindParam(':id', $seller_id);
$seller_stmt->execute();
$seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);

// Seller image
$seller_img = file_exists("images/sellers/{$seller_id}.png") ? "images/sellers/{$seller_id}.png" :
              (file_exists("images/sellers/{$seller_id}.jpg") ? "images/sellers/{$seller_id}.jpg" : "images/default_seller.png");
              
$profile_path = file_exists("profile/{$seller_id}.jpg") 
              ? "profile/{$seller_id}.jpg"
              : (file_exists("profile/{$seller_id}.png") 
                  ? "profile/{$seller_id}.png" 
                  : "profile/default_user.png");
          


// Seller's top products
$top_stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_seller_id = :sid AND fld_product_name != :pname LIMIT 10");
$top_stmt->bindParam(':sid', $seller_id);
$top_stmt->bindParam(':pname', $name);
$top_stmt->execute();
$top_products = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recommended products
$cat = $product['fld_product_category'];
$rec_stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_product_category = :cat AND fld_product_name != :pname LIMIT 8");
$rec_stmt->bindParam(':cat', $cat);
$rec_stmt->bindParam(':pname', $name);
$rec_stmt->execute();
$recommended = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['fld_product_name']) ?> | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Georgia&display=swap" rel="stylesheet">
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

/* ---------------- GENERAL ---------------- */
body { font-family: 'Poppins', sans-serif; background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%); color: var(--text-dark); margin:0; padding:0; }
a { text-decoration:none; transition:.3s; }
h5, h6 { font-weight:600; }

/* ---------------- NAVBAR (minimalist) ---------------- */
.navbar { background-color: #ffffff; border-bottom: 1px solid #e5e7eb; padding: 0.75rem 1.5rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); position: sticky; top: 0; z-index: 1000; }
.navbar-brand { font-size: 1.5rem; font-weight: 600; color: #2563eb; letter-spacing: -0.3px; text-decoration: none; }
.navbar-brand:hover { color: #1e40af; }
.nav-link, .navbar-text { color: #374151 !important; font-weight: 400; font-size: 0.95rem; }
.navbar .btn-icon { background: transparent; border: none; color: #374151; font-size: 1.1rem; padding: 6px 10px; border-radius: 6px; transition: all 0.2s ease; }
.navbar .btn-icon:hover { color: #2563eb; background: #f3f4f6; }
.navbar .dropdown-toggle { background: transparent; border: none; color: #374151; font-weight: 400; font-size: 0.95rem; padding: 6px 12px; border-radius: 6px; }
.navbar .dropdown-toggle:hover { color: #2563eb; background: #f3f4f6; }
.navbar .form-control { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 16px; font-size: 0.9rem; }
.navbar .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
.navbar .btn { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 8px 20px; font-weight: 500; font-size: 0.9rem; transition: all 0.2s ease; }
.navbar .btn:hover { background: #1e40af; }
.cart-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.75rem; border-radius: 50%; padding: 2px 6px; }

/* ---------------- PRODUCT MAIN ---------------- */
.product-main { max-width:1280px; margin:30px auto; background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); border-radius:24px; padding:50px; box-shadow: var(--shadow-xl); border: 2px solid var(--primary-blue-light); display:flex; flex-wrap:wrap; gap:50px; animation: fadeUp 0.6s ease; position: relative; overflow: hidden; }
.product-main::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.product-images { 
  flex:1 1 45%; 
  text-align:center; 
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.product-images img.main-img {
  width: 100%;
  max-width: 500px;
  height: auto;
  max-height: 500px;
  object-fit: contain;
  border-radius: 20px;
  box-shadow: 0 10px 20px rgba(0,0,0,0.08);
  transition: .3s;
  background: #f8fafc;
}
.product-images .thumbs { display:flex; justify-content:center; gap:12px; margin-top:15px; }
.product-images .thumbs img { width:70px; height:70px; object-fit:cover; border-radius:15px; cursor:pointer; border:2px solid transparent; transition:.3s; }
.product-images .thumbs img.active, .product-images .thumbs img:hover { border-color:#ff6b6b; transform:scale(1.05); }

/* ---------------- PRODUCT INFO ---------------- */
.product-info { 
  flex:1 1 50%; 
  display:flex; 
  flex-direction:column; 
  gap:15px; 
  min-width: 300px;
  align-self: flex-start;
}
.product-category { font-size:14px; font-weight:700; color: white; text-transform:uppercase; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display:inline-block; padding:8px 18px; border-radius:50px; letter-spacing:1px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
.product-title { font-size:2.8rem; font-weight:800; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin:0; }
.price-box { font-size:2.5rem; font-weight:800; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin:15px 0; }
.qty-input { width:90px; padding:10px; border-radius:15px; border:1px solid #ddd; text-align:center; margin-right:15px; font-size:14px; }

/* ---------------- BUTTONS ---------------- */
.btn-main { border-radius:12px; font-weight:700; padding:14px 30px; font-size:16px; transition:all 0.3s ease; box-shadow: var(--shadow-md); text-transform: uppercase; letter-spacing: 0.5px; }
.btn-cart { background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: var(--primary-blue); border:3px solid var(--primary-blue); }
.btn-cart:hover { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transform:translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); border-color: transparent; }
.btn-buy { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff; box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
.btn-buy:hover { transform:translateY(-3px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5); }
.btn-chat { background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: var(--primary-blue); border:3px solid var(--primary-blue); }
.btn-chat:hover { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); border-color: transparent; }

/* ---------------- SELLER CARD ---------------- */
.seller-card { border-radius:18px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 3px solid var(--primary-blue-light); padding:25px; display:flex; justify-content:space-between; align-items:center; margin-top:30px; box-shadow: var(--shadow-md); animation: fadeUp 0.6s ease; }
.seller-card .btn { font-size:14px; padding:8px 16px; }
.seller-card img { width:60px; height:60px; border-radius:50%; object-fit:cover; margin-right:15px; }

/* ---------------- TABS ---------------- */
.tab-content { 
  background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); 
  border: 2px solid var(--primary-blue-light); 
  border-radius:18px; 
  padding:30px; 
  margin-top:25px; 
  box-shadow: var(--shadow-md);
  max-width: 100%;
  width: 100%;
  box-sizing: border-box;
}
.tab-nav .nav-link { font-weight:700; color: var(--text-gray); font-size:16px; text-transform: uppercase; letter-spacing: 0.5px; }
.tab-nav .nav-link.active { color: var(--primary-blue); border-bottom:4px solid var(--primary-blue); background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }

/* ---------------- SCROLL CARDS ---------------- */
.scroll-x { overflow-x:auto; white-space:nowrap; padding-bottom:15px; margin-bottom:25px; }
.scroll-x::-webkit-scrollbar { display:none; }
.scroll-x .card { display:inline-block; width:220px; margin-right:20px; border-radius:18px; box-shadow: var(--shadow-md); border: 2px solid var(--border-color); overflow:hidden; background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); transition:all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); animation: fadeUp 0.6s ease; position: relative; }
.scroll-x .card::before {
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
.scroll-x .card:hover::before {
  transform: scaleX(1);
}
.scroll-x .card:hover { transform:translateY(-8px) scale(1.03); box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3); border-color: var(--primary-blue); }
.scroll-x .card img { width:100%; height:160px; object-fit:cover; }
.scroll-x .card .p-2 { padding:12px; }
.scroll-x .card h6 { font-size:16px; margin-bottom:6px; color: var(--text-dark); }
.scroll-x .card p { margin:0; font-weight:700; color: var(--primary-blue); }
.scroll-x .card .btn { font-size:13px; padding:8px 0; border-radius:10px; }

/* ---------------- RESPONSIVE ---------------- */
/* Tablet and below */
@media(max-width:992px) { 
  .product-main { 
    flex-direction:column; 
    padding: 30px;
    gap: 30px;
  } 
  .product-images,.product-info { 
    flex:1 1 100%; 
    width: 100%;
  }
  .product-info {
    align-self: stretch;
    min-width: auto;
  }
  .product-title {
    font-size: 2.2rem;
  }
  .price-box {
    font-size: 2rem;
  }
  .seller-card {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
  .seller-card img {
    margin-right: 0;
    margin-bottom: 10px;
  }
}

/* Mobile devices */
@media(max-width:768px) {
  .product-main {
    padding: 20px;
    margin: 15px;
    gap: 20px;
  }
  .product-title {
    font-size: 1.8rem;
  }
  .price-box {
    font-size: 1.6rem;
  }
  .product-category {
    font-size: 12px;
    padding: 6px 14px;
  }
  .product-images {
    width: 100%;
  }
  .product-images img.main-img {
    width: 100%;
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
  }
  .product-info {
    align-self: stretch;
    min-width: auto;
    width: 100%;
  }
  .product-images .thumbs {
    flex-wrap: wrap;
    gap: 8px;
  }
  .product-images .thumbs img {
    width: 50px;
    height: 50px;
  }
  .btn-main {
    padding: 12px 20px;
    font-size: 14px;
    width: 100%;
    margin-bottom: 10px;
  }
  .qty-input {
    width: 100%;
    margin-right: 0;
    margin-bottom: 10px;
  }
  .seller-card {
    padding: 20px;
  }
  .tab-content {
    padding: 20px;
    width: 100%;
    max-width: 100%;
  }
  .description-content {
    width: 100%;
    max-width: 100%;
  }
  .scroll-x .card {
    width: 180px;
  }
  .back-btn-container {
    padding: 0 15px;
  }
}

/* Small mobile devices */
@media(max-width:576px) {
  .product-main {
    padding: 15px;
    margin: 10px;
    border-radius: 16px;
  }
  .product-title {
    font-size: 1.5rem;
  }
  .price-box {
    font-size: 1.4rem;
  }
  .product-info {
    gap: 10px;
    align-self: stretch;
    min-width: auto;
  }
  .product-images img.main-img {
    max-height: 300px;
    object-fit: contain;
    width: 100%;
  }
  .detail-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .scroll-x .card {
    width: 160px;
  }
  .tab-content {
    padding: 15px;
    margin-top: 10px;
    width: 100%;
    max-width: 100%;
  }
  .description-content {
    width: 100%;
    max-width: 100%;
    padding: 5px 0;
  }
}

/* Description label */
.description strong { color:#000; font-weight:700; }

/* Simple entrance animation */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}
/* Stock alerts */
.alert {
  animation: fadeUp 0.5s ease;
}

.qty-input {
  width: 90px;
  padding: 10px;
  border-radius: 15px;
  border: 2px solid var(--primary-blue-light);
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.qty-input:focus {
  outline: none;
  border-color: var(--primary-blue);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Disable pointer on out of stock */
.product-info button:disabled {
  cursor: not-allowed !important;
  opacity: 0.5;
}

/* Product Details Card */
.product-details-card {
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border: 2px solid var(--border-color);
  border-radius: 16px;
  padding: 20px;
  margin: 20px 0;
  box-shadow: var(--shadow-sm);
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid var(--border-color);
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-weight: 600;
  color: var(--text-gray);
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-label i {
  color: var(--primary-blue);
  font-size: 16px;
}

.detail-value {
  font-weight: 600;
  color: var(--text-dark);
  font-size: 15px;
  text-align: right;
}

/* Stock alerts */
.alert {
  animation: fadeUp 0.5s ease;
  margin-bottom: 20px;
}

.qty-input {
  width: 90px;
  padding: 10px;
  border-radius: 15px;
  border: 2px solid var(--primary-blue-light);
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.qty-input:focus {
  outline: none;
  border-color: var(--primary-blue);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Disable pointer on out of stock */
.product-info button:disabled {
  cursor: not-allowed !important;
  opacity: 0.5;
}

/* Back Button Styling */
.back-btn {
  font-size: 1.5rem;
  color: var(--primary-blue);
  padding: 10px;
  display: inline-block;
  margin-right: 10px;
  transition: all 0.3s ease;
  text-decoration: none;
  cursor: pointer;
}

.back-btn:hover {
  color: var(--primary-blue-dark);
  transform: scale(1.1);
}

.back-btn-container {
  margin: 20px auto;
  max-width: 1280px;
  padding: 0 20px;
}

/* Responsive adjustments */
@media(max-width: 576px) {
  .detail-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .detail-value {
    text-align: left;
  }
  
  .back-btn {
    font-size: 1.3rem;
  }
}
/* Description Content */
.description-content {
  padding: 10px 0;
  max-width: 100%;
  width: 100%;
}

.description-content h5 {
  color: var(--text-dark);
  font-weight: 700;
  border-bottom: 3px solid var(--primary-blue-light);
  padding-bottom: 10px;
  margin-bottom: 20px;
}

.description-content p {
  font-size: 15px;
  line-height: 1.8;
  color: var(--text-dark);
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* Tab Content */
.tab-content {
  max-width: 100%;
  width: 100%;
  margin-top: 20px;
}

.tab-content .tab-pane {
  width: 100%;
  max-width: 100%;
}
</style>
</head>
<body>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<!-- Back Button -->
<div class="back-btn-container">
  <a href="buyer.php" class="back-btn" title="Back to Home">
    <i class="bi bi-arrow-left-circle-fill"></i>
  </a>
</div>

<!-- Product Main -->
<div class="container">
  <div class="product-main">
    <!-- Images -->
    <div class="product-images">
      <img src="<?= $img ?>" class="main-img" id="mainImage">
      <div class="thumbs mt-3">
        <img src="<?= $img ?>" class="active" onclick="document.getElementById('mainImage').src='<?= $img ?>'">
      </div>
    </div>

    <!-- Info -->
<div class="product-info">
  <div class="product-category"><?= htmlspecialchars($product['fld_product_category']) ?></div>
  <div class="product-title"><?= htmlspecialchars($product['fld_product_name']) ?></div>
  <div class="price-box">RM <?= htmlspecialchars($product['fld_product_price']) ?></div>

  <!-- Stock Status -->
  <?php if (!$is_available): ?>
    <div class="alert alert-danger d-flex align-items-center" style="border-radius: 12px; border-left: 5px solid #dc2626;">
      <i class="bi bi-x-circle-fill me-2" style="font-size: 1.5rem;"></i>
      <div>
        <strong>Out of Stock</strong><br>
        <small>This product is currently unavailable. Check back later or contact the seller.</small>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-success d-flex align-items-center" style="border-radius: 12px; border-left: 5px solid #10b981;">
      <i class="bi bi-check-circle-fill me-2" style="font-size: 1.5rem;"></i>
      <div>
        <strong>In Stock</strong><br>
        <small><?= $stock_quantity ?> unit(s) available</small>
      </div>
    </div>
  <?php endif; ?>

  <!-- Product Details Card -->
  <div class="product-details-card">
    <div class="detail-row">
      <span class="detail-label"><i class="bi bi-tag"></i> Condition:</span>
      <span class="detail-value"><?= htmlspecialchars($product['fld_product_condition']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label"><i class="bi bi-award"></i> Brand:</span>
      <span class="detail-value"><?= htmlspecialchars($product['fld_product_brand']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label"><i class="bi bi-gear"></i> Model:</span>
      <span class="detail-value"><?= htmlspecialchars($product['fld_product_model']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label"><i class="bi bi-geo-alt"></i> Location:</span>
      <span class="detail-value"><?= htmlspecialchars($product['fld_product_location']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label"><i class="bi bi-telephone"></i> Contact:</span>
      <span class="detail-value"><?= htmlspecialchars($product['fld_contact_phone']) ?></span>
    </div>
  </div>

  <!-- Action Buttons -->
  <div class="d-flex flex-wrap gap-2 mb-3 mt-4">
    <?php if (!empty($product['fld_product_id']) && !empty($seller['fld_seller_id'])): ?>
      <a href="chatbuyer.php?product_id=<?= htmlspecialchars($product['fld_product_id']) ?>&seller_id=<?= htmlspecialchars($seller['fld_seller_id']) ?>" 
         class="btn btn-main btn-chat">
          <i class="bi bi-chat-dots"></i> Chat Seller
      </a>
    <?php else: ?>
      <button class="btn btn-secondary" disabled><i class="bi bi-chat-dots"></i> Chat Unavailable</button>
    <?php endif; ?>

    <?php if ($is_available): ?>
      <!-- Add to Cart Form - ONLY SHOW IF IN STOCK -->
      <form method="post" action="add_to_cart.php" class="d-flex align-items-center gap-2" id="addToCartForm">
        <input type="hidden" name="product_id" value="<?= $product['fld_product_id'] ?>">
        <div class="position-relative">
          <input type="number" 
                 name="quantity" 
                 id="quantityInput"
                 value="1" 
                 min="1" 
                 max="<?= $stock_quantity ?>"
                 class="qty-input">
          <small class="text-muted d-block mt-1" style="font-size: 11px;">Max: <?= $stock_quantity ?></small>
        </div>
        <button type="submit" class="btn btn-main btn-cart" id="addToCartBtn">
            <i class="bi bi-cart-plus"></i> Add to Cart
        </button>
      </form>

      <button class="btn btn-main btn-buy" onclick="buyNow()">
        <i class="bi bi-bag-heart"></i> Buy Now
      </button>
    <?php else: ?>
      <!-- Out of Stock - Disabled Buttons -->
      <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;">
        <i class="bi bi-cart-x"></i> Out of Stock
      </button>
      <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;">
        <i class="bi bi-bag-x"></i> Unavailable
      </button>
    <?php endif; ?>
  </div>

  <!-- Seller Card -->
  <div class="seller-card">
    <div class="d-flex align-items-center">
      <img src="<?= $profile_path ?>" alt="Seller Image" class="img-fluid rounded-circle" style="width:60px;height:60px;object-fit:cover;">
      <span class="fw-bold ms-3"><?= htmlspecialchars($seller['fld_seller_name']) ?></span>
    </div>
    <a href="visit_seller.php?seller_id=<?= $seller['fld_seller_id'] ?>" class="btn btn-main btn-cart">
      Visit Seller
    </a>
  </div>
</div>

  <!-- Tabs -->
<ul class="nav nav-tabs tab-nav mt-5" id="productTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc" type="button">
      <i class="bi bi-file-text"></i> Description
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="review-tab" data-bs-toggle="tab" data-bs-target="#review" type="button">
      <i class="bi bi-star"></i> Reviews
    </button>
  </li>
</ul>

<div class="tab-content" id="productTabContent">
  <div class="tab-pane fade show active" id="desc">
    <div class="description-content">
      <h5 class="mb-3"><i class="bi bi-info-circle"></i> Product Description</h5>
      <?php if (!empty($product['fld_product_description'])): ?>
        <p style="line-height: 1.8; color: var(--text-dark); white-space: pre-wrap;"><?= htmlspecialchars($product['fld_product_description']) ?></p>
      <?php else: ?>
        <p class="text-muted">No detailed description available for this product.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="tab-pane fade" id="review">
    <div class="text-center py-5">
      <i class="bi bi-star" style="font-size: 3rem; color: var(--text-gray);"></i>
      <p class="text-muted mt-3">No reviews yet. Be the first to review this product!</p>
    </div>
  </div>
</div>

  <!-- Seller's Top Products -->
  <?php if ($top_products): ?>
  <div class="scroll-x">
    <?php foreach($top_products as $tp): 
      $file = $tp['fld_product_image'];
      $base = pathinfo($file, PATHINFO_FILENAME);
      $img_tp = file_exists("images/$file") ? "images/$file" :
                (file_exists("images/$base.jpg") ? "images/$base.jpg" :
                (file_exists("images/$base.png") ? "images/$base.png" : "images/default.png"));
    ?>
    <div class="card">
      <img src="<?= $img_tp ?>">
      <div class="p-2">
        <h6><?= $tp['fld_product_name'] ?></h6>
        <p>RM <?= $tp['fld_product_price'] ?></p>
        <a href="product_details.php?name=<?= urlencode($tp['fld_product_name']) ?>" class="btn btn-main btn-buy w-100">View</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Recommended Products -->
  <?php if ($recommended): ?>
  <div class="scroll-x">
    <?php foreach($recommended as $rec): 
      $file = $rec['fld_product_image'];
      $base = pathinfo($file, PATHINFO_FILENAME);
      $img_r = file_exists("images/$file") ? "images/$file" :
               (file_exists("images/$base.jpg") ? "images/$base.jpg" :
               (file_exists("images/$base.png") ? "images/$base.png" : "images/default.png"));
    ?>
    <div class="card">
      <img src="<?= $img_r ?>">
      <div class="p-2">
        <h6><?= $rec['fld_product_name'] ?></h6>
        <p>RM <?= $rec['fld_product_price'] ?></p>
        <a href="product_details.php?name=<?= urlencode($rec['fld_product_name']) ?>" class="btn btn-main btn-buy w-100">View</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Quantity input validation
const quantityInput = document.getElementById('quantityInput');
const addToCartForm = document.getElementById('addToCartForm');
const maxStock = <?= $stock_quantity ?>;

if (quantityInput) {
  // Prevent typing more than max stock
  quantityInput.addEventListener('input', function() {
    let value = parseInt(this.value);
    if (value > maxStock) {
      this.value = maxStock;
      alert(`Only ${maxStock} unit(s) available!`);
    }
    if (value < 1 || isNaN(value)) {
      this.value = 1;
    }
  });

  // Validate on form submit
  addToCartForm?.addEventListener('submit', function(e) {
    let qty = parseInt(quantityInput.value);
    if (qty > maxStock) {
      e.preventDefault();
      alert(`Cannot add ${qty} units. Only ${maxStock} available!`);
      quantityInput.value = maxStock;
      return false;
    }
    if (qty < 1 || isNaN(qty)) {
      e.preventDefault();
      alert('Please enter a valid quantity (minimum 1).');
      quantityInput.value = 1;
      return false;
    }
  });
}

function shareProduct() {
  const url = window.location.href;
  const title = "Check out this product on UKMart!";
  if (navigator.share) {
    navigator.share({ title: title, url: url });
  } else {
    prompt("Copy this link to share:", url);
  }
}

function buyNow() {
  // Validate stock first
  const qty = parseInt(document.getElementById('quantityInput')?.value || 1);
  if (qty > <?= $stock_quantity ?>) {
    alert(`Only <?= $stock_quantity ?> unit(s) available!`);
    return;
  }
  
  // For now, redirect to cart after adding
  document.getElementById('addToCartForm')?.submit();
}
</script>
</body>
</html>
