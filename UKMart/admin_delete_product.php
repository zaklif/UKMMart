<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        // Delete product
        $stmt = $conn->prepare("DELETE FROM tbl_product_ukmart WHERE fld_product_id = :id");
        $stmt->bindParam(':id', $product_id);
        $stmt->execute();
        
        // Also delete related reviews
        $stmt = $conn->prepare("DELETE FROM tbl_ratings_reviews WHERE product_id = :id");
        $stmt->bindParam(':id', $product_id);
        $stmt->execute();
        
        header("Location: admin_products.php?success=product_deleted");
    } catch (PDOException $e) {
        header("Location: admin_products.php?error=delete_failed");
    }
} else {
    header("Location: admin_products.php");
}
exit;
?>