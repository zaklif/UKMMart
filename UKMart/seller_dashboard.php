<?php
session_start();
if (!isset($_SESSION['user_email'])) {
  header("Location: login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>UKMart Seller Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #007bff 0%, #00b894 100%);
      color: white;
      min-height: 100vh;
    }
    .dashboard-container {
      max-width: 800px;
      margin: 100px auto;
      background: white;
      color: #333;
      border-radius: 15px;
      padding: 40px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      text-align: center;
    }
    h2 {
      font-weight: 700;
      color: #007bff;
    }
    .btn-logout {
      background-color: #00b894;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: bold;
      margin-top: 20px;
      transition: 0.3s;
    }
    .btn-logout:hover {
      background-color: #009973;
    }
  </style>
</head>
<body>

<div class="dashboard-container">
  <h2>Welcome to Seller Dashboard</h2>
  <p>
    Hello, <strong>
        <?php 
        echo htmlspecialchars(
            isset($_SESSION['user_name']) 
                ? $_SESSION['user_name'] 
                : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '')
        ); 
        ?>
    </strong>
</p>

  <p>Here you can manage your products, view orders, and chat with buyers.</p>
  <a href="logout.php" class="btn-logout">Logout</a>
</div>

</body>
</html>
