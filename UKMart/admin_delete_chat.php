<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $chat_id = (int)$_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM tbl_chat_ukmart WHERE chat_id = :id");
        $stmt->bindParam(':id', $chat_id);
        $stmt->execute();
        
        header("Location: admin_chats.php?success=message_deleted");
    } catch (PDOException $e) {
        header("Location: admin_chats.php?error=delete_failed");
    }
} else {
    header("Location: admin_chats.php");
}
exit;
?>