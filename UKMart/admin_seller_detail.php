<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get seller ID
$seller_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($seller_id === 0) {
    header("Location: admin_sellers.php");
    exit;
}

// Fetch seller details with user info
try {
    $stmt = $conn->prepare("SELECT s.*, u.fld_user_name, u.fld_user_email, u.fld_user_id, u.fld_user_status
                           FROM tbl_sellers_ukmart s
                           LEFT JOIN tbl_user_ukmart u ON s.fld_user_id = u.fld_user_id
                           WHERE s.fld_seller_id = :id");
    $stmt->bindParam(':id', $seller_id);
    $stmt->execute();
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seller) {
        header("Location: admin_sellers.php?error=seller_not_found");
        exit;
    }
} catch (PDOException $e) {
    header("Location: admin_sellers.php?error=database_error");
    exit;
}

// Get all products
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_seller_id = :id ORDER BY fld_product_id DESC");
    $stmt->bindParam(':id', $seller_id);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_products = count($products);
    $available_products = 0;
    $total_stock = 0;
    foreach ($products as $p) {
        if ($p['is_available'] == 1 && $p['fld_product_quantity'] > 0) {
            $available_products++;
        }
        $total_stock += $p['fld_product_quantity'];
    }
} catch (PDOException $e) {
    $products = [];
    $total_products = 0;
    $available_products = 0;
    $total_stock = 0;
}

// Get reviews
try {
    $stmt = $conn->prepare("SELECT r.*, u.fld_user_name as buyer_name, p.fld_product_name
                           FROM tbl_ratings_reviews r
                           LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                           LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                           WHERE r.seller_id = :id
                           ORDER BY r.created_at DESC");
    $stmt->bindParam(':id', $seller_id);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate rating distribution
    $rating_dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $total_rating = 0;
    foreach ($reviews as $r) {
        $rating_dist[$r['rating']]++;
        $total_rating += $r['rating'];
    }
    $avg_rating = count($reviews) > 0 ? $total_rating / count($reviews) : 0;
} catch (PDOException $e) {
    $reviews = [];
    $rating_dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $avg_rating = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Details - UKMart Admin</title>
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

        .seller-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .seller-profile-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--border-color);
        }

        .seller-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 3rem;
            border: 4px solid var(--border-color);
        }

        .seller-info h2 {
            margin: 0 0 10px;
            font-size: 2rem;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            padding: 20px;
            background: var(--bg-light);
            border-radius: 10px;
            text-align: center;
        }

        .stat-box h4 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 5px;
            color: var(--primary-purple);
        }

        .stat-box p {
            margin: 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .info-item {
            padding: 15px;
            background: var(--bg-light);
            border-radius: 10px;
            margin-bottom: 15px;
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

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .product-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .rating-stars {
            color: #f59e0b;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .rating-bar-fill {
            flex: 1;
            height: 8px;
            background: var(--bg-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar-inner {
            height: 100%;
            background: #f59e0b;
            transition: width 0.3s ease;
        }

        .review-item {
            padding: 20px;
            background: var(--bg-light);
            border-radius: 10px;
            margin-bottom: 15px;
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
            <h1><i class="fas fa-store"></i> Seller Details</h1>
            <a href="admin_sellers.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Sellers
            </a>
        </div>

        <!-- Seller Profile Card -->
        <div class="detail-card">
            <div class="seller-header">
                <?php if (!empty($seller['fld_profile_pic'])): ?>
                    <img src="profile/<?php echo htmlspecialchars($seller['fld_profile_pic']); ?>" alt="Profile" class="seller-profile-large">
                <?php else: ?>
                    <div class="seller-avatar-large">
                        <?php echo strtoupper(substr($seller['fld_seller_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>

                <div class="seller-info">
                    <h2><?php echo htmlspecialchars($seller['fld_seller_name']); ?></h2>
                    <div style="margin-bottom: 10px;">
                        <span class="rating-stars" style="font-size: 1.5rem;">
                            <?php echo number_format($seller['fld_seller_rating'], 1); ?> ★
                        </span>
                    </div>
                    <p style="margin: 0; color: var(--text-gray);">
                        User: <strong><?php echo htmlspecialchars($seller['fld_user_name']); ?></strong> 
                        (<?php echo htmlspecialchars($seller['fld_user_email']); ?>)
                    </p>
                    <span class="badge <?php echo $seller['fld_user_status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo ucfirst($seller['fld_user_status']); ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($seller['fld_seller_description'])): ?>
            <div class="info-item">
                <label>About</label>
                <p><?php echo nl2br(htmlspecialchars($seller['fld_seller_description'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-box">
                    <h4><?php echo number_format($total_products); ?></h4>
                    <p>Total Products</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo number_format($available_products); ?></h4>
                    <p>Available Products</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo number_format($total_stock); ?></h4>
                    <p>Total Stock</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo number_format(count($reviews)); ?></h4>
                    <p>Total Reviews</p>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <a href="admin_user_detail.php?id=<?php echo $seller['fld_user_id']; ?>" class="btn-action" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-user"></i> View User Profile
                </a>
                <button class="btn-action btn-delete" onclick="deleteSeller(<?php echo $seller_id; ?>, '<?php echo htmlspecialchars(addslashes($seller['fld_seller_name'])); ?>')">
                    <i class="fas fa-trash"></i> Delete Seller
                </button>
            </div>
        </div>

        <!-- Rating Breakdown -->
        <?php if (count($reviews) > 0): ?>
        <div class="detail-card">
            <h3><i class="fas fa-chart-bar"></i> Rating Breakdown</h3>
            <div style="max-width: 600px;">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <?php $percentage = count($reviews) > 0 ? ($rating_dist[$i] / count($reviews)) * 100 : 0; ?>
                    <div class="rating-bar">
                        <span style="width: 60px;"><?php echo $i; ?> ★</span>
                        <div class="rating-bar-fill">
                            <div class="rating-bar-inner" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        <span style="width: 60px; text-align: right; color: var(--text-gray);">
                            <?php echo $rating_dist[$i]; ?>
                        </span>
                    </div>
                <?php endfor; ?>
                <div style="text-align: center; margin-top: 20px; font-size: 1.2rem;">
                    <strong>Average: <?php echo number_format($avg_rating, 1); ?> ★</strong> 
                    <span style="color: var(--text-gray);">(<?php echo count($reviews); ?> reviews)</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products -->
        <div class="detail-card">
            <h3><i class="fas fa-box"></i> Products (<?php echo count($products); ?>)</h3>
            <?php if (!empty($products)): ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="window.location='admin_product_detail.php?id=<?php echo $product['fld_product_id']; ?>'">
                        <?php if (!empty($product['fld_product_image'])): ?>
                            <img src="images/<?php echo htmlspecialchars($product['fld_product_image']); ?>" alt="Product" class="product-img">
                        <?php else: ?>
                            <div class="product-img" style="background: white; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2rem; color: var(--text-gray);"></i>
                            </div>
                        <?php endif; ?>
                        <strong style="display: block; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($product['fld_product_name']); ?>
                        </strong>
                        <p style="margin: 0; color: var(--primary-purple); font-weight: 700; font-size: 1.1rem;">
                            RM <?php echo number_format($product['fld_product_price'], 2); ?>
                        </p>
                        <p style="margin: 5px 0 0; font-size: 0.85rem; color: var(--text-gray);">
                            Stock: <?php echo $product['fld_product_quantity']; ?>
                            <?php if ($product['is_available'] == 1 && $product['fld_product_quantity'] > 0): ?>
                                <span class="badge badge-success" style="margin-left: 5px; padding: 3px 8px; font-size: 0.7rem;">Available</span>
                            <?php else: ?>
                                <span class="badge badge-warning" style="margin-left: 5px; padding: 3px 8px; font-size: 0.7rem;">Sold Out</span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: var(--text-gray); text-align: center; padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 15px;"></i>
                No products listed yet
            </p>
            <?php endif; ?>
        </div>

        <!-- Recent Reviews -->
        <?php if (!empty($reviews)): ?>
        <div class="detail-card">
            <h3><i class="fas fa-star"></i> Recent Reviews (<?php echo min(10, count($reviews)); ?> of <?php echo count($reviews); ?>)</h3>
            <?php 
            $display_reviews = array_slice($reviews, 0, 10);
            foreach ($display_reviews as $review): 
            ?>
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
    Product: <?php 
    echo htmlspecialchars(
        isset($review['fld_product_name']) ? $review['fld_product_name'] : 'N/A'
    ); 
    ?>
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
                    <small style="color: var(--text-gray);">
                        <?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function deleteSeller(sellerId, sellerName) {
            if (confirm('Are you sure you want to delete seller "' + sellerName + '"?\n\nThis will also delete all their products and cannot be undone!')) {
                window.location.href = 'admin_delete_seller.php?id=' + sellerId;
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