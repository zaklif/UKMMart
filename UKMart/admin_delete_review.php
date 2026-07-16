<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $review_id = (int)$_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM tbl_ratings_reviews WHERE id = :id");
        $stmt->bindParam(':id', $review_id);
        $stmt->execute();
        
        header("Location: admin_reviews.php?success=review_deleted");
    } catch (PDOException $e) {
        header("Location: admin_reviews.php?error=delete_failed");
    }
} else {
    header("Location: admin_reviews.php");
}
exit;
?>