<?php

//kena buang
header('Content-Type: application/json');
include('db_connect.php');
session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$result = [];


// include('db_connect.php');
// session_start();

// $q = trim($_GET['q']);
// $user_id = $_SESSION['user_id'];
// $result = [];

// --- Fetch product search results ---
if ($q !== "") {
    $stmt = $conn->prepare("
        SELECT fld_product_name, fld_product_price, fld_product_image, fld_product_category
        FROM tbl_products_ukmart
        WHERE fld_product_name LIKE :s
        ORDER BY fld_product_name ASC
        LIMIT 10
    ");
    $search = "%$q%";
    $stmt->bindParam(':s', $search);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $image = "images/default.png";

        // Handle file extension auto-detect
        $base = pathinfo($row['fld_product_image'], PATHINFO_FILENAME);
        $exts = ['jpg','jpeg','png','JPG','JPEG','PNG'];
        foreach ($exts as $e) {
            if (file_exists("images/$base.$e")) {
                $image = "images/$base.$e";
                break;
            }
        }

        $result[] = [
            "name" => $row['fld_product_name'],
            "price" => $row['fld_product_price'],
            "image" => $image,
            "category" => $row['fld_product_category']
        ];
    }

    // --- Save the search term for the user ---
    $stmt = $conn->prepare("
        INSERT INTO tbl_user_search_ukmart (user_id, search_term)
        VALUES (:uid, :term)
        ON DUPLICATE KEY UPDATE search_date = CURRENT_TIMESTAMP
    ");
    $stmt->bindParam(':uid', $user_id);
    $stmt->bindParam(':term', $q);
    $stmt->execute();
}

// --- Fetch recent searches for this user (top 5) ---
$recent = [];
$stmt = $conn->prepare("
    SELECT search_term 
    FROM tbl_user_search_ukmart 
    WHERE user_id = :uid
    ORDER BY search_date DESC 
    LIMIT 5
");
$stmt->bindParam(':uid', $user_id);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $recent[] = $row['search_term'];
}

// Return JSON
echo json_encode([
    "products" => $result,
    "recent" => $recent
]);
