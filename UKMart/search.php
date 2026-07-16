<?php
session_start();
include('db_connect.php');

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Retrieve the user type (seller or buyer) from the session
$user_type = $_SESSION['user_type']; // Assume 'user_type' is set during login

// Get the user name based on their type (buyer or seller)
if ($user_type == 'seller') {
    // Get seller name
    $stmt = $conn->prepare("SELECT fld_seller_name FROM tbl_sellers_ukmart WHERE fld_seller_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row['fld_seller_name'];
} else {
    // Get buyer name
    $stmt = $conn->prepare("SELECT fld_user_name FROM tbl_user_ukmart WHERE fld_user_id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $row['fld_user_name'];
}

// Ambil nilai GET (jika ada)
$q          = isset($_GET['q']) ? trim($_GET['q']) : '';
$category   = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_price  = isset($_GET['min_price']) ? trim($_GET['min_price']) : '';
$max_price  = isset($_GET['max_price']) ? trim($_GET['max_price']) : '';
$sort       = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

// Ambil senarai kategori untuk dropdown filter
$catStmt = $conn->query("SELECT DISTINCT fld_product_category FROM tbl_products_ukmart ORDER BY fld_product_category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Bina query search + filter
$sql = "SELECT * FROM tbl_products_ukmart WHERE 1=1";
$params = [];

// Search text (jika ada)
if ($q !== '') {
    $sql .= " AND (fld_product_name LIKE :search OR fld_product_category LIKE :search)";
    $params[':search'] = "%" . $q . "%";
}

// Filter kategori (jika dipilih)
if ($category !== '') {
    $sql .= " AND fld_product_category = :category";
    $params[':category'] = $category;
}

// Filter harga minimum
if ($min_price !== '' && is_numeric($min_price)) {
    $sql .= " AND fld_product_price >= :min_price";
    $params[':min_price'] = $min_price;
}

// Filter harga maksimum
if ($max_price !== '' && is_numeric($max_price)) {
    $sql .= " AND fld_product_price <= :max_price";
    $params[':max_price'] = $max_price;
}

// Sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY fld_product_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY fld_product_price DESC";
        break;
    case 'latest':
        $sql .= " ORDER BY fld_product_id DESC"; // tukar ikut column tarikh kalau ada
        break;
    default:
        // relevance -> tiada ORDER BY khas, biar natural
        break;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if this is an AJAX request for live search
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// If this is an AJAX request, return JSON and exit
if ($is_ajax) {
    header('Content-Type: application/json');
    $output = [];
    foreach($results as $product) {
        // FIX: Use absolute path from web root
        $img = (!empty($product['fld_product_image'])) 
            ? "images/" . $product['fld_product_image'] 
            : "images/default.png";
        
        $output[] = [
            'name' => $product['fld_product_name'],
            'price' => number_format($product['fld_product_price'], 2),
            'category' => $product['fld_product_category'],
            'image' => $img,
            'link' => "product_details.php?name=" . urlencode($product['fld_product_name'])
        ];
    }
    echo json_encode($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Search Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
:root {
  --primary-blue: #2563eb;
  --primary-blue-dark: #1e40af;
  --primary-blue-light: #3b82f6;
  --accent-orange: #f97316;
  --accent-green: #10b981;
  --bg-light: #f8fafc;
  --bg-white: #ffffff;
  --text-dark: #1e293b;
  --text-gray: #64748b;
  --border-color: #e2e8f0;
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
  --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.2);
  --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.25);
}

body { 
    font-family: 'Poppins', sans-serif; 
    background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%); 
    padding:20px; 
    color: var(--text-dark);
}
.page-wrapper {
    width: 100%;
    margin: 0 auto;
}
.page-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom:20px;
}
.page-header h3 {
    margin:0;
    font-weight:700;
}
.page-header small {
    color:#6c757d;
}

/* Search & filter bar */
.search-filter-bar {
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    border-radius:18px;
    box-shadow: var(--shadow-lg);
    border: 2px solid var(--primary-blue-light);
    padding:25px;
    margin-bottom:30px;
    width: 100%;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    position: relative;
    overflow: hidden;
}
.search-filter-bar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.search-filter-bar form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    width: 100%;
}
.search-filter-bar input[type="text"],
.search-filter-bar input[type="number"],
.search-filter-bar select {
    font-size:14px;
}
.search-input {
    flex: 1 1 220px;
}
.filter-select {
    flex: 0 0 160px;
}
.price-input {
    width: 120px;
}
.btn-search {
    padding: 8px 18px;
    font-size:14px;
    font-weight:600;
}
.btn-reset {
    padding: 8px 18px;
    font-size:14px;
    font-weight:600;
    background: #6c757d;
    border: none;
    color: white;
}
.btn-reset:hover {
    background: #5a6268;
    color: white;
}

/* Grid produk */
.product-grid { 
    display:grid; 
    grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); 
    gap:20px; 
}
.product-card { 
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); 
    border-radius:16px; 
    box-shadow: var(--shadow-md);
    border: 2px solid var(--border-color);
    overflow:hidden; 
    transition:all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
    display:flex;
    flex-direction:column;
    position: relative;
}
.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.4s ease;
}
.product-card:hover::before {
    transform: scaleX(1);
}
.product-card:hover { 
    transform: translateY(-6px) scale(1.02); 
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
    border-color: var(--primary-blue);
}
.product-card img { 
    width:100%; 
    height:150px; 
    object-fit:cover; 
}
.card-body { 
    padding:10px; 
    flex:1;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}
.card-body h6 { 
    font-size:14px; 
    margin-bottom:5px; 
    font-weight:600;
}
.card-body p { 
    font-size:14px; 
    color: var(--primary-blue); 
    font-weight:600; 
    margin-bottom:5px; 
}
.card-body small {
    font-size:11px;
    color: var(--text-gray);
}
.card-body a { 
    font-size:14px; 
    text-decoration:none; 
    text-align:center; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    color:#fff; 
    padding:10px 0; 
    border-radius:10px; 
    transition:all 0.3s ease; 
    margin-top:8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.card-body a:hover { 
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* No result text */
.no-result {
    padding:30px 10px;
    text-align:center;
    color: var(--text-gray);
}

.back-btn {
    font-size: 24px;
    color: var(--primary-blue);
    margin-right: 10px;
    text-decoration: none;
    transition: all 0.3s ease;
}
.back-btn:hover { 
    color: var(--primary-blue-dark);
    transform: scale(1.1);
}
.category-title-container {
    display:flex;
    align-items:center;
    margin-bottom:15px;
}


/* Responsive */
@media(max-width: 576px) {
    .search-filter-bar form {
        flex-direction:column;
        align-items:stretch;
    }
    .filter-select,
    .price-input {
        width:100%;
    }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light mb-3" style="background-color: #ffffff; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); position: sticky; top: 0; z-index: 1000;">
  <div class="container-fluid">
    <a class="navbar-brand" href="buyer.php" style="font-weight: 600; color: #2563eb; letter-spacing: -0.3px; font-size: 1.5rem;">UKMart</a>
    <div class="d-flex align-items-center ms-3 position-relative">
      <div class="dropdown">
        <button class="btn dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent; border: none; color: #374151; font-weight: 400; font-size: 0.95rem; padding: 6px 12px; border-radius: 6px;">
          Hello, <?= htmlspecialchars($user_name); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="buyer_profile.php">Profile</a></li>
          <li><a class="dropdown-item" href="logout.php">Logout</a></li>
        </ul>
      </div>
      <a href="chat.php" class="btn ms-2" style="background: transparent; border: none; color: #374151; font-size: 1.1rem; padding: 6px 10px; border-radius: 6px;"><i class="bi bi-chat-dots"></i></a>
    </div>
  </div>
</nav>

<div class="page-wrapper">

<div class="container">
    <div class="category-title-container">
        <a href="buyer.php" class="back-btn">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </div>
</div>

    <div class="page-header">
        <h3>Search Results</h3>
        <small>
            <?php if ($q !== ''): ?>
                Showing results for: <strong><?= htmlspecialchars($q) ?></strong>
            <?php else: ?>
                Showing all products
            <?php endif; ?>
        </small>
    </div>

    <!-- Search + Filter Bar -->
    <div class="search-filter-bar">
        <form method="get" action="search.php" id="searchForm">
            <!-- Search text -->
            <input 
                type="text" 
                name="q" 
                class="form-control search-input" 
                placeholder="Search product name or category..."
                value="<?= htmlspecialchars($q) ?>"
            >

            <!-- Category filter -->
            <select name="category" class="form-select filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" 
                        <?= ($category === $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Min price -->
            <input 
                type="number" 
                step="0.01" 
                name="min_price" 
                class="form-control price-input" 
                placeholder="Min RM"
                value="<?= htmlspecialchars($min_price) ?>"
            >

            <!-- Max price -->
            <input 
                type="number" 
                step="0.01" 
                name="max_price" 
                class="form-control price-input" 
                placeholder="Max RM"
                value="<?= htmlspecialchars($max_price) ?>"
            >

            <!-- Sort -->
            <select name="sort" class="form-select filter-select">
                <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Sort: Relevance</option>
                <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Newest</option>
            </select>

            <!-- Search button -->
            <button type="submit" class="btn btn-primary btn-search">
                <i class="bi bi-search"></i> Search
            </button>
            
            <!-- Reset button -->
            <button type="button" class="btn btn-reset" onclick="resetSearch()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </button>
        </form>
    </div>

    <?php if($results): ?>
        <div class="product-grid">
        <?php foreach($results as $product): 
            // FIX: Use relative path that works on server
            $img = (!empty($product['fld_product_image'])) 
                ? "images/" . $product['fld_product_image'] 
                : "images/default.png";
        ?>
            <div class="product-card">
                <img src="<?= htmlspecialchars($img) ?>" 
                     alt="<?= htmlspecialchars($product['fld_product_name']) ?>"
                     onerror="this.src='images/default.png'">
                <div class="card-body">
                    <div>
                        <h6><?= htmlspecialchars($product['fld_product_name']) ?></h6>
                        <p>RM <?= number_format($product['fld_product_price'],2) ?></p>
                        <small><?= htmlspecialchars($product['fld_product_category']) ?></small>
                    </div>
                    <a href="product_details.php?name=<?= urlencode($product['fld_product_name']) ?>">View</a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-result">
            No products found. Try different keywords or filters.
        </div>
    <?php endif; ?>

</div>

<script>
// Reset function
function resetSearch() {
    window.location.href = 'search.php';
}

// Live search functionality
(function() {
    const searchInput = document.querySelector('input[name="q"]');
    const categorySelect = document.querySelector('select[name="category"]');
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');
    const sortSelect = document.querySelector('select[name="sort"]');
    const pageWrapper = document.querySelector('.page-wrapper');
    const pageHeaderSmall = document.querySelector('.page-header small');
    
    let debounceTimer;
    
    function performLiveSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            const params = new URLSearchParams();
            params.append('ajax', '1');
            if (searchInput.value.trim()) params.append('q', searchInput.value.trim());
            if (categorySelect.value) params.append('category', categorySelect.value);
            if (minPriceInput.value) params.append('min_price', minPriceInput.value);
            if (maxPriceInput.value) params.append('max_price', maxPriceInput.value);
            if (sortSelect.value) params.append('sort', sortSelect.value);
            
            fetch('search.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    updateResults(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }, 300);
    }
    
    function updateResults(products) {
        // Update the "Showing results for" text
        const searchTerm = searchInput.value.trim();
        if (pageHeaderSmall) {
            pageHeaderSmall.innerHTML = searchTerm
                ? 'Showing results for: <strong>' + escapeHtml(searchTerm) + '</strong>'
                : 'Showing all products';
        }

        if (!pageWrapper) return;

        // Remove existing product grid or no-result div
        const existingGrid = document.querySelector('.product-grid');
        const existingNoResult = document.querySelector('.no-result');
        if (existingGrid) existingGrid.remove();
        if (existingNoResult) existingNoResult.remove();

        if (!products || products.length === 0) {
            // Show no results message
            const noResult = document.createElement('div');
            noResult.className = 'no-result';
            noResult.textContent = 'No products found. Try different keywords or filters.';
            pageWrapper.appendChild(noResult);
            return;
        }

        // Create product grid
        const grid = document.createElement('div');
        grid.className = 'product-grid';

        products.forEach(product => {
            // Use the path as-is from the server response
            let imgSrc = product.image && product.image.trim() !== ''
                ? product.image
                : 'images/default.png';

            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <img src="${escapeHtml(imgSrc)}" 
                     alt="${escapeHtml(product.name)}" 
                     onerror="this.src='images/default.png'">
                <div class="card-body">
                    <div>
                        <h6>${escapeHtml(product.name)}</h6>
                        <p>RM ${escapeHtml(product.price)}</p>
                        <small>${escapeHtml(product.category)}</small>
                    </div>
                    <a href="product_details.php?name=${encodeURIComponent(product.name)}">View</a>
                </div>
            `;
            grid.appendChild(card);
        });

        pageWrapper.appendChild(grid);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Add event listeners for live search
    if (searchInput) {
        searchInput.addEventListener('input', performLiveSearch);
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', performLiveSearch);
    }
    if (minPriceInput) {
        minPriceInput.addEventListener('input', performLiveSearch);
    }
    if (maxPriceInput) {
        maxPriceInput.addEventListener('input', performLiveSearch);
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', performLiveSearch);
    }
    
    // Allow form submission (don't prevent it)
    // This way clicking Search will do a full page reload with proper images
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // Let the form submit naturally - don't prevent default
            // This ensures proper page reload with correct image paths
        });
    }
})();
</script>
</body>
</html>