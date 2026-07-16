<?php
include('db_connect.php');
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Retrieve the user type (seller or buyer) from the session
$user_type = $_SESSION['user_type']; // Assume 'user_type' is set during login
$_SESSION['user_type'] = 'buyer'; // Ensure it's set for navbar

// Get the user name based on their type (buyer or seller)
if ($user_type == 'seller') {
    // Get seller name - query by fld_user_id (foreign key in sellers table)
    $stmt = $conn->prepare("SELECT fld_seller_name FROM tbl_sellers_ukmart WHERE fld_user_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row ? $row['fld_seller_name'] : 'User';
} else {
    // Get buyer name
    $stmt = $conn->prepare("SELECT fld_user_name FROM tbl_user_ukmart WHERE fld_user_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row ? $row['fld_user_name'] : 'User';
}

// Get categories and product counts for tooltips
$categories = ['Food', 'Book', 'Clothing', 'Dorm supply', 'Electronics', 'Other'];
$categoryCounts = [];
foreach ($categories as $cat) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_products_ukmart WHERE fld_product_category = :c");
    $stmt->bindParam(':c', $cat);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryCounts[$cat] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UKMart</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Satoshi Font -->
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
  <!-- Google Fonts for Georgia -->
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

    body {
      font-family: 'Satoshi', Georgia, serif;
      background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
      color: var(--text-dark);
    }


    /* ===== Carousel ===== */
    .carousel {
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      margin-bottom: 30px;
      border: 3px solid var(--primary-blue-light);
    }
    .carousel-item img {
      width: 100%;
      height: 350px;
      object-fit: cover;
      transition: transform 0.5s ease;
    }
    .carousel-item:hover img {
      transform: scale(1.05);
    }
    .carousel-control-prev, .carousel-control-next {
      width: 50px;
      height: 50px;
      background: rgba(102, 126, 234, 0.8);
      border-radius: 50%;
      top: 50%;
      transform: translateY(-50%);
      backdrop-filter: blur(10px);
    }

    /* ===== Categories ===== */
    .category-icon {
      width: 90px;
      height: 90px;
      border-radius: 20px;
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 3px solid var(--primary-blue-light);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: auto;
      transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      box-shadow: 0 4px 15px rgba(37, 99, 235, 0.15);
      position: relative;
      overflow: hidden;
    }
    .category-icon::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: left 0.4s ease;
      z-index: 0;
    }
    .category-icon:hover::before {
      left: 0;
    }
    .category-icon:hover {
      transform: translateY(-8px) scale(1.1);
      border-color: var(--primary-blue);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .category-icon img {
      position: relative;
      z-index: 1;
      filter: brightness(0.8);
      transition: filter 0.3s ease;
    }
    .category-icon:hover img {
      filter: brightness(1.2);
    }
    .category-title {
      margin-top: 10px;
      transition: transform 0.3s;
    }
    .category-title:hover {
      transform: translateY(-5px);
    }

    /* ===== Product Cards ===== */
    .product-card {
      border: 2px solid var(--border-color);
      background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
      border-radius: 16px;
      transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    .product-card::before {
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
    .product-card:hover::before {
      transform: scaleX(1);
    }
    .product-card:hover {
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
      border-color: var(--primary-blue);
      transform: translateY(-8px) scale(1.02);
    }
    .product-card img {
      height: 180px;
      width: 100%;
      object-fit: contain;
      border-radius: 12px;
      background: var(--bg-white);
      transition: transform 0.4s ease;
      padding: 10px;
    }
    .product-card:hover img {
      transform: scale(1.1) rotate(2deg);
    }
    .product-card .card-header {
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      border-bottom: 2px solid var(--primary-blue-light);
      padding: 12px;
      border-radius: 12px 12px 0 0;
    }
    .product-card .card-body {
      padding: 15px;
    }
    .product-card .card-title {
      font-weight: 700;
      color: var(--text-dark);
      font-size: 1.1rem;
      margin-bottom: 8px;
    }
    .product-card .card-text {
      color: var(--primary-blue);
      font-weight: 700;
      font-size: 1.3rem;
      margin-bottom: 5px;
    }
    .view-details-btn {
      display: none;
      position: absolute;
      bottom: 10px;
      left: 50%;
      transform: translateX(-50%);
    }
    .product-card:hover .view-details-btn {
      display: block;
    }

    /* Horizontal carousel scroll */
    .scrollbar-hidden::-webkit-scrollbar {
      display: none;
    }
    .scrollbar-hidden {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    .carousel-container .scroll-left, .carousel-container .scroll-right {
      opacity: 0;
      transition: opacity 0.3s;
    }
    .carousel-container:hover .scroll-left, .carousel-container:hover .scroll-right {
      opacity: 1;
    }

    /* ===== Responsive Adjustments ===== */
    /* For smaller screens */
    @media (max-width: 767px) {
      .carousel-item img { height: 250px; }
      .category-icon { width: 70px; height: 70px; }
      .category-title { font-size: 14px; }
      .product-card { min-width: 120px; }
    }

    /* For medium screens */
    @media (max-width: 1024px) {
      .category-icon { width: 75px; height: 75px; }
      .category-title { font-size: 16px; }
      .product-card { min-width: 160px; }
    }

    /* For larger screens */
    @media (min-width: 1025px) {
      .category-icon { width: 80px; height: 80px; }
      .category-title { font-size: 18px; }
    }

    /* ===== SOLD OUT STYLING ===== */
    .product-card.sold-out {
        opacity: 0.7;
        position: relative;
        filter: grayscale(60%);
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .product-card.sold-out::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.15);
        border-radius: 16px;
        pointer-events: none;
    }

    .sold-out-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-20deg);
        z-index: 100;
        pointer-events: none;
        animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translate(-50%, -50%) rotate(-20deg) scale(0.8);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%) rotate(-20deg) scale(1);
        }
    }

    .sold-out-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 15px 30px;
        font-size: 1.5rem;
        font-weight: 900;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
        text-transform: uppercase;
        letter-spacing: 2px;
        border: 3px solid white;
    }

    .product-card.sold-out .card-body {
        position: relative;
        z-index: 1;
    }

    .product-card.sold-out:hover {
        transform: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-color: var(--border-color);
    }

    /* Real-time update indicator */
    .stock-update-indicator {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(16, 185, 129, 0.9);
        color: white;
        padding: 10px 20px;
        border-radius: 30px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 1000;
    }

    .stock-update-indicator.show {
        opacity: 1;
    }

    .product-img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .seller-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
    }

    .card-header {
        display: flex;
        align-items: center;
    }

    .card-header h6 {
        margin-left: 10px;
    }
    
  </style>
</head>
<body>

<!-- Stock update indicator -->
<div id="stockUpdateIndicator" class="stock-update-indicator">
    <i class="bi bi-check-circle me-2"></i>
    <span id="updateText">Stock updated</span>
</div>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<!-- CAROUSEL -->
<div id="carouselExampleIndicators" class="carousel slide mt-3" data-bs-ride="carousel">
  <div class="carousel-inner">
    <?php
    $banners = ['banner.jpg', 'banner1.jpg'];
    foreach ($banners as $index => $banner) {
        $active = $index === 0 ? 'active' : '';
        $img_path = file_exists("images/$banner") ? "images/$banner" : "images/default.png";
        echo "<div class='carousel-item $active'><img src='$img_path' class='d-block w-100'></div>";
    }
    ?>
  </div>
</div>

<!-- CATEGORIES -->
<div class="container text-center my-5">
  <h4 class="mb-4" style="font-weight: normal;">SHOP BY CATEGORY</h4>
  <div class="row justify-content-center">
    <?php
    foreach ($categories as $cat) {
        $file = strtolower(str_replace(" ", "_", $cat));
        $img = file_exists("images/$file.jpg") ? "images/$file.jpg" : (file_exists("images/$file.png") ? "images/$file.png" : "images/default.png");
        $count = $categoryCounts[$cat];
        echo "
        <div class='col-6 col-md-2 mb-4'>
            <a href='view_all.php?category=".urlencode($cat)."' style='text-decoration:none; color:inherit;' title='$count products'>
                <div class='category-icon'>
                    <img src='$img' width='40'>
                </div>
                <div class='category-title'>$cat</div>
            </a>
        </div>";
    }
    ?>
  </div>
</div>

<!-- PRODUCT SECTIONS -->
<div class="container">
    <div class="section-title mb-4">
        <h4 style="font-weight: normal;">Recommended For You</h4>
    </div>

    <div class="row" id="productContainer">
        <?php 
        // Fetch all products - check both quantity and is_available field
        $stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart ORDER BY fld_product_quantity DESC, is_available DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($products) {
          foreach ($products as $product) {
              // Get seller info
              $stmt = $conn->prepare("SELECT fld_seller_name, fld_seller_id FROM tbl_sellers_ukmart WHERE fld_seller_id = :seller_id");
              $stmt->bindParam(':seller_id', $product['fld_seller_id']);
              $stmt->execute();
              $seller = $stmt->fetch(PDO::FETCH_ASSOC);
              $seller_name = $seller ? $seller['fld_seller_name'] : 'Unknown Seller';
              $seller_id = $seller ? $seller['fld_seller_id'] : 0;
      
              // Seller profile image
              $seller_image_path = "profile/$seller_id.jpg";
              if (!file_exists($seller_image_path)) {
                  $seller_image_path = "profile/default.jpg";
              }
      
              
              // Product image
              // Product image (CORRECT WAY)
$img_path = (!empty($product['fld_product_image']) 
    && file_exists("images/" . $product['fld_product_image']))
    ? "images/" . $product['fld_product_image']
    : "images/default.png";

      
              // Check if product is sold out - either no stock OR marked unavailable
              $is_sold_out = ($product['fld_product_quantity'] <= 0 || $product['is_available'] == 0);
              $sold_out_class = $is_sold_out ? 'sold-out' : '';
              
              // Display each product
              echo "
              <div class='col-6 col-md-3 mb-4'>
                  <div class='card product-card $sold_out_class' 
                       data-product-id='{$product['fld_product_id']}' 
                       data-quantity='{$product['fld_product_quantity']}'
                       data-available='{$product['is_available']}'>
                      ";
              
              // Add SOLD OUT badge if product is out of stock
              if ($is_sold_out) {
                  echo "
                      <div class='sold-out-overlay'>
                          <span class='sold-out-badge'>SOLD OUT</span>
                      </div>";
              }
              
              echo "
                      <div class='card-header d-flex align-items-center'>
                          <img src='$seller_image_path' alt='Seller Profile' class='seller-img' style='width: 50px; height: 50px; border-radius: 50%;'>
                          <h6 class='ms-2'>$seller_name</h6>
                      </div>
                      <img src='$img_path' class='card-img-top product-img' alt='{$product['fld_product_name']}'>
                      <div class='card-body'>
                          <h5 class='card-title'>{$product['fld_product_name']}</h5>
                          <p class='card-text'>RM {$product['fld_product_price']}</p>
                          <p class='card-text'><strong>Condition:</strong> {$product['fld_product_condition']}</p>
                          <div class='stock-info'>";
              
              // Show stock status
              if ($is_sold_out) {
                  echo "<p class='text-danger fw-bold'><i class='bi bi-x-circle'></i> Out of Stock</p>";
                  echo "<button class='btn btn-secondary w-100' disabled>Unavailable</button>";
              } else {
                  echo "<p class='text-success stock-text'><i class='bi bi-check-circle'></i> In Stock: <span class='stock-quantity'>{$product['fld_product_quantity']}</span> units</p>";
                  echo "<a href='product_details.php?name=".urlencode($product['fld_product_name'])."' class='btn btn-warning w-100 view-btn'>View Details</a>";
              }
              
              echo "
                          </div>
                      </div>
                  </div>
              </div>";
          }
      } else {
          echo "<p class='text-muted'>No products available.</p>";
      }
        ?>
    </div>
</div>

<!-- FOOTER -->
<footer class="text-center py-3 bg-light border-top mt-4">
  <p>© 2025 UKMart</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Real-time stock checking
let lastStockData = {};
let updateCount = 0;

function checkStockUpdates() {
    fetch('check_product_stock.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProductCards(data.products);
            }
        })
        .catch(error => {
            console.log('Stock check failed:', error);
        });
}

function updateProductCards(stockData) {
    let changesDetected = false;
    
    document.querySelectorAll('.product-card').forEach(card => {
        const productId = card.dataset.productId;
        const currentQuantity = parseInt(card.dataset.quantity);
        const currentAvailable = parseInt(card.dataset.available);
        
        if (stockData[productId]) {
            const newQuantity = stockData[productId].quantity;
            const newAvailable = stockData[productId].is_available;
            const isSoldOut = stockData[productId].is_sold_out;
            
            // Check if stock or availability changed
            if (currentQuantity !== newQuantity || currentAvailable !== newAvailable) {
                changesDetected = true;
                card.dataset.quantity = newQuantity;
                card.dataset.available = newAvailable;
                
                // Update quantity display
                const quantitySpan = card.querySelector('.stock-quantity');
                if (quantitySpan) {
                    quantitySpan.textContent = newQuantity;
                }
                
                // Handle sold out status change
                if (isSoldOut && !card.classList.contains('sold-out')) {
                    // Product just sold out or marked unavailable
                    card.classList.add('sold-out');
                    
                    // Add sold out badge
                    if (!card.querySelector('.sold-out-overlay')) {
                        const overlay = document.createElement('div');
                        overlay.className = 'sold-out-overlay';
                        overlay.innerHTML = '<span class="sold-out-badge">SOLD OUT</span>';
                        card.insertBefore(overlay, card.firstChild);
                    }
                    
                    // Update stock info
                    const stockInfo = card.querySelector('.stock-info');
                    if (stockInfo) {
                        stockInfo.innerHTML = `
                            <p class='text-danger fw-bold'><i class='bi bi-x-circle'></i> Out of Stock</p>
                            <button class='btn btn-secondary w-100' disabled>Unavailable</button>
                        `;
                    }
                    
                    showUpdateNotification(`"${stockData[productId].name}" is now sold out!`);
                }
                else if (!isSoldOut && card.classList.contains('sold-out')) {
                    // Product back in stock
                    card.classList.remove('sold-out');
                    
                    // Remove sold out badge
                    const overlay = card.querySelector('.sold-out-overlay');
                    if (overlay) {
                        overlay.remove();
                    }
                    
                    // Update stock info
                    const stockInfo = card.querySelector('.stock-info');
                    if (stockInfo) {
                        stockInfo.innerHTML = `
                            <p class='text-success stock-text'><i class='bi bi-check-circle'></i> In Stock: <span class='stock-quantity'>${newQuantity}</span> units</p>
                            <a href='product_details.php?name=${encodeURIComponent(stockData[productId].name)}' class='btn btn-warning w-100 view-btn'>View Details</a>
                        `;
                    }
                    
                    showUpdateNotification(`"${stockData[productId].name}" is back in stock!`);
                }
            }
        }
    });
    
    if (changesDetected) {
        updateCount++;
    }
}

function showUpdateNotification(message) {
    const indicator = document.getElementById('stockUpdateIndicator');
    const text = document.getElementById('updateText');
    
    text.textContent = message || 'Stock updated';
    indicator.classList.add('show');
    
    setTimeout(() => {
        indicator.classList.remove('show');
    }, 3000);
}

// Check stock every 5 seconds
setInterval(checkStockUpdates, 5000);

// Initial check after 5 seconds
setTimeout(checkStockUpdates, 5000);

// Horizontal carousel scroll
document.querySelectorAll('.scroll-left').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        target.scrollBy({ left: -200, behavior: 'smooth' });
    });
});
document.querySelectorAll('.scroll-right').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        target.scrollBy({ left: 200, behavior: 'smooth' });
    });
});
</script>

</body>
</html>