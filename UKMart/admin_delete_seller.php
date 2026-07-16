<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $seller_id = (int)$_GET['id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete reviews for seller's products
        $stmt = $conn->prepare("DELETE FROM tbl_ratings_reviews WHERE seller_id = :id");
        $stmt->bindParam(':id', $seller_id);
        $stmt->execute();
        
        // Delete products
        $stmt = $conn->prepare("DELETE FROM tbl_product_ukmart WHERE fld_seller_id = :id");
        $stmt->bindParam(':id', $seller_id);
        $stmt->execute();
        
        // Delete seller
        $stmt = $conn->prepare("DELETE FROM tbl_sellers_ukmart WHERE fld_seller_id = :id");
        $stmt->bindParam(':id', $seller_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        header("Location: admin_sellers.php?success=seller_deleted");
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();
        header("Location: admin_sellers.php?error=delete_failed");
    }
} else {
    header("Location: admin_sellers.php");
}
exit;
?>