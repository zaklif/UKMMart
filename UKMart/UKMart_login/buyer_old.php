<?php
include('db_connect.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>E-Commerce UKM System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background-color: #fff; font-family: Arial, sans-serif; }
    .navbar { background-color: #0d6efd; }
    .navbar-brand, .nav-link, .navbar-text { color: white !important; }
    
    /* 🔹 Navbar icon buttons (Profile, Chat, Cart) */
    .navbar .btn-icon {
      background: transparent;
      border: none;
      color: white;
      font-size: 1.4rem;
      margin-left: 15px;
      transition: transform 0.2s, color 0.2s;
    }
    .navbar .btn-icon:hover {
      transform: scale(1.15);
      color: #ffd700;
    }

    /* 🔹 Product & Carousel styling */
    .category-icon { width: 80px; height: 80px; border-radius: 50%; background: #f1f1f1; display: flex; align-items: center; justify-content: center; margin: auto; }
    .category-title { font-weight: bold; margin-top: 10px; }
    .section-title { font-weight: bold; margin: 30px 0 15px; }
    .product-card img { height: 150px; object-fit: cover; border-radius: 10px; }
    .product-card { border: none; background: #f9f9f9; border-radius: 10px; transition: transform .2s; }
    .product-card:hover { transform: scale(1.02); }
    .view-all-btn { float: right; font-size: 14px; }
    .carousel-item img { width: 100%; height: 250px; object-fit: cover; }

    /* 🔹 Carousel arrow position */
    .carousel-control-prev,
    .carousel-control-next {
      width: 5%;
      top: 50%;
      transform: translateY(-50%);
    }
    .carousel-control-prev { left: -30px; }
    .carousel-control-next { right: -30px; }
    .carousel-control-prev-icon,
    .carousel-control-next-icon {
      background-color: rgba(0,0,0,0.5);
      border-radius: 50%;
      padding: 15px;
    }
    .carousel-indicators [data-bs-target] { background-color: #555; }

    @media (max-width: 768px) {
      .carousel-control-prev,
      .carousel-control-next { left: 0; right: 0; width: 10%; }
    }
  </style>
</head>

<body>

<!-- 🔹 Navigation Bar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">E-COMMERCE UKM SYSTEM</a>

    <!-- Search Bar -->
    <form class="d-flex ms-auto" role="search">
      <input class="form-control me-2" type="search" placeholder="Search products..." aria-label="Search">
      <button class="btn btn-light" type="submit">Search</button>
    </form>

    <!-- Profile, Chat & Cart Icons -->
    <div class="d-flex align-items-center ms-3">
      <a href="profile.php" class="btn-icon" title="Profile">
        <i class="bi bi-person-circle"></i>
      </a>
      <a href="chat.php" class="btn-icon" title="Chat">
        <i class="bi bi-chat-dots"></i>
      </a>
      <a href="cart.php" class="btn-icon" title="Cart">
        <i class="bi bi-cart3"></i>
      </a>
    </div>
  </div>
</nav>

<!-- 🔹 Carousel Banner -->
<div id="carouselExampleIndicators" class="carousel slide mt-3" data-bs-ride="carousel">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="images/banner1.jpg" class="d-block w-100" alt="Latest Item">
    </div>
    <div class="carousel-item">
      <img src="images/banner2.jpg" class="d-block w-100" alt="Promo">
    </div>
  </div>
</div>

<!-- 🔹 Shop by Category -->
<div class="container text-center my-5">
  <h4 class="fw-bold mb-4">SHOP BY CATEGORY</h4>
  <div class="row justify-content-center">
    <?php
    $categories = ['Food', 'Book', 'Clothing', 'Dorm supply', 'Electronics'];
    foreach ($categories as $cat) {
      $iconBase = strtolower(str_replace(" ", "_", $cat));
      $iconPathJpg = "images/$iconBase.jpg";
      $iconPathPng = "images/$iconBase.png";

      if (file_exists($iconPathJpg)) {
          $iconPath = $iconPathJpg;
      } elseif (file_exists($iconPathPng)) {
          $iconPath = $iconPathPng;
      } else {
          $iconPath = "images/default.png"; // fallback image
      }

      echo '
      <div class="col-6 col-md-2 mb-4">
        <div class="category-icon">
          <img src="'.$iconPath.'" alt="'.$cat.'" width="40">
        </div>
        <div class="category-title">'.$cat.'</div>
      </div>';
    }
    ?>
  </div>
</div>

<!-- 🔹 Product Sections -->
<div class="container">
  <?php
  $categories = ['Food', 'Book', 'Clothing'];
  foreach ($categories as $category) {
      echo "<div class='section-title d-flex justify-content-between align-items-center'>
              <span>$category</span>
              <a href='view_all.php?category=$category' class='btn btn-warning btn-sm view-all-btn'>VIEW ALL</a>
            </div>";

      // Fetch products
      $stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_product_category = :category");
      $stmt->bindParam(':category', $category);
      $stmt->execute();
      $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if ($products) {
          echo "
          <div id='carousel_$category' class='carousel slide mb-5 position-relative' data-bs-ride='carousel'>
            <div class='carousel-inner'>";

          $chunks = array_chunk($products, 3);
          $isActive = "active";

          foreach ($chunks as $group) {
              echo "<div class='carousel-item $isActive'>
                      <div class='row'>";
              foreach ($group as $product) {
                  $imgJpg = 'images/' . $product['fld_product_image'] . '.jpg';
                  $imgPng = 'images/' . $product['fld_product_image'] . '.png';
                  $imgPath = file_exists($imgJpg) ? $imgJpg : (file_exists($imgPng) ? $imgPng : 'images/default.png');

                  echo "
                  <div class='col-6 col-md-4 mb-3'>
                    <div class='card product-card p-2'>
                      <img src='$imgPath' class='card-img-top' alt='{$product['fld_product_name']}'>
                      <div class='card-body'>
                        <h6 class='card-title text-center'>{$product['fld_product_name']}</h6>
                        <p class='text-center text-muted'>RM {$product['fld_product_price']}</p>
                      </div>
                    </div>
                  </div>";
              }
              echo "</div></div>";
              $isActive = "";
          }

          echo "
            </div>
            <button class='carousel-control-prev' type='button' data-bs-target='#carousel_$category' data-bs-slide='prev'>
              <span class='carousel-control-prev-icon' aria-hidden='true'></span>
              <span class='visually-hidden'>Previous</span>
            </button>
            <button class='carousel-control-next' type='button' data-bs-target='#carousel_$category' data-bs-slide='next'>
              <span class='carousel-control-next-icon' aria-hidden='true'></span>
              <span class='visually-hidden'>Next</span>
            </button>
            <div class='carousel-indicators mt-3'>";
          for ($i = 0; $i < count($chunks); $i++) {
              $active = ($i === 0) ? 'active' : '';
              echo "<button type='button' data-bs-target='#carousel_$category' data-bs-slide-to='$i' class='$active'></button>";
          }
          echo "</div>
          </div>";
      } else {
          echo "<p class='text-muted'>No products available in this category.</p>";
      }
  }
  ?>
</div>

<!-- 🔹 Footer -->
<footer class="text-center py-3 bg-light mt-4 border-top">
  <small>© 2025 E-Commerce UKM System. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
