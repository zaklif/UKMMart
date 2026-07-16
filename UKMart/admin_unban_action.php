<?php
session_start();
include('database.php');

// Check admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = "Invalid request parameters.";
    header("Location: admin_unban_requests.php");
    exit;
}

try {
    // Get the request details first
    $stmt = $conn->prepare("SELECT fld_user_id FROM tbl_unban_requests WHERE fld_request_id = :req_id");
    $stmt->bindParam(':req_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error'] = "Unban request not found.";
        header("Location: admin_unban_requests.php");
        exit;
    }

    $user_id = $request['fld_user_id'];

    if ($action === 'approve') {
        // Update request status to approved
        $stmt = $conn->prepare("UPDATE tbl_unban_requests SET fld_request_status = 'approved' WHERE fld_request_id = :req_id");
        $stmt->bindParam(':req_id', $request_id, PDO::PARAM_INT);
        $stmt->execute();

        // Unban the user (set status to active and clear ban reason)
        $stmt = $conn->prepare("UPDATE tbl_user_ukmart SET fld_user_status = 'active', fld_user_ban_reason = NULL WHERE fld_user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "Unban request approved. User has been unbanned successfully.";
    } else {
        // Update request status to rejected
        $stmt = $conn->prepare("UPDATE tbl_unban_requests SET fld_request_status = 'rejected' WHERE fld_request_id = :req_id");
        $stmt->bindParam(':req_id', $request_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success'] = "Unban request has been rejected.";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Error processing request: " . $e->getMessage();
}

header("Location: admin_unban_requests.php");
exit;
?>