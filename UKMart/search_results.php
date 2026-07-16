<?php
include('db_connect.php');
session_start();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    $products = []; // no results if query empty
} else {
    // Use LOWER() to make search case-insensitive
    $stmt = $conn->prepare("
        SELECT * 
        FROM tbl_products_ukmart 
        WHERE LOWER(fld_product_name) LIKE LOWER(:term)
           OR LOWER(fld_product_category) LIKE LOWER(:term)
    ");
    $searchTerm = "%$query%";
    $stmt->bindParam(':term', $searchTerm);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Search Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-img-top { height: 150px; object-fit: contain; }
</style>
</head>
<body class="bg-light">

<div class="container mt-4">

<h3>Search Results for: <span class="text-primary"><?php echo htmlspecialchars($query); ?></span></h3>
<hr>

<div class="row">
<?php
if ($products) {
    foreach ($products as $product) {
        // Find product image
        $base = pathinfo($product['fld_product_image'], PATHINFO_FILENAME);
        $extensions = ['jpg','jpeg','png','JPG','JPEG','PNG'];
        $img_path = "images/default.png"; // fallback image

        foreach ($extensions as $ext) {
            if (file_exists("images/$base.$ext")) {
                $img_path = "images/$base.$ext";
                break;
            }
        }

        echo "
        <div class='col-md-3 mb-4'>
            <div class='card h-100'>
                <img src='$img_path' class='card-img-top'>
                <div class='card-body'>
                    <h6 class='card-title'>{$product['fld_product_name']}</h6>
                    <p class='text-muted'>RM {$product['fld_product_price']}</p>
                    <a href='product_details.php?name=".urlencode($product['fld_product_name'])."' class='btn btn-primary w-100'>View Details</a>
                </div>
            </div>
        </div>";
    }
} else {
    echo "<p class='text-muted'>No products found matching <strong>$query</strong>.</p>";
}
?>
</div>
</div>

</body>
</html>
