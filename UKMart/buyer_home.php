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
  <title>UKMart Buyer Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #00b894 0%, #007bff 100%);
      color: white;
      min-height: 100vh;
    }
    .home-container {
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
      color: #00b894;
    }
    .btn-logout {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: bold;
      margin-top: 20px;
      transition: 0.3s;
    }
    .btn-logout:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>

<div class="home-container">
  <h2>Welcome to Buyer Home</h2>
  <p>
    Hello, <strong>
        <?php 
        echo htmlspecialchars(
            isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '')
        ); 
        ?>
    </strong>
</p>

  <p>Explore products, chat with sellers, and enjoy shopping with UKMart!</p>
  <a href="logout.php" class="btn-logout">Logout</a>
</div>

</body>
</html>

