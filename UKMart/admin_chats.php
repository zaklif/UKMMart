<?php
session_start();
include('database.php');

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build query with search and filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.sender_name LIKE :search OR c.message LIKE :search OR p.fld_product_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "c.message_type = :type";
    $params[':type'] = $type_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total 
                    FROM tbl_chat_ukmart c
                    LEFT JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
                    $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Get sort parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'timestamp';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Allowed sort columns (for security)
$allowed_sorts = ['chat_id', 'sender_name', 'timestamp', 'fld_product_name'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'timestamp';
}

// Fetch chat messages
try {
    $query = "SELECT c.*, 
          p.fld_product_name,
          buyer.fld_user_name as buyer_name,
          s.fld_seller_name,
          CASE 
              WHEN c.sender_type = 'buyer' THEN buyer.fld_user_name
              WHEN c.sender_type = 'seller' THEN s.fld_seller_name
              ELSE 'Unknown'
          END as sender_name
          FROM tbl_chat_ukmart c
          LEFT JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
          LEFT JOIN tbl_user_ukmart buyer ON c.buyer_id = buyer.fld_user_id
          LEFT JOIN tbl_sellers_ukmart s ON c.seller_id = s.fld_seller_id
          $where_clause
          ORDER BY $sort_by $sort_order
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
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $chats = [];
    $error_message = "Error fetching chats: " . $e->getMessage();
}

// Get statistics
try {
    $stats_query = "SELECT 
                    COUNT(*) as total_messages,
                    COUNT(DISTINCT CONCAT(buyer_id, '-', seller_id)) as total_conversations,
                    SUM(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as messages_24h,
                    SUM(CASE WHEN message_type = 'file' THEN 1 ELSE 0 END) as file_messages
                    FROM tbl_chat_ukmart";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_messages' => 0, 'total_conversations' => 0, 'messages_24h' => 0, 'file_messages' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Monitoring - UKMart Admin</title>
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

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .message-bubble {
            padding: 10px 15px;
            border-radius: 12px;
            background: var(--bg-light);
            max-width: 300px;
            line-height: 1.5;
        }

        .message-bubble.buyer {
            background: #e0e7ff;
        }

        .message-bubble.seller {
            background: #fce7f3;
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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
                padding-top: 80px;
            }

            .top-bar {
                padding-top: 60px;
            }
        }

        
        /* Sortable table headers */
.custom-table thead th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 25px;
}

.custom-table thead th.sortable:hover {
    background: rgba(0, 0, 0, 0.1);
}

.custom-table thead th.sortable::after {
    content: '\f0dc';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 10px;
    opacity: 0.5;
    font-size: 0.8rem;
}

.custom-table thead th.sortable.asc::after {
    content: '\f0de';
    opacity: 1;
}

.custom-table thead th.sortable.desc::after {
    content: '\f0dd';
    opacity: 1;
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
            <li><a href="admin_reviews.php"><i class="fas fa-star"></i> Manage Reviews</a></li>
            <li><a href="admin_chats.php" class="active"><i class="fas fa-comments"></i> Chat Monitoring</a></li>
            <li><a href="admin_unban_requests.php"><i class="fas fa-ban"></i> Unban Requests</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-comments"></i> Chat Monitoring</h1>
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
                    <i class="fas fa-comments"></i>
                </div>
                <h3><?php echo number_format($stats['total_messages']); ?></h3>
                <p>Total Messages</p>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo number_format($stats['total_conversations']); ?></h3>
                <p>Total Conversations</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo number_format($stats['messages_24h']); ?></h3>
                <p>Messages (24h)</p>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-paperclip"></i>
                </div>
                <h3><?php echo number_format($stats['file_messages']); ?></h3>
                <p>File Attachments</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by sender, message, or product..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Message Type</label>
                        <select name="type" class="filter-input">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="text" <?php echo $type_filter === 'text' ? 'selected' : ''; ?>>Text</option>
                            <option value="file" <?php echo $type_filter === 'file' ? 'selected' : ''; ?>>File</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-input">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="admin_chats.php" class="filter-btn btn-reset">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Chats Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> Chat Messages (<?php echo number_format($total_records); ?> total)</h3>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <table class="custom-table">
                <thead>
                    <tr>
                    <th>No.</th>
        <th class="sortable <?php echo $sort_by === 'chat_id' ? strtolower($sort_order) : ''; ?>" 
            onclick="sortTable('chat_id', '<?php echo $sort_by === 'chat_id' && $sort_order === 'ASC' ? 'desc' : 'asc'; ?>')">
            ID
        </th>
        <th class="sortable <?php echo $sort_by === 'sender_name' ? strtolower($sort_order) : ''; ?>" 
            onclick="sortTable('sender_name', '<?php echo $sort_by === 'sender_name' && $sort_order === 'ASC' ? 'desc' : 'asc'; ?>')">
            Sender
        </th>
        <th>Type</th>
        <th class="sortable <?php echo $sort_by === 'fld_product_name' ? strtolower($sort_order) : ''; ?>" 
            onclick="sortTable('fld_product_name', '<?php echo $sort_by === 'fld_product_name' && $sort_order === 'ASC' ? 'desc' : 'asc'; ?>')">
            Product
        </th>
        <th>Message</th>
        <th class="sortable <?php echo $sort_by === 'timestamp' ? strtolower($sort_order) : ''; ?>" 
            onclick="sortTable('timestamp', '<?php echo $sort_by === 'timestamp' && $sort_order === 'ASC' ? 'desc' : 'asc'; ?>')">
            Time
        </th>
        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chats)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p style="margin-top: 15px;">No chat messages found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
$row_number = $offset + 1; // Start numbering from current page offset
foreach ($chats as $chat): 
?>
    <tr>
        <!-- Row Number -->
        <td><strong><?php echo $row_number++; ?></strong></td>
        
        <!-- ID -->
        <td>#<?php echo htmlspecialchars($chat['chat_id']); ?></td>
        
        <!-- Sender -->
        <td>
        <strong>
    <?php 
    echo htmlspecialchars(
        isset($chat['sender_name']) ? $chat['sender_name'] : 'Unknown'
    ); 
    ?>
</strong>

            <br>
            <?php if ($chat['sender_type'] === 'buyer'): ?>
                <span class="badge badge-info"><i class="fas fa-shopping-cart"></i> Buyer</span>
            <?php else: ?>
                <span class="badge badge-warning"><i class="fas fa-store"></i> Seller</span>
            <?php endif; ?>
        </td>
        
        <!-- Type -->
        <td>
            <?php if (isset($chat['message_type']) && $chat['message_type'] === 'file'): ?>
                <span class="badge badge-danger"><i class="fas fa-paperclip"></i> File</span>
            <?php else: ?>
                <span class="badge badge-success"><i class="fas fa-comment"></i> Text</span>
            <?php endif; ?>
        </td>
        
        <!-- Product -->
        <td>
            <?php if (!empty($chat['fld_product_name'])): ?>
                <?php echo htmlspecialchars($chat['fld_product_name']); ?>
            <?php else: ?>
                <span style="color: var(--text-gray);">N/A</span>
            <?php endif; ?>
        </td>
        
        <!-- Message -->
        <td>
            <?php 
            $message = isset($chat['message']) ? $chat['message'] : '';

            if (strlen($message) > 50) {
                echo htmlspecialchars(substr($message, 0, 50)) . '...';
            } else {
                echo htmlspecialchars($message);
            }
            ?>
        </td>
        
        <!-- Time -->
        <td>
            <?php echo date('M d, Y', strtotime($chat['timestamp'])); ?>
            <br><small style="color: var(--text-gray);">
                <?php echo date('h:i A', strtotime($chat['timestamp'])); ?>
            </small>
        </td>
        
        <!-- Actions -->
        <td>
            <a href="admin_chat_detail.php?buyer=<?php echo $chat['buyer_id']; ?>&seller=<?php echo $chat['seller_id']; ?>&product=<?php echo $chat['product_id']; ?>" class="btn-action btn-view">
                <i class="fas fa-eye"></i> View
            </a>
            <button class="btn-action btn-delete" onclick="deleteMessage(<?php echo $chat['chat_id']; ?>)">
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
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo strtolower($sort_order); ?>" class="page-link">
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
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo strtolower($sort_order); ?>" 
               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo strtolower($sort_order); ?>" class="page-link">
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
        function deleteMessage(chatId) {
            if (confirm('Are you sure you want to delete this message?\n\nThis action cannot be undone!')) {
                window.location.href = 'admin_delete_chat.php?id=' + chatId;
            }
        }

        function sortTable(column, order) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', column);
    urlParams.set('order', order);
    urlParams.set('page', '1'); // Reset to first page when sorting
    window.location.search = urlParams.toString();
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
                                