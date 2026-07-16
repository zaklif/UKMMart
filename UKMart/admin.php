<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch statistics
try {
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_user_ukmart");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total sellers
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_sellers_ukmart");
    $total_sellers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total products
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_products_ukmart");
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Available products
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_products_ukmart WHERE is_available = 1");
    $available_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total reviews
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_ratings_reviews");
    $total_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Average platform rating
    $stmt = $conn->query("SELECT AVG(rating) as avg_rating FROM tbl_ratings_reviews");
    $avg_rating = $stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'];
    $avg_rating = $avg_rating ? number_format($avg_rating, 1) : '0.0';

    // Active chats (last 7 days)
    $stmt = $conn->query("SELECT COUNT(DISTINCT chat_id) as total FROM tbl_chat_ukmart 
                          WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $active_chats = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    $total_users = 0;
    $total_sellers = 0;
    $total_products = 0;
    $available_products = 0;
    $total_reviews = 0;
    $avg_rating = '0.0';
    $active_chats = 0;
}

// Fetch recent users
try {
    $stmt = $conn->query("SELECT fld_user_id, fld_user_name, fld_user_email 
                          FROM tbl_user_ukmart 
                          ORDER BY fld_user_id DESC 
                          LIMIT 5");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_users = [];
}

// Fetch recent products
try {
    $stmt = $conn->query("SELECT p.fld_product_id, p.fld_product_name, p.fld_product_price, 
                          p.fld_product_category, s.fld_seller_name, p.is_available
                          FROM tbl_products_ukmart p
                          LEFT JOIN tbl_sellers_ukmart s ON p.fld_seller_id = s.fld_seller_id
                          ORDER BY p.fld_product_id DESC 
                          LIMIT 5");
    $recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_products = [];
}

// Fetch recent reviews
try {
    $stmt = $conn->query("SELECT r.id, r.rating, r.review, r.created_at,
                          u.fld_user_name as buyer_name,
                          p.fld_product_name
                          FROM tbl_ratings_reviews r
                          LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                          LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                          ORDER BY r.created_at DESC 
                          LIMIT 5");
    $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_reviews = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UKMart</title>
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
            --primary-purple-dark: #5568d3;
            --secondary-pink: #764ba2;
            --accent-blue: #4facfe;
            --accent-cyan: #00f2fe;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Sidebar */
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

        .sidebar-brand p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin: 5px 0 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
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

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            backdrop-filter: blur(10px);
        }

        .sidebar-menu a i {
            width: 25px;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        /* Top Bar */
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

        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .admin-info h5 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .admin-info p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
            color: white;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-color);
        }

        .stat-card.purple::before {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.blue::before {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.green::before {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.orange::before {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-card.red::before {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .stat-card.cyan::before {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }

        .stat-card.indigo::before {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 15px;
        }

        .stat-card.purple .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.blue .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.green .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.orange .stat-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-card.red .stat-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .stat-card.cyan .stat-icon {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }

        .stat-card.indigo .stat-icon {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 5px;
            color: var(--text-dark);
        }

        .stat-card p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Table Section */
        .table-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-section h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .custom-table thead th {
            padding: 15px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .custom-table thead th:first-child {
            border-top-left-radius: 12px;
        }

        .custom-table thead th:last-child {
            border-top-right-radius: 12px;
        }

        .custom-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .custom-table tbody tr:hover {
            background: var(--bg-light);
        }

        .custom-table tbody td {
            padding: 15px;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
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

        /* Responsive */
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding-top: 60px;
            }

            .table-section {
                overflow-x: auto;
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
            <p>Management Dashboard</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="admin_sellers.php"><i class="fas fa-store"></i> Active Sellers</a></li>
            <li><a href="admin_products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="admin_reviews.php"><i class="fas fa-star"></i> Manage Reviews</a></li>
            <li><a href="admin_chats.php"><i class="fas fa-comments"></i> Chat Monitoring</a></li>
            <li><a href="admin_unban_requests.php"><i class="fas fa-ban"></i> Unban Requests</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1>Dashboard Overview</h1>
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <h5><?php echo htmlspecialchars($_SESSION['username']); ?></h5>
                    <p>Administrator</p>
                </div>
                <a href="logout_admin.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total Users</p>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h3><?php echo number_format($total_sellers); ?></h3>
                <p>Total Sellers</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h3><?php echo number_format($total_products); ?></h3>
                <p>Total Products</p>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo number_format($available_products); ?></h3>
                <p>Available Products</p>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo number_format($total_reviews); ?></h3>
                <p>Total Reviews</p>
            </div>

            <div class="stat-card cyan">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3><?php echo $avg_rating; ?> ★</h3>
                <p>Average Rating</p>
            </div>

            <div class="stat-card indigo">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3><?php echo number_format($active_chats); ?></h3>
                <p>Active Chats (7d)</p>
            </div>
        </div>

        <!-- Recent Users Table -->
        <div class="table-section">
            <h3><i class="fas fa-users"></i> Recent Users</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-gray);">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($user['fld_user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['fld_user_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['fld_user_email']); ?></td>
                                <td>
                                    <a href="admin_user_details.php?id=<?php echo $user['fld_user_id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['fld_user_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Products Table -->
        <div class="table-section">
            <h3><i class="fas fa-box"></i> Recent Products</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Seller</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-gray);">No products found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_products as $product): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($product['fld_product_id']); ?></td>
                                <td><?php echo htmlspecialchars($product['fld_product_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($product['fld_product_category']); ?></span></td>
                                <td>RM <?php echo number_format($product['fld_product_price'], 2); ?></td>
                                <td>
    <?php 
    echo htmlspecialchars(
        isset($product['fld_seller_name']) ? $product['fld_seller_name'] : 'N/A'
    ); 
    ?>
</td>

                                <td>
                                    <?php if ($product['is_available'] == 1): ?>
                                        <span class="badge badge-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Unavailable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin_product_detail.php?id=<?php echo $product['fld_product_id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn-action btn-delete" onclick="deleteProduct(<?php echo $product['fld_product_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Reviews Table -->
        <div class="table-section">
            <h3><i class="fas fa-star"></i> Recent Reviews</h3>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Buyer</th>
                        <th>Product</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_reviews)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-gray);">No reviews found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_reviews as $review): ?>
                            <tr>
                            <td>
    <?php 
    echo htmlspecialchars(
        isset($review['buyer_name']) ? $review['buyer_name'] : 'N/A'
    ); 
    ?>
</td>
<td>
    <?php 
    echo htmlspecialchars(
        isset($review['fld_product_name']) ? $review['fld_product_name'] : 'N/A'
    ); 
    ?>
</td>

                                <td>
                                    <span class="rating-stars">
                                        <?php for($i = 0; $i < 5; $i++): ?>
                                            <?php if($i < $review['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($review['review'], 0, 50)) . (strlen($review['review']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                <td>
                                    <a href="admin_review_detail.php?id=<?php echo $review['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn-action btn-delete" onclick="deleteReview(<?php echo $review['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                window.location.href = 'admin_delete_user.php?id=' + userId;
            }
        }

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = 'admin_delete_product.php?id=' + productId;
            }
        }

        function deleteReview(reviewId) {
            if (confirm('Are you sure you want to delete this review?')) {
                window.location.href = 'admin_delete_review.php?id=' + reviewId;
            }
        }

        // Hamburger menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (menuToggle && sidebar && sidebarOverlay) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                
                // Change icon
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            });

            // Close sidebar when clicking a menu item on mobile
            function setupMobileMenuClose() {
                if (window.innerWidth <= 768) {
                    const menuItems = document.querySelectorAll('.sidebar-menu a');
                    menuItems.forEach(item => {
                        // Remove existing listeners by cloning
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

            // Handle window resize
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