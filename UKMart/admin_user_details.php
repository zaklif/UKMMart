<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get user ID
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    header("Location: admin_users.php");
    exit;
}

// Fetch user details
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_user_ukmart WHERE fld_user_id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: admin_users.php?error=user_not_found");
        exit;
    }
} catch (PDOException $e) {
    header("Location: admin_users.php?error=database_error");
    exit;
}

// Get seller information if user is a seller
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_sellers_ukmart WHERE fld_user_id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $seller = null;
}

// Get products if seller
$products = [];
if ($seller) {
    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_seller_id = :seller_id ORDER BY fld_product_id DESC LIMIT 10");
        $stmt->bindParam(':seller_id', $seller['fld_seller_id']);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $products = [];
    }
}

// Get reviews as buyer
try {
    $stmt = $conn->prepare("SELECT r.*, p.fld_product_name, s.fld_seller_name 
                           FROM tbl_ratings_reviews r
                           LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                           LEFT JOIN tbl_sellers_ukmart s ON r.seller_id = s.fld_seller_id
                           WHERE r.buyer_id = :buyer_id 
                           ORDER BY r.created_at DESC LIMIT 5");
    $stmt->bindParam(':buyer_id', $user_id);
    $stmt->execute();
    $buyer_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $buyer_reviews = [];
}

// Get reviews received as seller
$seller_reviews = [];
if ($seller) {
    try {
        $stmt = $conn->prepare("SELECT r.*, u.fld_user_name as buyer_name, p.fld_product_name
                               FROM tbl_ratings_reviews r
                               LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                               LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                               WHERE r.seller_id = :seller_id 
                               ORDER BY r.created_at DESC LIMIT 5");
        $stmt->bindParam(':seller_id', $seller['fld_seller_id']);
        $stmt->execute();
        $seller_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $seller_reviews = [];
    }
}

// Get chat activity
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_chat_ukmart WHERE buyer_id = :id OR seller_id = :seller_id");
    $stmt->bindParam(':id', $user_id);
    // Store the value in a variable first
    $seller_id_value = $seller ? $seller['fld_seller_id'] : 0;
    $stmt->bindParam(':seller_id', $seller_id_value);
    $stmt->execute();
    $chat_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $chat_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - UKMart Admin</title>
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

        .user-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .user-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
        }

        .user-info h2 {
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

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
            padding: 10px 20px;
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

        .btn-suspend {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-ban {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-activate {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .product-card {
            background: var(--bg-light);
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .product-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .review-item {
            padding: 15px;
            background: var(--bg-light);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .rating-stars {
            color: #f59e0b;
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

        .user-profile-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--border-color);
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
            <h1><i class="fas fa-user"></i> User Details</h1>
            <a href="admin_users.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- User Profile Card -->
        <div class="detail-card">
        <div class="user-header">
    <?php if (!empty($seller['fld_profile_pic'])): ?>
        <img src="profile/<?php echo htmlspecialchars($seller['fld_profile_pic']); ?>" 
             alt="Profile" 
             class="user-profile-large">
    <?php else: ?>
        <div class="user-avatar-large">
            <?php echo strtoupper(substr($user['fld_user_name'], 0, 1)); ?>
        </div>
    <?php endif; ?>

    <div class="user-info">
        <h2><?php echo htmlspecialchars($user['fld_user_name']); ?></h2>
        <div>
            <span class="badge badge-info">Buyer</span>

            <?php if ($seller): ?>
                <span class="badge badge-warning">Seller</span>
            <?php endif; ?>

            <?php
            $status = $user['fld_user_status'];
            $badge_class = '';
            switch($status) {
                case 'active':
                    $badge_class = 'badge-success';
                    break;
                case 'suspended':
                    $badge_class = 'badge-warning';
                    break;
                case 'banned':
                    $badge_class = 'badge-danger';
                    break;
            }
            ?>
            <span class="badge <?php echo $badge_class; ?>">
                <?php echo ucfirst($status); ?>
            </span>
        </div>
    </div>
</div>


            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>User ID</label>
                    <p>#<?php echo $user['fld_user_id']; ?></p>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($user['fld_user_email']); ?></p>
                </div>
                <div class="info-item">
    <label>Phone</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($user['fld_user_phone']) ? $user['fld_user_phone'] : 'N/A'
        ); 
        ?>
    </p>
</div>

<div class="info-item">
    <label>Address</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($user['fld_user_address']) ? $user['fld_user_address'] : 'N/A'
        ); 
        ?>
    </p>
</div>

                <div class="info-item">
                    <label>Member Since</label>
                    <p><?php echo isset($user['fld_user_created_at']) ? date('M d, Y', strtotime($user['fld_user_created_at'])) : 'N/A'; ?></p>
                </div>
                <div class="info-item">
                    <label>Total Chats</label>
                    <p><?php echo number_format($chat_count); ?> messages</p>
                </div>
            </div>

            <div class="action-buttons">
                <?php if ($status === 'active'): ?>
                    <a href="admin_update_user_status.php?id=<?php echo $user_id; ?>&status=suspended" 
                       class="btn-action btn-suspend"
                       onclick="return confirm('Suspend this user?')">
                        <i class="fas fa-pause"></i> Suspend User
                    </a>
                <?php elseif ($status === 'suspended'): ?>
                    <a href="admin_update_user_status.php?id=<?php echo $user_id; ?>&status=active" 
                       class="btn-action btn-activate"
                       onclick="return confirm('Activate this user?')">
                        <i class="fas fa-check"></i> Activate User
                    </a>
                    <a href="admin_update_user_status.php?id=<?php echo $user_id; ?>&status=banned" 
                       class="btn-action btn-ban"
                       onclick="return confirm('Ban this user permanently?')">
                        <i class="fas fa-ban"></i> Ban User
                    </a>
                <?php elseif ($status === 'banned'): ?>
                    <a href="admin_update_user_status.php?id=<?php echo $user_id; ?>&status=active" 
                       class="btn-action btn-activate"
                       onclick="return confirm('Unban this user?')">
                        <i class="fas fa-undo"></i> Unban User
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Seller Information -->
        <?php if ($seller): ?>
        <div class="detail-card">
            <h3><i class="fas fa-store"></i> Seller Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Seller ID</label>
                    <p>#<?php echo $seller['fld_seller_id']; ?></p>
                </div>
                <div class="info-item">
                    <label>Shop Name</label>
                    <p><?php echo htmlspecialchars($seller['fld_seller_name']); ?></p>
                </div>
                <div class="info-item">
                    <label>Seller Rating</label>
                    <p class="rating-stars"><?php echo number_format($seller['fld_seller_rating'], 1); ?> ★</p>
                </div>
                <div class="info-item">
                    <label>Total Products</label>
                    <p><?php echo count($products); ?> products</p>
                </div>
            </div>
            <?php if (!empty($seller['fld_seller_description'])): ?>
                <div class="info-item" style="margin-top: 15px;">
                    <label>Description</label>
                    <p><?php echo htmlspecialchars($seller['fld_seller_description']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Products -->
        <?php if (!empty($products)): ?>
        <div class="detail-card">
            <h3><i class="fas fa-box"></i> Recent Products (<?php echo count($products); ?>)</h3>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if (!empty($product['fld_product_image'])): ?>
                            <img src="images/<?php echo htmlspecialchars($product['fld_product_image']); ?>" alt="Product" class="product-img">
                        <?php else: ?>
                            <div class="product-img" style="background: var(--bg-light); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2rem; color: var(--text-gray);"></i>
                            </div>
                        <?php endif; ?>
                        <strong><?php echo htmlspecialchars($product['fld_product_name']); ?></strong>
                        <p style="margin: 5px 0; color: var(--primary-purple); font-weight: 600;">
                            RM <?php echo number_format($product['fld_product_price'], 2); ?>
                        </p>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-gray);">
                            Qty: <?php echo $product['fld_product_quantity']; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reviews Received as Seller -->
        <?php if (!empty($seller_reviews)): ?>
        <div class="detail-card">
            <h3><i class="fas fa-star"></i> Reviews Received (<?php echo count($seller_reviews); ?>)</h3>
            <?php foreach ($seller_reviews as $review): ?>
                <div class="review-item">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                        <strong>
    <?php 
    echo htmlspecialchars(
        isset($review['buyer_name']) ? $review['buyer_name'] : 'Unknown'
    ); 
    ?>
</strong>
<br>
<small style="color: var(--text-gray);">
    <?php 
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
                    <p style="margin: 0;"><?php echo htmlspecialchars($review['review']); ?></p>
                    <small style="color: var(--text-gray);">
                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Reviews Given as Buyer -->
        <?php if (!empty($buyer_reviews)): ?>
        <div class="detail-card">
            <h3><i class="fas fa-comment"></i> Reviews Given (<?php echo count($buyer_reviews); ?>)</h3>
            <?php foreach ($buyer_reviews as $review): ?>
                <div class="review-item">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                        <strong>
    <?php 
    echo htmlspecialchars(
        isset($review['fld_product_name']) ? $review['fld_product_name'] : 'N/A'
    ); 
    ?>
</strong>
<br>
<small style="color: var(--text-gray);">
    Seller: <?php 
    echo htmlspecialchars(
        isset($review['fld_seller_name']) ? $review['fld_seller_name'] : 'N/A'
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
                    <p style="margin: 0;"><?php echo htmlspecialchars($review['review']); ?></p>
                    <small style="color: var(--text-gray);">
                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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