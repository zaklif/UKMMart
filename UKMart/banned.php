<?php
session_start();

if (!isset($_SESSION['ban_status'])) {
    header("Location: login.php");
    exit;
}

$status = $_SESSION['ban_status'];
$reason = $_SESSION['ban_reason'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account <?php echo ucfirst($status); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.card {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(15px);
    border-radius: 24px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    max-width: 450px;
}

.card h1 {
    font-size: 2rem;
    margin-bottom: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;   /* standard */
    color: transparent;      /* fallback */
}


.card p {
    font-size: 1.1rem;
    color: #374151;
    margin-bottom: 30px;
}

.card .btn {
    border-radius: 12px;
    padding: 12px 25px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
}

.card .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: #fff;
    box-shadow: 0 8px 20px rgba(102,126,234,0.4);
}

.card .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(102,126,234,0.5);
}

.card .btn-secondary {
    background: #f3f4f6;
    border: none;
    color: #374151;
}

</style>
</head>
<body>
<div class="card">
    <h1>Account <?php echo ucfirst($status); ?></h1>
    <p>Reason: <?php echo htmlspecialchars($reason); ?></p>

    <?php if ($status === 'suspended') : ?>
        <a href="request_unban.php" class="btn btn-primary">Request Unban</a>
    <?php endif; ?>
    <a href="login.php" class="btn btn-secondary mt-3">Back to Login</a>
</div>
</body>
</html>
