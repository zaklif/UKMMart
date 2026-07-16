
<?php
include('database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password_raw = $_POST['password'];
  
  // Validation errors array
  $errors = [];
  
  // TCON-01-001, TCON-01-002, TCON-01-003: Username validation (5-15 alphanumeric characters)
  if (!preg_match('/^[a-zA-Z0-9]{5,15}$/', $username)) {
    if (strlen($username) < 5) {
      $errors[] = "Username must be at least 5 characters long.";
    } elseif (strlen($username) > 15) {
      $errors[] = "Username must not exceed 15 characters.";
    } else {
      $errors[] = "Username must contain only letters and numbers.";
    }
  }
  
  // TCON-01-004, TCON-01-005, TCON-01-006: Password validation (8-16 characters, must include number and special character)
  if (strlen($password_raw) < 8) {
    $errors[] = "Password must be at least 8 characters long.";
  } elseif (strlen($password_raw) > 16) {
    $errors[] = "Password must not exceed 16 characters.";
  } elseif (!preg_match('/[0-9]/', $password_raw)) {
    $errors[] = "Password must contain at least one number.";
  } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password_raw)) {
    $errors[] = "Password must contain at least one special character.";
  }
  
  // Email validation
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
  }
  
  // If there are validation errors, show them
  if (!empty($errors)) {
    $error_message = implode("\\n", $errors);
    echo "<script>alert('Registration failed:\\n\\n" . addslashes($error_message) . "');</script>";
  } else {
    // Proceed with registration if validation passes
    $password = password_hash($password_raw, PASSWORD_DEFAULT);
    
    try {
      $check = $conn->prepare("SELECT * FROM tbl_user_ukmart WHERE fld_user_name = :username OR fld_user_email = :email");
      $check->bindParam(':username', $username);
      $check->bindParam(':email', $email);
      $check->execute();

      if ($check->rowCount() > 0) {
        echo "<script>alert('Username or email already exists!');</script>";
      } else {
        $stmt = $conn->prepare("INSERT INTO tbl_user_ukmart (fld_user_name, fld_user_email, fld_user_password) VALUES (:username, :email, :password)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        if ($stmt->execute()) {
          echo "<script>alert('Registration successful! You can now log in.');window.location='login.php';</script>";
        } else {
          echo "<script>alert('Something went wrong. Try again.');</script>";
        }
      }
    } catch (PDOException $e) {
      echo "Database error: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - E-Commerce UKM System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
      background-size: 400% 400%;
      animation: gradientShift 15s ease infinite;
      min-height: 100vh;
      padding: 20px;
      position: relative;
      overflow-y: auto;
      overflow-x: hidden;
    }
    
    .signup-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 40px 20px;
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
    .signup-container {
      width: 100%;
      max-width: 1000px;
      margin: 0 auto;
    }
    .signup-card {
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
    .signup-left {
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

    .signup-left::before {
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

    .signup-left-content {
      text-align: center;
      color: white;
      z-index: 2;
      position: relative;
    }
    .signup-left-content h1 {
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
    .signup-left-content p {
      font-size: 1.2rem;
      opacity: 0.95;
      font-weight: 400;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    .signup-right {
      flex: 1;
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    }
    .signup-card h2 {
      font-weight: 800;
      font-size: 2.2rem;
      margin-bottom: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .signup-card p {
      color: var(--text-gray);
      font-size: 1rem;
      margin-bottom: 30px;
      font-weight: 400;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
      font-size: 0.9rem;
    }
    .form-control {
      border-radius: 12px;
      padding: 16px 20px;
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
    .signup-btn {
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
    .signup-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.5s;
    }
    .signup-btn:hover::before {
      left: 100%;
    }
    .signup-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
    }
    .signup-btn:active {
      transform: translateY(-1px);
    }
    .login-text {
      text-align: center;
      margin-top: 20px;
      color: var(--text-gray);
      font-size: 0.9rem;
    }
    .login-text a {
      text-decoration: none;
      font-weight: 600;
      color: var(--primary-blue);
      transition: all 0.3s;
      position: relative;
    }
    .login-text a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary-blue);
      transition: width 0.3s;
    }
    .login-text a:hover::after {
      width: 100%;
    }
    .login-text a:hover {
      color: var(--primary-blue-dark);
    }
    @media (max-width: 768px) {
      .signup-card {
        flex-direction: column;
      }
      .signup-left {
        padding: 40px 30px;
        min-height: 200px;
      }
      .signup-left-content h1 {
        font-size: 1.8rem;
      }
      .signup-right {
        padding: 40px 30px;
      }
      .signup-card h2 {
        font-size: 1.5rem;
      }
      .signup-left-content p {
        font-size: 1rem;
      }
    }

  </style>
</head>
<body>
<div class="signup-container">
  <div class="signup-card">
    <div class="signup-left">
      <div class="signup-left-content">
        <h1>UKMart</h1>
        <p>Your trusted marketplace for all your needs</p>
      </div>
    </div>
    <div class="signup-right">
      <h2>Create Account</h2>
      <p>Join UKMart today and start shopping</p>
      <form method="post" autocomplete="off">
        <div class="mb-3">
          <label>Username</label>
          <input type="text" name="username" class="form-control" placeholder="Enter username" required>
          <div class="validation-hint">5-15 alphanumeric characters only</div>
        </div>
        <div class="mb-3">
          <label>Email</label>
          <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
        </div>
        <div class="mb-3">
          <label>Password</label>
          <input type="password" name="password" class="form-control" placeholder="Create a password" required>
          <div class="validation-hint">8-16 characters, must include number and special character</div>
        </div>
        <button type="submit" class="signup-btn">Sign Up</button>
      </form>
      <div class="login-text">
        <small>Already have an account? <a href="login.php">Login here</a></small>
      </div>
    </div>
  </div>
</div>
</body>
</html>