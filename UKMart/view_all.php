<?php
session_start();
include('db_connect.php');

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Retrieve the user type (seller or buyer) from the session
$user_type = $_SESSION['user_type']; // Assume 'user_type' is set during login

// Get the user name based on their type (buyer or seller)
if ($user_type == 'seller') {
    // Get seller name
    $stmt = $conn->prepare("SELECT fld_seller_name FROM tbl_sellers_ukmart WHERE fld_seller_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row['fld_seller_name'];
} else {
    // Get buyer name
    $stmt = $conn->prepare("SELECT fld_user_name FROM tbl_user_ukmart WHERE fld_user_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row['fld_user_name'];
}

// Check if category is specified in the URL
if (!isset($_GET['category'])) {
    die("Category not specified.");
}

$category = $_GET['category'];

// Fetch all products in the selected category
$stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_product_category = :c");
$stmt->bindParam(':c', $category);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($category) ?> - UKMart</title>
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
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
      color: var(--text-dark);
    }


    /* ===== Product Cards ===== */
    .product-card {
      background: var(--bg-white);
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 20px;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--border-color);
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary-blue-light);
    }

    .product-card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 10px;
      transition: transform 0.3s ease;
      border: 1px solid var(--border-color);
    }

    .product-card img:hover {
      transform: scale(1.05);
    }

    .product-title {
      font-weight: 600;
      color: var(--text-dark);
      font-size: 16px;
      line-height: 1.3;
    }

    .product-price {
      color: var(--primary-blue);
      font-size: 18px;
      font-weight: 700;
    }

    .view-details-btn {
      display: inline-block;
      margin-top: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .view-details-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
    }

    /* ===== Cart badge ===== */
    .cart-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: red;
      color: white;
      font-size: 0.75rem;
      border-radius: 50%;
      padding: 2px 6px;
    }

    /* Pagination */
    .pagination {
      justify-content: center;
    }

    .pagination .page-link {
      color: var(--primary-blue);
      background-color: var(--bg-white);
      border: 1px solid var(--primary-blue);
      transition: all 0.3s ease;
    }

    .pagination .page-link:hover {
      color: #fff;
      background-color: var(--primary-blue);
    }

    /* Back Button Styling */
    .back-btn {
      font-size: 1.5rem;
      color: var(--primary-blue);
      padding: 10px;
      display: inline-block;
      margin-right: 10px;
      transition: all 0.3s ease;
    }

    .back-btn:hover {
      color: var(--primary-blue-dark);
      transform: scale(1.1);
    }

    .category-title-container {
      display: flex;
      align-items: center;
      margin-bottom: 30px;
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<!-- BACK BUTTON + CATEGORY TITLE -->
<div class="container">
  <div class="category-title-container">
    <a href="buyer.php" class="back-btn">
      <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    <h2><?= htmlspecialchars($category) ?> Products</h2>
  </div>
</div>

<!-- PRODUCT DISPLAY -->
<div class="container">
    <div class="row">
        <?php if ($products): ?>
            <?php foreach ($products as $p): 
                // Fetch seller info
                $stmt = $conn->prepare("SELECT fld_seller_name, fld_seller_id FROM tbl_sellers_ukmart WHERE fld_seller_id = :seller_id");
                $stmt->bindParam(':seller_id', $p['fld_seller_id']);
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
                $base = pathinfo($p['fld_product_image'], PATHINFO_FILENAME);
                $img_path = "images/default.png"; 
                $extensions = ['jpg', 'jpeg', 'png'];
                foreach ($extensions as $ext) {
                    $file = "images/$base.$ext";
                    if (file_exists($file)) {
                        $img_path = $file;
                        break;
                    }
                }
            ?>
            <div class="col-6 col-md-3">
                <a href="product_details.php?name=<?= urlencode($p['fld_product_name']) ?>" style="text-decoration:none;color:inherit;">
                    <div class="product-card">
                        <div class="card-header d-flex align-items-center">
                            <img src="<?= $seller_image_path ?>" alt="Seller Profile" class="seller-img" style="width: 50px; height: 50px; border-radius: 50%;"/>
                            <h6 class="ms-2"><?= $seller_name ?></h6>
                        </div>
                        <img src="<?= $img_path ?>" class="card-img-top product-img" alt="<?= $p['fld_product_name'] ?>"/>
                        <div class="card-body">
                            <h5 class="card-title"><?= $p['fld_product_name'] ?></h5>
                            <p class="card-text">RM <?= $p['fld_product_price'] ?></p>
                            <p class="card-text"><strong>Condition:</strong> <?= $p['fld_product_condition'] ?></p>
                            <a href="product_details.php?name=<?= urlencode($p['fld_product_name']) ?>" class="view-details-btn">View Details</a>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No products found in this category.</p>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation example">
      <ul class="pagination">
        <li class="page-item disabled">
          <a class="page-link" href="#" tabindex="-1">Previous</a>
        </li>
        <li class="page-item"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">3</a></li>
        <li class="page-item">
          <a class="page-link" href="#">Next</a>
        </li>
      </ul>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
