<?php
session_start();
include('database.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($review_id === 0) {
    header("Location: admin_reviews.php");
    exit;
}

// Fetch review with related data
try {
    $stmt = $conn->prepare("SELECT r.*, 
                           u.fld_user_name as buyer_name, u.fld_user_email as buyer_email,
                           p.fld_product_name, p.fld_product_price, p.fld_product_image,
                           s.fld_seller_name, s.fld_seller_id
                           FROM tbl_ratings_reviews r
                           LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                           LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                           LEFT JOIN tbl_sellers_ukmart s ON r.seller_id = s.fld_seller_id
                           WHERE r.id = :id");
    $stmt->bindParam(':id', $review_id);
    $stmt->execute();
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        header("Location: admin_reviews.php?error=review_not_found");
        exit;
    }
} catch (PDOException $e) {
    header("Location: admin_reviews.php?error=database_error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Details - UKMart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 0; box-shadow: 4px 0 20px rgba(0,0,0,0.1); z-index: 1000; overflow-y: auto; }
        .sidebar-brand { padding: 0 30px 30px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 30px; }
        .sidebar-brand h2 { color: white; font-weight: 800; font-size: 1.8rem; margin: 0; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 30px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s ease; font-weight: 500; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a i { width: 25px; margin-right: 15px; }
        .main-content { margin-left: 280px; padding: 30px; min-height: 100vh; }
        .top-bar { background: white; padding: 20px 30px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .back-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); color: white; }
        .detail-card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .detail-card h3 { font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .rating-display { font-size: 4rem; color: #f59e0b; text-align: center; margin: 20px 0; }
        .rating-stars { color: #f59e0b; font-size: 2rem; }
        .review-content { background: #f8fafc; padding: 25px; border-radius: 12px; line-height: 1.8; font-size: 1.1rem; margin: 20px 0; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .info-item { padding: 15px; background: #f8fafc; border-radius: 10px; }
        .info-item label { font-weight: 600; color: #64748b; font-size: 0.85rem; display: block; margin-bottom: 5px; }
        .info-item p { margin: 0; font-size: 1rem; color: #1e293b; }
        .product-preview { display: flex; gap: 20px; align-items: center; padding: 20px; background: #f8fafc; border-radius: 12px; margin: 20px 0; }
        .product-img { width: 120px; height: 120px; object-fit: cover; border-radius: 10px; }
        .btn-delete { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 12px 24px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239,68,68,0.4); }
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

    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand"><h2>UKMart Admin</h2></div>
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

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-star"></i> Review Details</h1>
            <a href="admin_reviews.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Reviews</a>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-star"></i> Rating & Review</h3>
            <div class="rating-display">
                <?php echo $review['rating']; ?>/5
                <div class="rating-stars">
                    <?php for($i = 0; $i < 5; $i++): ?>
                        <?php if($i < $review['rating']): ?>
                            <i class="fas fa-star"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="review-content">
                <?php echo nl2br(htmlspecialchars($review['review'])); ?>
            </div>
            <small style="color: #64748b; display: block; text-align: center;">
                Posted on <?php echo date('F d, Y \a\t h:i A', strtotime($review['created_at'])); ?>
            </small>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Review Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Review ID</label>
                    <p>#<?php echo $review['id']; ?></p>
                </div>
                <div class="info-item">
    <label>Buyer Name</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($review['buyer_name']) ? $review['buyer_name'] : 'Unknown'
        ); 
        ?>
    </p>
</div>

<div class="info-item">
    <label>Buyer Email</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($review['buyer_email']) ? $review['buyer_email'] : 'N/A'
        ); 
        ?>
    </p>
</div>

<div class="info-item">
    <label>Seller Name</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($review['fld_seller_name']) ? $review['fld_seller_name'] : 'N/A'
        ); 
        ?>
    </p>
</div>

            </div>
        </div>

        <?php if (!empty($review['fld_product_name'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-box"></i> Product Reviewed</h3>
            <div class="product-preview">
                <?php if (!empty($review['fld_product_image'])): ?>
                    <img src="<?php echo htmlspecialchars($review['fld_product_image']); ?>" alt="Product" class="product-img">
                <?php else: ?>
                    <div class="product-img" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-image" style="font-size: 2rem; color: #64748b;"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h4 style="margin: 0 0 10px;"><?php echo htmlspecialchars($review['fld_product_name']); ?></h4>
                    <p style="margin: 0; color: #667eea; font-weight: 700; font-size: 1.2rem;">
                        RM <?php echo number_format($review['fld_product_price'], 2); ?>
                    </p>
                    <a href="admin_product_detail.php?id=<?php echo $review['product_id']; ?>" style="color: #667eea; text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">
                        <i class="fas fa-arrow-right"></i> View Product Details
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="detail-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
            <p style="color: #64748b; margin-bottom: 15px;">Deleting this review is permanent and cannot be undone.</p>
            <button class="btn-delete" onclick="if(confirm('Are you sure you want to delete this review permanently?')) window.location='admin_delete_review.php?id=<?php echo $review_id; ?>'">
                <i class="fas fa-trash"></i> Delete Review
            </button>
        </div>
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