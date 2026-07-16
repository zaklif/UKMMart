<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id === 0) {
    header("Location: admin_products.php");
    exit;
}

// Fetch product details with seller info
try {
    $stmt = $conn->prepare("SELECT p.*, s.fld_seller_name, s.fld_seller_id, u.fld_user_name, u.fld_user_email, u.fld_user_id
                           FROM tbl_products_ukmart p
                           LEFT JOIN tbl_sellers_ukmart s ON p.fld_seller_id = s.fld_seller_id
                           LEFT JOIN tbl_user_ukmart u ON s.fld_user_id = u.fld_user_id
                           WHERE p.fld_product_id = :id");
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: admin_products.php?error=product_not_found");
        exit;
    }
} catch (PDOException $e) {
    header("Location: admin_products.php?error=database_error");
    exit;
}

// Get reviews for this product
try {
    $stmt = $conn->prepare("SELECT r.*, u.fld_user_name as buyer_name
                           FROM tbl_ratings_reviews r
                           LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                           WHERE r.product_id = :id
                           ORDER BY r.created_at DESC");
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate average rating
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $avg_rating = count($reviews) > 0 ? $total_rating / count($reviews) : 0;
} catch (PDOException $e) {
    $reviews = [];
    $avg_rating = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - UKMart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-purple: #667eea;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 0 30px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .sidebar-brand h2 {
            color: white;
            font-weight: 800;
            font-size: 1.8rem;
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 30px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .sidebar-menu a i {
            width: 25px;
            margin-right: 15px;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .detail-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .detail-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-header {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .product-image-main {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }

        .product-image-placeholder {
            width: 100%;
            height: 350px;
            background: var(--bg-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border-color);
        }

        .product-info h2 {
            margin: 0 0 15px;
            font-size: 2rem;
        }

        .price-tag {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-purple);
            margin-bottom: 15px;
        }

        .badge {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-right: 8px;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            padding: 15px;
            background: var(--bg-light);
            border-radius: 10px;
        }

        .info-item label {
            font-weight: 600;
            color: var(--text-gray);
            font-size: 0.85rem;
            display: block;
            margin-bottom: 5px;
        }

        .info-item p {
            margin: 0;
            font-size: 1rem;
            color: var(--text-dark);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-action {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .review-item {
            padding: 20px;
            background: var(--bg-light);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .rating-stars {
            color: #f59e0b;
            font-size: 1.1rem;
        }

        .seller-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--bg-light);
            border-radius: 10px;
        }

        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
        }

        /* Hamburger Menu Button */
        .menu-toggle {
            display: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1001;
            position: fixed;
            top: 20px;
            left: 20px;
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
                padding-top: 80px;
            }

            .top-bar {
                padding-top: 60px;
            }

            .admin-profile {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }

            .product-header {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu Button -->
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2>UKMart Admin</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="admin_sellers.php"><i class="fas fa-store"></i> Active Sellers</a></li>
            <li><a href="admin_products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="admin_reviews.php"><i class="fas fa-star"></i> Manage Reviews</a></li>
            <li><a href="admin_chats.php"><i class="fas fa-comments"></i> Chat Monitoring</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-box"></i> Product Details</h1>
            <a href="admin_products.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <!-- Product Details Card -->
        <div class="detail-card">
            <div class="product-header">
                <div>
                    <?php if (!empty($product['fld_product_image'])): ?>
                        <img src="images/<?php echo htmlspecialchars($product['fld_product_image']); ?>" 
     alt="Product" 
     class="product-image-main">

                    <?php else: ?>
                        <div class="product-image-placeholder">
                            <i class="fas fa-image" style="font-size: 4rem; color: var(--text-gray);"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-info">
                    <h2><?php echo htmlspecialchars($product['fld_product_name']); ?></h2>
                    
                    <div class="price-tag">
                        RM <?php echo number_format($product['fld_product_price'], 2); ?>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <span class="badge badge-info"><?php echo htmlspecialchars($product['fld_product_category']); ?></span>
                        
                        <?php if ($product['is_available'] == 1 && $product['fld_product_quantity'] > 0): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Available
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-times"></i> Sold Out
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['fld_product_condition'])): ?>
                            <span class="badge badge-warning"><?php echo htmlspecialchars($product['fld_product_condition']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; color: var(--text-gray); display: block; margin-bottom: 8px;">Description</label>
                        <p style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($product['fld_product_description'])); ?></p>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <label>Product ID</label>
                            <p>#<?php echo $product['fld_product_id']; ?></p>
                        </div>
                        <div class="info-item">
                            <label>Quantity</label>
                            <p style="<?php echo $product['fld_product_quantity'] == 0 ? 'color: var(--danger);' : ''; ?>">
                                <?php echo number_format($product['fld_product_quantity']); ?> units
                            </p>
                        </div>
                        <?php if (!empty($product['fld_product_brand'])): ?>
                        <div class="info-item">
                            <label>Brand</label>
                            <p><?php echo htmlspecialchars($product['fld_product_brand']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['fld_product_model'])): ?>
                        <div class="info-item">
                            <label>Model</label>
                            <p><?php echo htmlspecialchars($product['fld_product_model']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['fld_product_location'])): ?>
                        <div class="info-item">
                            <label>Location</label>
                            <p><?php echo htmlspecialchars($product['fld_product_location']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['fld_deal_method'])): ?>
                        <div class="info-item">
                            <label>Deal Method</label>
                            <p><?php echo htmlspecialchars($product['fld_deal_method']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['fld_contact_phone'])): ?>
                        <div class="info-item">
                            <label>Contact Phone</label>
                            <p><?php echo htmlspecialchars($product['fld_contact_phone']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button class="btn-action btn-delete" onclick="deleteProduct(<?php echo $product_id; ?>, '<?php echo htmlspecialchars(addslashes($product['fld_product_name'])); ?>')">
                    <i class="fas fa-trash"></i> Delete Product
                </button>
            </div>
        </div>

        <!-- Seller Information -->
        <div class="detail-card">
            <h3><i class="fas fa-store"></i> Seller Information</h3>
            <div class="seller-info">
            <div class="seller-avatar">
    <?php 
    echo strtoupper(
        isset($product['fld_seller_name']) ? substr($product['fld_seller_name'], 0, 1) : 'U'
    ); 
    ?>
</div>
<div>
    <h4 style="margin: 0 0 5px;">
        <?php 
        echo htmlspecialchars(
            isset($product['fld_seller_name']) ? $product['fld_seller_name'] : 'Unknown'
        ); 
        ?>
    </h4>
    <p style="margin: 0; color: var(--text-gray);">
        User: <?php 
        echo htmlspecialchars(
            isset($product['fld_user_name']) ? $product['fld_user_name'] : 'N/A'
        ); 
        ?>
    </p>
    <p style="margin: 0; color: var(--text-gray);">
        Email: <?php 
        echo htmlspecialchars(
            isset($product['fld_user_email']) ? $product['fld_user_email'] : 'N/A'
        ); 
        ?>
    </p>
</div>

                    <?php if (!empty($product['fld_user_id'])): ?>
                    <a href="admin_user_details.php?id=<?php echo $product['fld_user_id']; ?>" style="color: var(--primary-purple); text-decoration: none; font-weight: 600;">
                        <i class="fas fa-arrow-right"></i> View Seller Profile
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        

        <!-- Reviews -->
        <?php if (!empty($reviews)): ?>
        <div class="detail-card">
            <h3>
                <i class="fas fa-star"></i> Customer Reviews (<?php echo count($reviews); ?>)
                <span style="margin-left: 15px; color: var(--warning);">
                    <?php echo number_format($avg_rating, 1); ?> ★ Average
                </span>
            </h3>
            
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                        <strong>
    <?php 
    echo htmlspecialchars(
        isset($review['buyer_name']) ? $review['buyer_name'] : 'Anonymous'
    ); 
    ?>
</strong>

                            <br>
                            <small style="color: var(--text-gray);">
                                <?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?>
                            </small>
                        </div>
                        <div>
                            <span class="rating-stars">
                                <?php for($i = 0; $i < 5; $i++): ?>
                                    <?php if($i < $review['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </span>
                        </div>
                    </div>
                    <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="detail-card">
            <h3><i class="fas fa-star"></i> Customer Reviews</h3>
            <p style="color: var(--text-gray); text-align: center; padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                No reviews yet for this product
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function deleteProduct(productId, productName) {
            if (confirm('Are you sure you want to delete "' + productName + '"?\n\nThis action cannot be undone and will also delete all reviews for this product!')) {
                window.location.href = 'admin_delete_product.php?id=' + productId;
            }
        }
    </script>

    <script>
        // Hamburger menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            });

            function setupMobileMenuClose() {
                if (window.innerWidth <= 768) {
                    const menuItems = document.querySelectorAll('.sidebar-menu a');
                    menuItems.forEach(item => {
                        const newItem = item.cloneNode(true);
                        item.parentNode.replaceChild(newItem, item);
                        newItem.addEventListener('click', function() {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                            const icon = menuToggle.querySelector('i');
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        });
                    });
                }
            }

            setupMobileMenuClose();
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    setupMobileMenuClose();
                }
            });
        }
    </script>
</body>
</html>