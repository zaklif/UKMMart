<?php
session_start();
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>007 Store System : Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CDN -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

  <style>
    body {
      background-color: #fdf6f0; /* Nude/soft background */
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .navbar {
      background-color: #800000; /* Maroon */
      border: none;
      border-radius: 0;
      margin-bottom: 0;
    }

    .navbar-brand,
    .navbar-nav > li > a,
    .navbar-text {
      color: #ffffff !important;
      font-weight: bold;
      letter-spacing: 1px;
    }

    .navbar-nav > li > a:hover,
    .navbar-brand:hover {
      color: #fdd9b5 !important; /* Nude on hover */
    }

    .dropdown-menu {
      background-color: #fff;
      border-radius: 0;
    }

    .dropdown-menu > li > a {
      color: #800000;
    }

    .dropdown-menu > li > a:hover {
      background-color: #fdd9b5;
      color: #000;
    }

    .center-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      height: calc(100vh - 50px);
      background-image: linear-gradient(to bottom right, #fdf6f0, #fff);
    }

    .hero-img {
      max-width: 400px;
      width: 100%;
      height: auto;
      filter: drop-shadow(2px 4px 6px #80000055);
    }

    footer {
      background-color: #800000;
      color: white;
      text-align: center;
      padding: 10px 0;
      position: fixed;
      bottom: 0;
      width: 100%;
    }
  </style>
</head>
<body>

  <!-- Navigation Bar -->
  <nav class="navbar navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <a class="navbar-brand" href="#">007 Store</a>
      </div>
      <ul class="nav navbar-nav">
        <li class="active"><a href="index.php">Home</a></li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <li>
          <p class="navbar-text">Hi, <?php echo htmlspecialchars($_SESSION['fld_staff_name']); ?>!</p>
        </li>
        <li class="dropdown">
          <a class="dropdown-toggle" data-toggle="dropdown" href="#">Menu
            <span class="caret"></span>
          </a>
          <ul class="dropdown-menu">
            <li><a href="products.php">Products</a></li>
            <li><a href="customers.php">Customers</a></li>
            <li><a href="staffs.php">Staffs</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Centered Image -->
  <div class="center-wrapper">
    <img src="products/007StoreLogo.png" alt="007 Store Banner" class="hero-img">
  </div>

  <!-- Optional Footer -->
  <footer>
    &copy; 2025 007 Store System. All Rights Reserved.
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

</body>
</html>
