<?php
include('db_connect.php');
session_start();
if (!isset($_SESSION['email'])) {
  header('Location: ../login.php');
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Seller Dashboard | E-Commerce UKM System</title>
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
      background: var(--light-bg);
      font-family: 'Poppins', sans-serif;
    }
    .navbar {
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    .navbar-brand, .nav-link, .navbar-text {
      color: #fff !important;
    }
    .btn-icon {
      background: transparent;
      border: none;
      color: #fff;
      font-size: 1.5rem;
      margin-left: 15px;
      transition: 0.3s;
    }
    .btn-icon:hover { transform: scale(1.1); color: #ffd700; }

    .dashboard-container {
      padding: 50px 20px;
    }
    .dashboard-card {
      border: none;
      border-radius: 20px;
      background: var(--card-bg);
      box-shadow: 0 6px 15px rgba(0,0,0,0.05);
      transition: 0.3s;
      text-align: center;
    }
    .dashboard-card:hover {
      transform: translateY(-8px);
    }
    .card-icon {
      font-size: 2.5rem;
      padding: 25px;
      border-radius: 20px 20px 0 0;
      color: #fff;
    }
    .bg-product { background: #6d79e7; }
    .bg-add { background: #00b894; }
    .bg-order { background: #ffb84d; }
    .bg-profile { background: #ff7675; }
    footer {
      background: #fff;
      color: #666;
      border-top: 1px solid #ddd;
      padding: 15px;
      text-align: center;
      margin-top: 40px;
    }
  </style>
</head>

<body>
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">UKM Seller Portal</a>
    <div class="ms-auto d-flex align-items-center">
      <span class="me-3 text-white fw-semibold">
        Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>
      </span>
      <a href="profile.php" class="btn-icon"><i class="bi bi-person-circle"></i></a>
      <a href="logout.php" class="btn-icon"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</nav>

<div class="container dashboard-container">
  <h3 class="fw-bold mb-4 text-center">Seller Dashboard</h3>
  <div class="row g-4 justify-content-center">

    <div class="col-6 col-md-3">
      <a href="my_products.php" class="text-decoration-none text-dark">
        <div class="dashboard-card">
          <div class="card-icon bg-product"><i class="bi bi-box-seam"></i></div>
          <div class="card-body">
            <h5>My Products</h5>
            <p class="text-muted small">Manage your listings</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-6 col-md-3">
      <a href="add_product.php" class="text-decoration-none text-dark">
        <div class="dashboard-card">
          <div class="card-icon bg-add"><i class="bi bi-plus-circle"></i></div>
          <div class="card-body">
            <h5>Add Product</h5>
            <p class="text-muted small">List new items</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-6 col-md-3">
      <a href="orders.php" class="text-decoration-none text-dark">
        <div class="dashboard-card">
          <div class="card-icon bg-order"><i class="bi bi-cart-check"></i></div>
          <div class="card-body">
            <h5>Orders</h5>
            <p class="text-muted small">View received orders</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col-6 col-md-3">
      <a href="profile.php" class="text-decoration-none text-dark">
        <div class="dashboard-card">
          <div class="card-icon bg-profile"><i class="bi bi-person-badge"></i></div>
          <div class="card-body">
            <h5>Profile</h5>
            <p class="text-muted small">Update your info</p>
          </div>
        </div>
      </a>
    </div>

  </div>
</div>

<footer>
  <small>© 2025 E-Commerce UKM System | Seller Dashboard</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
