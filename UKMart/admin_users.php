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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with search and filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(fld_user_name LIKE :search OR fld_user_email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "fld_user_status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Sorting
$allowed_sort_columns = [
    'id' => 'u.fld_user_id',
    'name' => 'u.fld_user_name',
    'email' => 'u.fld_user_email',
    'products' => 'total_products',
    'rating' => 'avg_rating',
    'status' => 'u.fld_user_status'
];

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

$sort_column = isset($allowed_sort_columns[$sort]) ? $allowed_sort_columns[$sort] : 'u.fld_user_id';

$order = ($order === 'asc') ? 'ASC' : 'DESC';

// Get total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total FROM tbl_user_ukmart $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Fetch users with their selling activity
try {
    $query = "SELECT u.*, 
              COUNT(DISTINCT s.fld_seller_id) as is_seller,
              COUNT(DISTINCT p.fld_product_id) as total_products,
              COALESCE(AVG(s.fld_seller_rating), 0) as avg_rating
              FROM tbl_user_ukmart u
              LEFT JOIN tbl_sellers_ukmart s ON u.fld_user_id = s.fld_user_id
              LEFT JOIN tbl_products_ukmart p ON s.fld_seller_id = p.fld_seller_id
              $where_clause
              GROUP BY u.fld_user_id
              ORDER BY $sort_column $order
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
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error_message = "Error fetching users: " . $e->getMessage();
}

// Get statistics
try {
    $stats_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN fld_user_status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN fld_user_status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                    SUM(CASE WHEN fld_user_status = 'banned' THEN 1 ELSE 0 END) as banned_users
                    FROM tbl_user_ukmart";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_users' => 0, 'active_users' => 0, 'suspended_users' => 0, 'banned_users' => 0];
}

function sortLink($column, $currentSort, $currentOrder) {
    $order = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    return "sort=$column&order=$order";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - UKMart Admin</title>
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

        .stat-card.green::before {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.orange::before {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

        .stat-card.green .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card.orange .stat-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

        .badge-secondary {
            background: #f1f5f9;
            color: #475569;
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

        .btn-suspend {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-suspend:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
        }

        .btn-ban {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-ban:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-activate {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-activate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
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

        .user-role-tags {
            display: flex;
            gap: 5px;
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

        /* Extra mobile & tablet improvements */
@media (max-width: 992px) {
    .sidebar {
        width: 240px;
    }

    .main-content {
        margin-left: 0;
    }
}

@media (max-width: 576px) {
    .top-bar h1 {
        font-size: 1.4rem;
    }

    .btn-action {
        padding: 6px 8px;
        font-size: 0.75rem;
        margin-bottom: 5px;
    }

    .custom-table thead th,
    .custom-table tbody td {
        padding: 10px;
        font-size: 0.8rem;
    }

    .user-role-tags {
        flex-direction: column;
    }
}

/* Sortable table header links */
th a {
    color: #ffffff !important;
    text-decoration: none;
    font-weight: 600;
}

/* Hover effect */
th a:hover {
    color: #f1f1f1;
    text-decoration: underline;
}

/* Visited link color (important) */
th a:visited {
    color: #ffffff !important;
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
            <li><a href="admin_users.php" class="active"><i class="fas fa-users"></i> Manage Users</a></li>
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
            <h1><i class="fas fa-users"></i> Manage Users</h1>
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
                <h3><?php echo number_format($stats['total_users']); ?></h3>
                <p>Total Students</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo number_format($stats['active_users']); ?></h3>
                <p>Active Students</p>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-pause-circle"></i>
                </div>
                <h3><?php echo number_format($stats['suspended_users']); ?></h3>
                <p>Suspended Students</p>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h3><?php echo number_format($stats['banned_users']); ?></h3>
                <p>Banned Students</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-input">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="admin_users.php" class="filter-btn btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> All Students (<?php echo number_format($total_records); ?> total)</h3>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                    <th>No.</th>
                        <th>
                            <a href="?<?php echo sortLink('id', $sort, strtolower($order)); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                        ID
                        <?php if ($sort === 'id'): ?>
                        <i class="fas fa-sort-<?php echo strtolower($order) === 'asc' ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                        </a></th>
                        <th>
                        <a href="?sort=name&order=<?php echo ($sort === 'name' && $order === 'asc') ? 'desc' : 'asc'; ?>">
                        Name
                        <?php if ($sort === 'name'): ?>
                        <?php echo $order === 'asc' ? '▲' : '▼'; ?>
                        <?php endif; ?>
                        </a>
                        </th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Products</th>
                        <th>
    <a href="?<?php echo sortLink('rating', $sort, strtolower($order)); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
        Rating
        <?php if ($sort === 'rating'): ?>
            <i class="fas fa-sort-<?php echo strtolower($order) === 'asc' ? 'up' : 'down'; ?>"></i>
        <?php endif; ?>
    </a>
</th>

                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p style="margin-top: 15px;">No users found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $counter = $offset + 1; ?>

                        <?php foreach ($users as $user): ?>
                            <tr>
                            <td><?php echo $counter++; ?></td>
                                <td>#<?php echo htmlspecialchars($user['fld_user_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($user['fld_user_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['fld_user_email']); ?></td>
                                <td>
                                    <div class="user-role-tags">
                                        <span class="badge badge-info">Buyer</span>
                                        <?php if ($user['is_seller'] > 0): ?>
                                            <span class="badge badge-secondary">Seller</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo number_format($user['total_products']); ?></td>
                                <td>
                                    <?php if ($user['avg_rating'] > 0): ?>
                                        <span style="color: #f59e0b;">
                                            <?php echo number_format($user['avg_rating'], 1); ?> ★
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
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
                                        default:
                                            $badge_class = 'badge-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="admin_user_details.php?id=<?php echo $user['fld_user_id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    
                                    <?php if ($status === 'active'): ?>
                                        <button type="button" class="btn-action btn-suspend" 
                                                onclick="openSuspendModal(<?php echo $user['fld_user_id']; ?>)">
                                            <i class="fas fa-pause"></i> Suspend
                                        </button>
                                    <?php elseif ($status === 'suspended'): ?>
                                        <a href="admin_update_user_status.php?id=<?php echo $user['fld_user_id']; ?>&status=active" 
                                           class="btn-action btn-activate"
                                           onclick="return confirm('Activate this user?')">
                                            <i class="fas fa-check"></i> Activate
                                        </a>
                                        <button type="button" class="btn-action btn-ban" 
                                                onclick="openBanModal(<?php echo $user['fld_user_id']; ?>)">
                                            <i class="fas fa-ban"></i> Ban
                                        </button>
                                    <?php elseif ($status === 'banned'): ?>
                                        <a href="admin_update_user_status.php?id=<?php echo $user['fld_user_id']; ?>&status=active" 
                                           class="btn-action btn-activate"
                                           onclick="return confirm('Unban this user?')">
                                            <i class="fas fa-undo"></i> Unban
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php 
        // Build base URL with search, status, and sort
        $base_url = "?search=" . urlencode($search) . "&status=" . $status_filter . "&sort=" . $sort . "&order=" . strtolower($order); 
        ?>

        <!-- Previous -->
        <?php if ($page > 1): ?>
            <a class="page-link" href="<?php echo $base_url . '&page=' . ($page - 1); ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        <?php else: ?>
            <span class="page-link disabled">
                <i class="fas fa-chevron-left"></i> Previous
            </span>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a class="page-link <?php echo ($i === $page) ? 'active' : ''; ?>" href="<?php echo $base_url . '&page=' . $i; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <!-- Next -->
        <?php if ($page < $total_pages): ?>
            <a class="page-link" href="<?php echo $base_url . '&page=' . ($page + 1); ?>">
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

    <!-- Ban User Modal -->
<div class="modal fade" id="banModal" tabindex="-1" aria-labelledby="banModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                <h5 class="modal-title" id="banModalLabel">
                    <i class="fas fa-ban"></i> Ban User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="banForm" action="admin_update_user_status.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="ban_user_id">
                    <input type="hidden" name="status" value="banned">
                    
                    <div class="mb-3">
                        <label for="ban_reason" class="form-label">Reason for Ban <span style="color: red;">*</span></label>
                        <textarea class="form-control" id="ban_reason" name="ban_reason" rows="4" 
                                  placeholder="Enter the reason for banning this user..." required></textarea>
                        <small class="text-muted">This reason will be visible to the user.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will permanently ban the user from the platform until you manually unban them.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Ban User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

 <!-- Suspend User Modal -->
 <div class="modal fade" id="suspendModal" tabindex="-1" aria-labelledby="suspendModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                    <h5 class="modal-title" id="suspendModalLabel">
                        <i class="fas fa-pause"></i> Suspend User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="suspendForm" action="admin_update_user_status.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="suspend_user_id">
                        <input type="hidden" name="status" value="suspended">
                        
                        <div class="mb-3">
                            <label for="suspend_reason" class="form-label">Reason for Suspension <span style="color: red;">*</span></label>
                            <textarea class="form-control" id="suspend_reason" name="suspend_reason" rows="4" 
                                      placeholder="Enter the reason for suspending this user..." required></textarea>
                            <small class="text-muted">This reason will be visible to the user.</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This action will temporarily suspend the user. You can activate or ban them later.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-pause"></i> Suspend User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
// Handle ban button clicks
function openBanModal(userId) {
    document.getElementById('ban_user_id').value = userId;
    document.getElementById('ban_reason').value = '';
    var banModal = new bootstrap.Modal(document.getElementById('banModal'));
    banModal.show();
}

function openSuspendModal(userId) {
        document.getElementById('suspend_user_id').value = userId;
        document.getElementById('suspend_reason').value = '';
        var suspendModal = new bootstrap.Modal(document.getElementById('suspendModal'));
        suspendModal.show();
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