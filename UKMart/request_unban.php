<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['ban_status']) || $_SESSION['ban_status'] !== 'suspended') {
    header("Location: login.php");
    exit;
}


$user_id = $_SESSION['user_id'];
$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);

    if (empty($message)) {
        $error = "Please provide a reason for your request.";
    } else {
        // Check if user already submitted a pending request
        $stmt = $conn->prepare("SELECT * FROM tbl_unban_requests WHERE fld_user_id = :uid AND fld_request_status = 'pending'");
        $stmt->bindParam(':uid', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = "You already have a pending unban request.";
        } else {
            // Insert new request
            $stmt = $conn->prepare("INSERT INTO tbl_unban_requests (fld_user_id, fld_request_message) VALUES (:uid, :msg)");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':msg', $message);
            if ($stmt->execute()) {
                $success = "Your unban request has been submitted successfully.";
            } else {
                $error = "Failed to submit your request. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Unban | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    padding: 40px;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    max-width: 500px;
    width: 100%;
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


.card textarea {
    resize: none;
    height: 120px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    padding: 15px;
    width: 100%;
    font-size: 1rem;
}

.card button {
    margin-top: 20px;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    transition: all 0.3s;
}

.card button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(102,126,234,0.5);
}

.alert {
    margin-top: 15px;
}
</style>
</head>
<body>
<div class="card">
    <h1>Request Account Unban</h1>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php else: ?>
        <form method="post">
            <textarea name="message" placeholder="Explain why your account should be unbanned..." required><?php echo htmlspecialchars($message); ?></textarea>
            <button type="submit">Submit Request</button>
        </form>
    <?php endif; ?>

    <a href="login.php" class="btn btn-secondary mt-3">Back to Login</a>
</div>
</body>
</html>
