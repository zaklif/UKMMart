<?php
include_once 'database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $user_password = md5($_POST['password']); // renamed variable

  try {
    // ✅ connect using real DB credentials
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if email already exists
    $check = $conn->prepare("SELECT * FROM tbl_user_ukmart WHERE fld_user_email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
      $error = "Email already registered!";
    } else {
      // Insert new account
      $stmt = $conn->prepare("INSERT INTO tbl_user_ukmart 
        (fld_user_name, fld_user_email, fld_user_password)
        VALUES (?, ?, ?)");
      $stmt->execute([$name, $email, $user_password]);
      $success = "Account created successfully! You can now login.";
    }

  } catch (PDOException $e) {
    $error = "Connection failed: " . $e->getMessage();
  }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sign Up - E-Commerce UKM System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9f9f9;
    }
    .signup-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .signup-card {
      background: #fff;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      display: flex;
      overflow: hidden;
      width: 900px;
      max-width: 95%;
    }
    .signup-left {
      background: linear-gradient(135deg, #fff 0%, #f7f7f7 100%);
      padding: 60px 40px;
      flex: 1;
      text-align: center;
    }
    .signup-left img {
      width: 80%;
      max-width: 350px;
    }
    .signup-right {
      background-color: #fff;
      padding: 60px;
      flex: 1;
    }
    .signup-card h2 {
      font-weight: 700;
      margin-bottom: 10px;
    }
    .signup-card p {
      color: #666;
      font-size: 15px;
      margin-bottom: 30px;
    }
    .form-control {
      border-radius: 10px;
      padding: 10px 15px;
    }
    .user-btn {
      border: none;
      border-radius: 10px;
      width: 48%;
      padding: 10px;
      font-weight: bold;
      color: white;
      cursor: pointer;
    }
    .seller {
      background-color: #00e0b8;
    }
    .buyer {
      background-color: #6d79e7;
    }
    .signup-btn {
      background-color: #000;
      border-radius: 10px;
      color: white;
      padding: 12px;
      width: 100%;
      font-weight: bold;
      border: none;
      transition: 0.3s;
    }
    .signup-btn:hover {
      background-color: #333;
    }
    .login-text {
      text-align: center;
      margin-top: 15px;
    }
    .login-text a {
      text-decoration: none;
      font-weight: 600;
      color: #000;
    }
    .alert {
      border-radius: 10px;
    }
  </style>
</head>
<body>

<div class="signup-container">
  <div class="signup-card">

    <div class="signup-left">
      <img src="images/signup-illustration.png" alt="E-commerce illustration">
    </div>

    <div class="signup-right">
      <h2>Create Account</h2>
      <p>Join E-Commerce UKM System today</p>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php elseif (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="mb-3">
          <label>Username</label>
          <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
        </div>

        <div class="mb-3">
          <label>Email</label>
          <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
        </div>

        <div class="mb-3">
          <label>Password</label>
          <input type="password" name="password" class="form-control" placeholder="Create a password" required>
        </div>

<!--         <div class="d-flex justify-content-between mb-3">
          <button type="button" class="user-btn seller" onclick="setUserType('seller')">Seller</button>
          <button type="button" class="user-btn buyer" onclick="setUserType('buyer')">Buyer</button>
        </div>
        <input type="hidden" name="user_type" id="user_type" value="buyer"> -->

        <button type="submit" class="signup-btn">Sign Up</button>
      </form>

      <div class="login-text">
        <small>Already have an account? <a href="login.php">Login here</a></small>
      </div>
    </div>
  </div>
</div>

<script>
  function setUserType(type) {
    document.getElementById('user_type').value = type;
  }
</script>

</body>
</html>
