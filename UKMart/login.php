<?php
session_start();
include('db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type']; // seller or buyer

    try {
        // FIRST: Check if email exists in admin table
        $stmt = $conn->prepare("SELECT * FROM tbl_admin_ukmart WHERE fld_admin_email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            // Admin found
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $admin['fld_admin_password'])) {
                // Set admin session variables
                $_SESSION['admin_id'] = $admin['fld_admin_id'];
                $_SESSION['email'] = $admin['fld_admin_email'];
                $_SESSION['username'] = $admin['fld_admin_name'];
                $_SESSION['user_type'] = 'admin';
                
                header("Location: admin.php");
                exit;
            } else {
                echo "<script>alert('Incorrect admin password!');</script>";
            }
        } else {
            // Not an admin, check regular user table
            $stmt = $conn->prepare("SELECT * FROM tbl_user_ukmart WHERE fld_user_email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check if user is banned or suspended
if ($user['fld_user_status'] === 'suspended' || $user['fld_user_status'] === 'banned') {
  $_SESSION['ban_status'] = $user['fld_user_status'];

  $_SESSION['user_id'] = $user['fld_user_id'];
  
  if (!empty($user['fld_user_ban_reason'])) {
    $_SESSION['ban_reason'] = $user['fld_user_ban_reason'];


} else {
    $_SESSION['ban_reason'] = "No reason provided.";
}

  header("Location: banned.php");
  exit;
}

// Password check
if (password_verify($password, $user['fld_user_password'])) {
  // Normal login flow
  $_SESSION['email'] = $user['fld_user_email'];
  $_SESSION['username'] = $user['fld_user_name'];
  $_SESSION['user_type'] = $user_type;
  $_SESSION['user_id'] = $user['fld_user_id'];

  if ($user_type === 'seller') {
      // Seller logic
      $stmt = $conn->prepare("SELECT * FROM tbl_sellers_ukmart WHERE fld_user_id = :uid");
      $stmt->bindParam(':uid', $user['fld_user_id']);
      $stmt->execute();
      $seller = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($seller) {
          $_SESSION['seller_id'] = $seller['fld_seller_id'];
          header("Location: seller.php");
      } else {
          header("Location: create_seller_profile.php");
      }
  } else {
      header("Location: buyer.php");
  }
  exit;
} else {
  echo "<script>alert('Incorrect password!');</script>";
}

            } else {
                echo "<script>alert('No user found!');</script>";
            }
        }

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Commerce UKM System | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary-blue: #2563eb;
      --primary-blue-dark: #1e40af;
      --primary-blue-light: #3b82f6;
      --primary-blue-lighter: #60a5fa;
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
      background-size: 400% 400%;
      animation: gradientShift 15s ease infinite;
      min-height: 100vh;
      margin: 0;
      padding: 0;
      position: relative;
      overflow-y: auto;
      overflow-x: hidden;
    }
    
    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 40px 20px;
      width: 100%;
      max-width: 1000px;
      margin: 0 auto;
      box-sizing: border-box;
    }

    @keyframes gradientShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    body::before {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      border-radius: 50%;
      top: -250px;
      right: -250px;
      animation: float 20s infinite ease-in-out;
    }

    body::after {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
      border-radius: 50%;
      bottom: -200px;
      left: -200px;
      animation: float 25s infinite ease-in-out;
    }

    @keyframes float {
      0%, 100% { transform: translate(0, 0) rotate(0deg); }
      33% { transform: translate(30px, -30px) rotate(120deg); }
      66% { transform: translate(-20px, 20px) rotate(240deg); }
    }

    .login-container {
      width: 100%;
      max-width: 1000px;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
      border: 1px solid rgba(255, 255, 255, 0.3);
      display: flex;
      overflow: hidden;
      width: 100%;
      animation: slideUp 0.6s ease-out;
      position: relative;
      z-index: 1;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-left {
      flex: 1;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      background-size: 200% 200%;
      animation: gradientMove 8s ease infinite;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px;
      position: relative;
      overflow: hidden;
    }

    @keyframes gradientMove {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    .login-left::before {
      content: '';
      position: absolute;
      width: 200%;
      height: 200%;
      background: url('images/login-illustration.png') no-repeat center;
      background-size: contain;
      opacity: 0.1;
      animation: rotate 30s linear infinite;
    }

    @keyframes rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .login-left::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.5; }
      50% { transform: scale(1.1); opacity: 0.8; }
    }

    .login-left-content {
      text-align: center;
      color: white;
      z-index: 2;
      position: relative;
    }

    .login-left-content h1 {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 15px;
      letter-spacing: -1px;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .login-left-content p {
      font-size: 1.2rem;
      opacity: 0.95;
      font-weight: 400;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }


    .login-right {
      padding: 60px 50px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    }

    .login-right h2 {
      font-weight: 800;
      font-size: 2.2rem;
      margin-bottom: 8px;
      color: var(--text-dark);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .login-right p {
      color: var(--text-gray);
      font-size: 1rem;
      margin-bottom: 35px;
      font-weight: 400;
    }

    .form-group {
      margin-bottom: 24px;
      position: relative;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
      font-size: 0.9rem;
    }

    .input-wrapper {
      position: relative;
    }

    .input-wrapper i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-gray);
      font-size: 1rem;
      transition: color 0.3s;
    }

    .form-control {
      border-radius: 12px;
      padding: 16px 20px 16px 52px;
      border: 2px solid var(--border-color);
      font-size: 1rem;
      transition: all 0.3s ease;
      background: var(--bg-white);
      width: 100%;
      box-shadow: var(--shadow-sm);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-blue);
      background: var(--bg-white);
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), var(--shadow-md);
      transform: translateY(-2px);
    }

    .form-control::placeholder {
      color: #94a3b8;
    }

    .role-label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 12px;
      font-size: 0.9rem;
      display: block;
    }

    .choice-wrapper {
      display: flex;
      position: relative;
      gap: 12px;
      margin-bottom: 32px;
      background: var(--bg-light);
      padding: 6px;
      border-radius: 12px;
    }

    .role-highlight {
      position: absolute;
      width: calc(50% - 6px);
      height: calc(100% - 12px);
      top: 6px;
      left: 6px;
      border-radius: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
      transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      z-index: 0;
    }

    .user-btn {
      flex: 1;
      border: none;
      padding: 14px 20px;
      height: 52px;
      background: transparent;
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      z-index: 1;
      transition: all 0.3s ease;
      color: var(--text-gray);
      position: relative;
    }

    .user-btn:hover {
      color: var(--text-dark);
    }

    .user-btn.active-btn {
      color: #fff;
    }

    .login-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 12px;
      color: white;
      padding: 18px;
      width: 100%;
      font-weight: 700;
      font-size: 1.1rem;
      border: none;
      transition: all 0.3s ease;
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
      position: relative;
      overflow: hidden;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .login-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.5s;
    }

    .login-btn:hover::before {
      left: 100%;
    }

    .login-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
    }

    .login-btn:active {
      transform: translateY(-1px);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .signup-text {
      text-align: center;
      margin-top: 24px;
      color: var(--text-gray);
      font-size: 0.9rem;
    }

    .signup-text a {
      text-decoration: none;
      font-weight: 600;
      color: var(--primary-blue);
      transition: all 0.3s;
      position: relative;
    }

    .signup-text a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary-blue);
      transition: width 0.3s;
    }

    .signup-text a:hover::after {
      width: 100%;
    }

    .signup-text a:hover {
      color: var(--primary-blue-dark);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .login-card {
        flex-direction: column;
      }

      .login-left {
        padding: 40px 30px;
        min-height: 200px;
      }

      .login-left-content h1 {
        font-size: 1.8rem;
      }

      .login-right {
        padding: 40px 30px;
      }

      .login-right h2 {
        font-size: 1.5rem;
      }
    }

    /* Loading animation for form submission */
    .login-btn.loading {
      pointer-events: none;
      opacity: 0.7;
    }

    .login-btn.loading::after {
      content: '';
      position: absolute;
      width: 20px;
      height: 20px;
      top: 50%;
      left: 50%;
      margin-left: -10px;
      margin-top: -10px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
<div class="login-container">
  <div class="login-card">
    <!-- Left Side with Branding -->
    <div class="login-left">
      <div class="login-left-content">
        <h1>UKMart</h1>
        <p>Your trusted marketplace for all your needs</p>
      </div>
    </div>

    <!-- Login Form -->
    <div class="login-right">
      <h2>Welcome Back</h2>
      <p>Sign in to continue to your account</p>

      <form method="post" autocomplete="off" id="loginForm">
        <div class="form-group">
          <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email address" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password"><i class="fas fa-lock"></i> Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
          </div>
        </div>

        <label class="role-label"><i class="fas fa-user-tag"></i> Login as</label>
        <div class="choice-wrapper">
          <div class="role-highlight" id="roleIndicator"></div>
          <button type="button" id="sellerBtn" class="user-btn" onclick="selectRole('seller')">
            <i class="fas fa-store"></i> Seller
          </button>
          <button type="button" id="buyerBtn" class="user-btn active-btn" onclick="selectRole('buyer')">
            <i class="fas fa-shopping-cart"></i> Buyer
          </button>
        </div>

        <input type="hidden" name="user_type" id="user_type" value="buyer">

        <button type="submit" class="login-btn" id="submitBtn">
          <span>Sign In</span>
        </button>
      </form>

      <div class="signup-text">
        <small>Don't have an account? <a href="register.php">Create one now</a></small>
      </div>
    </div>
  </div>
</div>

<script>
function selectRole(type) {
  const sellerBtn = document.getElementById("sellerBtn");
  const buyerBtn = document.getElementById("buyerBtn");
  const indicator = document.getElementById("roleIndicator");
  const hiddenInput = document.getElementById("user_type");

  hiddenInput.value = type;

    if (type === "seller") {
    indicator.style.transform = "translateX(0%)";
    indicator.style.background = "#2563eb";
    indicator.style.boxShadow = "0 4px 6px -1px rgba(0, 0, 0, 0.1)";

    sellerBtn.classList.add("active-btn");
    buyerBtn.classList.remove("active-btn");
  } else {
    indicator.style.transform = "translateX(100%)";
    indicator.style.background = "#2563eb";
    indicator.style.boxShadow = "0 4px 6px -1px rgba(0, 0, 0, 0.1)";

    buyerBtn.classList.add("active-btn");
    sellerBtn.classList.remove("active-btn");
  }
}

// Initialize default highlight on Buyer
document.addEventListener("DOMContentLoaded", () => {
  const indicator = document.getElementById("roleIndicator");
  indicator.style.transform = "translateX(100%)";
  indicator.style.background = "#2563eb";
  indicator.style.boxShadow = "0 4px 6px -1px rgba(0, 0, 0, 0.1)";

  // Add form submission loading state
  const form = document.getElementById("loginForm");
  const submitBtn = document.getElementById("submitBtn");
  
  form.addEventListener("submit", function() {
    submitBtn.classList.add("loading");
    submitBtn.querySelector("span").textContent = "Signing in...";
  });

  // Add input focus animations
  const inputs = document.querySelectorAll(".form-control");
  inputs.forEach(input => {
    input.addEventListener("focus", function() {
      this.parentElement.querySelector("i").style.color = "#2563eb";
    });
    
    input.addEventListener("blur", function() {
      if (!this.value) {
        this.parentElement.querySelector("i").style.color = "#64748b";
      }
    });
  });
});
</script>
</body>
</html>