<?php
include('db_connect.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>E-Commerce UKM System | Buyer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --primary: #00bfa6;
      --secondary: #0078d7;
      --light-bg: #f9f9fb;
      --card-bg: #ffffff;
    }
    body {
      background-color: var(--light-bg);
      font-family: 'Poppins', sans-serif;
    }
    .navbar {
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    .navbar-brand, .nav-link, .navbar-text {
      color: #fff !important;
    }
    .navbar .btn-icon {
      background: transparent;
      border: none;
      color: #fff;
      font-size: 1.4rem;
      margin-left: 15px;
      transition: 0.3s;
    }
    .navbar .btn-icon:hover {
      transform: scale(1.15);
      color: #ffd700;
    }
    .carousel-item img {
      height: 250px;
      object-fit: cover;
      border-radius: 10px;
    }

    .category-icon {
      width: 90px;
      height: 90px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 3px 6px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: auto;
      transition: 0.3s;
    }
    .category-icon:hover {
      transform: scale(1.1);
    }
    .category-title {
      font-weight: 600;
      margin-top: 10px;
      font-size: 0.95rem;
    }
    .product-card {
      border: none;
      border-radius: 15px;
      background: var(--card-bg);
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      transition: transform 0.3s ease;
    }
    .product-card:hover {
      transform: translateY(-5px);
    }
    .product-card img {
      border-radius: 15px 15px 0 0;
      height: 160px;
      object-fit: cover;
    }
    .section-title {
      font-weight: 600;
      margin: 40px 0 20px;
      border-left: 4px solid var(--primary);
      padding-left: 10px;
    }
    footer {
      background: #fff;
      color: #666;
      font-size: 0.9rem;
      border-top: 1px solid #ddd;
      padding: 15px;
      text-align: center;
      margin-top: 50px;
    }
  </style>
</head>

<body>

<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">E-COMMERCE UKM SYSTEM</a>

    <form class="d-flex ms-auto" role="search">
      <input class="form-control me-2" type="search" placeholder="Search products..." aria-label="Search">
      <button class="btn btn-light" type="submit">Search</button>
    </form>

    <div class="d-flex align-items-center ms-3">
      <a href="profile.php" class="btn-icon"><i class="bi bi-person-circle"></i></a>
      <a href="chat.php" class="btn-icon"><i class="bi bi-chat-dots"></i></a>
      <a href="cart.php" class="btn-icon"><i class="bi bi-cart3"></i></a>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner rounded-4 shadow-sm">
      <div class="carousel-item active"><img src="images/banner1.jpg" class="d-block w-100" alt="Banner 1"></div>
      <div class="carousel-item"><img src="images/banner2.jpg" class="d-block w-100" alt="Banner 2"></div>
    </div>
  </div>
</div>

<div class="container text-center my-5">
  <h4 class="fw-bold mb-4">Shop by Category</h4>
  <div class="row justify-content-center g-4">
    <?php
    $categories = ['Food', 'Book', 'Clothing', 'Dorm supply', 'Electronics'];
    foreach ($categories as $cat) {
      $icon = "images/" . strtolower(str_replace(' ', '_', $cat)) . ".png";
      if (!file_exists($icon)) $icon = "images/default.png";
      echo "
      <div class='col-6 col-md-2'>
        <div class='category-icon'>
          <img src='$icon' width='50' alt='$cat'>
        </div>
        <div class='category-title'>$cat</div>
      </div>";
    }
    ?>
  </div>
</div>

<div class="container">
  <?php
  $categories = ['Food', 'Book', 'Clothing'];
  foreach ($categories as $category) {
      echo "<div class='section-title d-flex justify-content-between align-items-center'>
              <span>$category</span>
              <a href='view_all.php?category=$category' class='btn btn-sm btn-outline-primary'>View All</a>
            </div>";

      $stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_product_category = :category");
      $stmt->bindParam(':category', $category);
      $stmt->execute();
      $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<div class='row g-3'>";
      if ($products) {
        foreach ($products as $p) {
          $img = "images/" . $p['fld_product_image'] . ".jpg";
          if (!file_exists($img)) $img = "images/default.png";
          echo "
          <div class='col-6 col-md-4 col-lg-3'>
            <div class='card product-card'>
              <img src='$img' class='card-img-top' alt='{$p['fld_product_name']}'>
              <div class='card-body text-center'>
                <h6 class='card-title'>{$p['fld_product_name']}</h6>
                <p class='text-muted mb-1'>RM {$p['fld_product_price']}</p>
              </div>
            </div>
          </div>";
        }
      } else {
        echo "<p class='text-muted'>No products found.</p>";
      }
      echo "</div>";
  }
  ?>
</div>

<footer>
  <small>© 2025 E-Commerce UKM System. All rights reserved.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
