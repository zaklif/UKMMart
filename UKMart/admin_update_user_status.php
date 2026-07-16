<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// DEBUG: See what's being received
error_log("GET data: " . print_r($_GET, true));
error_log("POST data: " . print_r($_POST, true));

// Get parameters
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$new_status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? $_POST['status'] : '');
$ban_reason = isset($_POST['ban_reason']) ? trim($_POST['ban_reason']) : null;
$suspend_reason = isset($_POST['suspend_reason']) ? trim($_POST['suspend_reason']) : null;

// DEBUG
error_log("User ID: $user_id");
error_log("New Status: $new_status");
error_log("Ban Reason: $ban_reason");
error_log("Suspend Reason: $suspend_reason");

// Validate inputs
if ($user_id <= 0 || empty($new_status)) {
    $_SESSION['error'] = "Invalid request parameters. User ID: $user_id, Status: $new_status";
    header("Location: admin_users.php");
    exit;
}

// Validate status
$allowed_statuses = ['active', 'suspended', 'banned'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['error'] = "Invalid status value: $new_status";
    header("Location: admin_users.php");
    exit;
}

// If banning, require a ban reason
if ($new_status === 'banned' && empty($ban_reason)) {
    $_SESSION['error'] = "Ban reason is required when banning a user.";
    header("Location: admin_users.php");
    exit;
}

// If suspending, require a suspend reason
if ($new_status === 'suspended' && empty($suspend_reason)) {
    $_SESSION['error'] = "Suspension reason is required when suspending a user.";
    header("Location: admin_users.php");
    exit;
}

try {
    // Update user status
    if ($new_status === 'banned') {
        // Update with ban reason
        $stmt = $conn->prepare("UPDATE tbl_user_ukmart 
                               SET fld_user_status = :status, 
                                   fld_user_ban_reason = :ban_reason 
                               WHERE fld_user_id = :user_id");
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':ban_reason', $ban_reason);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } elseif ($new_status === 'suspended') {
        // Update with suspend reason
        $stmt = $conn->prepare("UPDATE tbl_user_ukmart 
                               SET fld_user_status = :status, 
                                   fld_user_ban_reason = :suspend_reason 
                               WHERE fld_user_id = :user_id");
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':suspend_reason', $suspend_reason);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        // Clear ban/suspend reason when activating
        $stmt = $conn->prepare("UPDATE tbl_user_ukmart 
                               SET fld_user_status = :status, 
                                   fld_user_ban_reason = NULL 
                               WHERE fld_user_id = :user_id");
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $result = $stmt->execute();
    error_log("Query executed. Result: " . ($result ? 'success' : 'failed'));
    error_log("Rows affected: " . $stmt->rowCount());
    
    // Set success message
    if ($new_status === 'banned') {
        $_SESSION['success'] = "User has been banned successfully.";
    } elseif ($new_status === 'suspended') {
        $_SESSION['success'] = "User has been suspended successfully.";
    } elseif ($new_status === 'active') {
        $_SESSION['success'] = "User has been activated successfully.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error updating user status: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
}

// Redirect back to users page
header("Location: admin_users.php");
exit;
?>