<?php
session_start();
include('db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = isset($row['total']) && $row['total'] !== null ? (int)$row['total'] : 0;
    
    echo json_encode(['count' => $cartCount]);
} catch (Exception $e) {
    error_log("Error getting cart count: " . $e->getMessage());
    echo json_encode(['count' => 0]);
}
?>
