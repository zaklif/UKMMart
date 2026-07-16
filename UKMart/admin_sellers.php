<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Pagination settings
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build query with search
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.fld_seller_name LIKE :search OR u.fld_user_name LIKE :search OR u.fld_user_email LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Determine order by clause
switch($sort_by) {
    case 'rating_high':
        $order_by = "ORDER BY s.fld_seller_rating DESC";
        break;
    case 'rating_low':
        $order_by = "ORDER BY s.fld_seller_rating ASC";
        break;
    case 'products_high':
        $order_by = "ORDER BY total_products DESC";
        break;
    case 'products_low':
        $order_by = "ORDER BY total_products ASC";
        break;
    case 'oldest':
        $order_by = "ORDER BY s.fld_seller_id ASC";
        break;
    default: // latest
        $order_by = "ORDER BY s.fld_seller_id DESC";
}

// Get total records for pagination
try {
    $count_query = "SELECT COUNT(DISTINCT s.fld_seller_id) as total 
                    FROM tbl_sellers_ukmart s
                    LEFT JOIN tbl_user_ukmart u ON s.fld_user_id = u.fld_user_id
                    $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Fetch sellers with their activity
try {
    $query = "SELECT s.*, u.fld_user_name, u.fld_user_email, u.fld_user_status,
              COUNT(DISTINCT p.fld_product_id) as total_products,
              SUM(CASE WHEN p.is_available = 1 AND p.fld_product_quantity > 0 THEN 1 ELSE 0 END) as available_products,
              COUNT(DISTINCT r.id) as total_reviews,
              COALESCE(AVG(r.rating), 0) as avg_review_rating
              FROM tbl_sellers_ukmart s
              LEFT JOIN tbl_user_ukmart u ON s.fld_user_id = u.fld_user_id
              LEFT JOIN tbl_products_ukmart p ON s.fld_seller_id = p.fld_seller_id
              LEFT JOIN tbl_ratings_reviews r ON s.fld_seller_id = r.seller_id
              $where_clause
              GROUP BY s.fld_seller_id
              $order_by
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    
    // Bind search params
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination params
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sellers = [];
    $error_message = "Error fetching sellers: " . $e->getMessage();
}

// Get statistics
try {
    $stats_query = "SELECT 
                    COUNT(DISTINCT s.fld_seller_id) as total_sellers,
                    COUNT(DISTINCT p.fld_product_id) as total_products,
                    SUM(CASE WHEN p.is_available = 1 AND p.fld_product_quantity > 0 THEN 1 ELSE 0 END) as active_products,
                    COALESCE(AVG(s.fld_seller_rating), 0) as avg_seller_rating
                    FROM tbl_sellers_ukmart s
                    LEFT JOIN tbl_products_ukmart p ON s.fld_seller_id = p.fld_seller_id";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_sellers' => 0, 'total_products' => 0, 'active_products' => 0, 'avg_seller_rating' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Sellers - UKMart Admin</title>
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

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 0 5px;
            color: var(--text-dark);
        }

        .stat-card p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-reset {
            background: var(--bg-light);
            color: var(--text-dark);
        }

        .btn-reset:hover {
            background: var(--border-color);
        }

        /* Table Section */
        .table-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

        .seller-profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            border: 2px solid var(--border-color);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
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

        .rating-stars {
            color: #f59e0b;
            font-size: 0.9rem;
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
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-link {
            padding: 10px 15px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: var(--primary-purple);
            color: white;
            border-color: var(--primary-purple);
        }

        .page-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
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

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
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
            <li><a href="admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="admin_sellers.php" class="active"><i class="fas fa-store"></i> Active Sellers</a></li>
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
            <h1><i class="fas fa-store"></i> Active Sellers</h1>
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
                    <i class="fas fa-store"></i>
                </div>
                <h3><?php echo number_format($stats['total_sellers']); ?></h3>
                <p>Active Sellers</p>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h3><?php echo number_format($stats['total_products']); ?></h3>
                <p>Products Listed</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo number_format($stats['active_products']); ?></h3>
                <p>Available Products</p>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo number_format($stats['avg_seller_rating'], 1); ?> ★</h3>
                <p>Average Seller Rating</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by seller or user name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort" class="filter-input">
                            <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="rating_high" <?php echo $sort_by === 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="rating_low" <?php echo $sort_by === 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                            <option value="products_high" <?php echo $sort_by === 'products_high' ? 'selected' : ''; ?>>Most Products</option>
                            <option value="products_low" <?php echo $sort_by === 'products_low' ? 'selected' : ''; ?>>Least Products</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="admin_sellers.php" class="filter-btn btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Sellers Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> All Active Sellers (<?php echo number_format($total_records); ?> total)</h3>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Seller ID</th>
                        <th>Seller Name</th>
                        <th>User Account</th>
                        <th>Products</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sellers)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-gray); padding: 40px;">
                                <i class="fas fa-store-slash" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p style="margin-top: 15px;">No sellers found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sellers as $seller): ?>
                            <tr>
                                <td>
                                <?php 
echo htmlspecialchars(
    isset($seller['fld_user_name']) ? $seller['fld_user_name'] : 'N/A'
); 
?>
<br>
<small style="color: var(--text-gray);">
    <?php 
    echo htmlspecialchars(
        isset($seller['fld_user_email']) ? $seller['fld_user_email'] : 'N/A'
    ); 
    ?>
</small>

                                </td>
                                <td>
                                    <strong><?php echo number_format($seller['total_products']); ?></strong> total
                                    <br><small style="color: var(--success);">
                                        <?php echo number_format($seller['available_products']); ?> available
                                    </small>
                                </td>
                                <td>
                                    <?php if ($seller['fld_seller_rating'] > 0): ?>
                                        <span class="rating-stars">
                                            <?php echo number_format($seller['fld_seller_rating'], 1); ?> ★
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">No rating</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($seller['total_reviews'] > 0): ?>
                                        <span class="badge badge-info">
                                            <?php echo number_format($seller['total_reviews']); ?> reviews
                                        </span>
                                        <?php if ($seller['avg_review_rating'] > 0): ?>
                                            <br><small class="rating-stars">
                                                Avg: <?php echo number_format($seller['avg_review_rating'], 1); ?> ★
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">No reviews</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = isset($seller['fld_user_status']) ? $seller['fld_user_status'] : 'active';

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
                                        default:
                                            $badge_class = 'badge-success';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="admin_seller_detail.php?id=<?php echo $seller['fld_seller_id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn-action btn-delete" onclick="deleteSeller(<?php echo $seller['fld_seller_id']; ?>, '<?php echo htmlspecialchars(addslashes($seller['fld_seller_name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">tbl_product_ukmart<?php if (!empty($seller['fld_profile_pic'])): ?>
                                        <img src="profile/<?php echo htmlspecialchars($seller['fld_profile_pic']); ?>" alt="Profile" class="seller-profile-img">
                                    <?php else: ?>
                                        <div class="seller-avatar">
                                            <?php echo strtoupper(substr($seller['fld_seller_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>#<?php echo htmlspecialchars($seller['fld_seller_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($seller['fld_seller_name']); ?></strong>
                                    <?php if (!empty($seller['fld_seller_description'])): ?>
                                        <br><small style="color: var(--text-gray);">
                                            <?php echo htmlspecialchars(substr($seller['fld_seller_description'], 0, 40)) . (strlen($seller['fld_seller_description']) > 40 ? '...' : ''); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteSeller(sellerId, sellerName) {
            if (confirm('Are you sure you want to delete seller "' + sellerName + '"?\n\nThis will also delete all their products and cannot be undone!')) {
                window.location.href = 'admin_delete_seller.php?id=' + sellerId;
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