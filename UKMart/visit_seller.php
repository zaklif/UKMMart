<?php
session_start();
include('db_connect.php');

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get seller_id from URL
$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;

if ($seller_id <= 0) {
    die("Invalid seller.");
}

// Fetch seller details
$stmt = $conn->prepare("SELECT * FROM tbl_sellers_ukmart WHERE fld_seller_id = ?");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    die("Seller not found.");
}

$seller_name = $seller['fld_seller_name'];

// Seller profile image
$profile_path = file_exists("profile/{$seller_id}.jpg") ? "profile/{$seller_id}.jpg" :
                 (file_exists("profile/{$seller_id}.png") ? "profile/{$seller_id}.png" : "profile/default_user.png");

// Fetch seller products
$products_stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_seller_id = ?");
$products_stmt->execute([$seller_id]);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Profile | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
.header-bar { background: #0d6efd; color: white; font-weight: bold; padding: 12px 20px; font-size: 18px; }
.profile-card { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.profile-pic { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; }
.product-card { background: #fff; border-radius: 15px; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); display: flex; align-items: flex-start; margin-bottom: 15px; }
.product-card img { width: 150px; height: 150px; border-radius: 12px; object-fit: cover; margin-right: 15px; }
.status-available { background-color: #00b894; color: white; border-radius: 5px; padding: 3px 10px; font-size: 12px; }
.status-sold { background-color: #ff4d4f; color: white; border-radius: 5px; padding: 3px 10px; font-size: 12px; }

/* Responsive Design */
@media(max-width: 992px) {
  .profile-card {
    margin-bottom: 20px;
  }
  .product-card {
    flex-direction: column;
    text-align: center;
  }
  .product-card img {
    margin-right: 0;
    margin-bottom: 15px;
    width: 100%;
    max-width: 200px;
    height: auto;
  }
}

@media(max-width: 768px) {
  .profile-pic {
    width: 120px;
    height: 120px;
  }
  .profile-card {
    padding: 20px;
  }
  .product-card {
    padding: 12px;
  }
  .product-card img {
    width: 100%;
    max-width: 150px;
  }
  .back-btn {
    font-size: 1.3rem !important;
  }
}

@media(max-width: 576px) {
  .profile-card {
    padding: 15px;
  }
  .profile-pic {
    width: 100px;
    height: 100px;
  }
  .product-card {
    padding: 10px;
    margin-bottom: 12px;
  }
  .product-card img {
    width: 100%;
    max-width: 120px;
    height: 120px;
  }
  .product-card h6 {
    font-size: 0.95rem;
  }
  .product-card p {
    font-size: 0.85rem;
  }
  .status-available,
  .status-sold {
    font-size: 10px;
    padding: 2px 8px;
  }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<!-- Back Button -->
<div class="container my-3">
    <a href="buyer.php" class="text-decoration-none" style="font-size: 1.5rem; color: #2563eb; padding: 10px; display: inline-block; transition: all 0.3s ease;" title="Back to Home">
        <i class="bi bi-arrow-left-circle-fill"></i> Back to Home
    </a>
</div>

<div class="container my-4">
    <div class="row">
    
        <!-- LEFT: Seller Profile -->
        <div class="col-md-4">
            <div class="profile-card text-center">
                <img src="<?= $profile_path ?>" class="profile-pic mb-3">
                <h5 class="fw-bold"><?= htmlspecialchars($seller_name); ?></h5>

                <p class="text-muted mt-2">Seller Profile</p>

                <hr>

                <p><strong>Total Products:</strong> <?= count($products); ?></p>
            </div>
        </div>

        <!-- RIGHT: Seller Products -->
        <div class="col-md-8">
            <h5 class="mb-3">Products by <?= htmlspecialchars($seller_name); ?></h5>

            <?php if (!$products): ?>
                <p class="text-muted">This seller has no products yet.</p>
            <?php endif; ?>

            <?php foreach ($products as $product): 
                $quantity = (int)$product['fld_product_quantity'];
                $status = $quantity > 0 ? 'Available' : 'Sold';

                // Product image
                $file = $product['fld_product_image'];
                $extensions = ['jpg','jpeg','png'];
                $product_img_path = "images/default.png";

                foreach ($extensions as $ext) {
                    if (file_exists("images/" . pathinfo($file, PATHINFO_FILENAME) . "." . $ext)) {
                        $product_img_path = "images/" . pathinfo($file, PATHINFO_FILENAME) . "." . $ext;
                        break;
                    }
                }
            ?>

            <div class="product-card">
                <img src="<?= $product_img_path ?>">
                <div>
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($product['fld_product_name']) ?></h6>
                    <p class="text-muted mb-1">RM<?= number_format($product['fld_product_price'], 2) ?></p>
                    <p class="text-secondary small">
                        <?= htmlspecialchars($product['fld_product_description']) ?>
                    </p>

                    <?php if ($status == 'Available'): ?>
                        <span class="status-available">Available</span>
                    <?php else: ?>
                        <span class="status-sold">Sold</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php endforeach; ?>

        </div>

    </div>
</div>

</body>
</html>
