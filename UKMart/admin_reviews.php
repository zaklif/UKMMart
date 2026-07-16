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

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build query with search and filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.fld_user_name LIKE :search OR p.fld_product_name LIKE :search OR r.review LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($rating_filter !== 'all') {
    $where_conditions[] = "r.rating = :rating";
    $params[':rating'] = (int)$rating_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Determine order by clause
switch($sort_by) {
    case 'oldest':
        $order_by = "ORDER BY r.created_at ASC";
        break;
    case 'rating_high':
        $order_by = "ORDER BY r.rating DESC";
        break;
    case 'rating_low':
        $order_by = "ORDER BY r.rating ASC";
        break;
    default: // latest
        $order_by = "ORDER BY r.created_at DESC";
}

// Get total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total 
                    FROM tbl_ratings_reviews r
                    LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                    LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
                    $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Fetch reviews
try {
    $query = "SELECT r.*, 
              u.fld_user_name as buyer_name,
              u.fld_user_email as buyer_email,
              p.fld_product_name,
              p.fld_product_id,
              s.fld_seller_name,
              s.fld_seller_id
              FROM tbl_ratings_reviews r
              LEFT JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
              LEFT JOIN tbl_products_ukmart p ON r.product_id = p.fld_product_id
              LEFT JOIN tbl_sellers_ukmart s ON r.seller_id = s.fld_seller_id
              $where_clause
              $order_by
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    
    // Bind search/filter params
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination params
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reviews = [];
    $error_message = "Error fetching reviews: " . $e->getMessage();
}

// Get statistics
try {
    $stats_query = "SELECT 
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_rating,
                    COALESCE(AVG(rating), 0) as avg_rating
                    FROM tbl_ratings_reviews";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_reviews' => 0, 'five_star' => 0, 'four_star' => 0, 'three_star' => 0, 'low_rating' => 0, 'avg_rating' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - UKMart Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .stat-card.yellow::before {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }

        .stat-card.green::before {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.blue::before {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-card.red::before {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

        .stat-card.yellow .stat-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }

        .stat-card.green .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.blue .stat-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-card.red .stat-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            min-width: 180px;
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

        .rating-stars {
            color: #f59e0b;
            font-size: 1rem;
        }

        .review-text {
            max-width: 300px;
            line-height: 1.5;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
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
            <li><a href="admin_sellers.php"><i class="fas fa-store"></i> Active Sellers</a></li>
            <li><a href="admin_products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="admin_reviews.php" class="active"><i class="fas fa-star"></i> Manage Reviews</a></li>
            <li><a href="admin_chats.php"><i class="fas fa-comments"></i> Chat Monitoring</a></li>
            <li><a href="admin_unban_requests.php"><i class="fas fa-ban"></i> Unban Requests</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-star"></i> Manage Reviews</h1>
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
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo number_format($stats['total_reviews']); ?></h3>
                <p>Total Reviews</p>
            </div>

            <div class="stat-card yellow">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3><?php echo number_format($stats['avg_rating'], 1); ?> ★</h3>
                <p>Average Rating</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <h3><?php echo number_format($stats['five_star']); ?></h3>
                <p>5 Star Reviews</p>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <h3><?php echo number_format($stats['four_star']); ?></h3>
                <p>4 Star Reviews</p>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3><?php echo number_format($stats['low_rating']); ?></h3>
                <p>Low Ratings (≤2★)</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by buyer, product, or review..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Rating</label>
                        <select name="rating" class="filter-input">
                            <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                            <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort" class="filter-input">
                            <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="rating_high" <?php echo $sort_by === 'rating_high' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="rating_low" <?php echo $sort_by === 'rating_low' ? 'selected' : ''; ?>>Lowest Rating</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="admin_reviews.php" class="filter-btn btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Reviews Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> All Reviews (<?php echo number_format($total_records); ?> total)</h3>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <table class="custom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Buyer</th>
                        <th>Product</th>
                        <th>Seller</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p style="margin-top: 15px;">No reviews found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($review['id']); ?></td>
                                <td>
                                <strong>
    <?php 
    echo htmlspecialchars(
        isset($review['buyer_name']) ? $review['buyer_name'] : 'Unknown'
    ); 
    ?>
</strong>

                                    <?php if (!empty($review['buyer_email'])): ?>
                                        <br><small style="color: var(--text-gray);">
                                            <?php echo htmlspecialchars($review['buyer_email']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($review['fld_product_name'])): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($review['fld_product_name']); ?>
                                        </span>
                                        <br><small style="color: var(--text-gray);">
                                            ID: #<?php echo $review['product_id']; ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">Product Deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($review['fld_seller_name'])): ?>
                                        <?php echo htmlspecialchars($review['fld_seller_name']); ?>
                                        <br><small style="color: var(--text-gray);">
                                            ID: #<?php echo $review['seller_id']; ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">N/A</span>
                                    <?php endif; ?>
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
                                    <br><small><strong><?php echo $review['rating']; ?>/5</strong></small>
                                </td>
                                <td>
                                    <div class="review-text">
                                        <?php 
                                        $review_text = htmlspecialchars($review['review']);
                                        echo strlen($review_text) > 100 ? substr($review_text, 0, 100) . '...' : $review_text;
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    <br><small style="color: var(--text-gray);">
                                        <?php echo date('h:i A', strtotime($review['created_at'])); ?>
                                    </small>
                                </td>
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort_by; ?>" class="page-link">
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
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort_by; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&sort=<?php echo $sort_by; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteReview(reviewId) {
            if (confirm('Are you sure you want to delete this review?\n\nThis action cannot be undone!')) {
                window.location.href = 'admin_delete_review.php?id=' + reviewId;
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