<?php
include 'database.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $buyer_id   = isset($_GET['buyer']) ? $_GET['buyer'] : 0;
    $seller_id  = isset($_GET['seller']) ? $_GET['seller'] : 0;
    $product_id = isset($_GET['product']) ? $_GET['product'] : 0;

    if (!$buyer_id || !$seller_id || !$product_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT sender_type, sender_name, message, message_type, file_path, 
               DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') as timestamp, 
               COALESCE(status, 'pending') as status,
               quantity
        FROM tbl_chat_ukmart
        WHERE buyer_id = ? AND seller_id = ? AND product_id = ?
        ORDER BY timestamp ASC
    ");

    $stmt->execute([$buyer_id, $seller_id, $product_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/json");
    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>