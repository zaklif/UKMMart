<?php
session_start();
include('database.php');

// Check admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch unban requests
try {
    $stmt = $conn->query("
        SELECT u.fld_user_id, u.fld_user_name, u.fld_user_email, 
               r.fld_request_id as request_id, 
               r.fld_request_message as reason, 
               r.fld_request_status as status, 
               r.fld_created_at as created_at
        FROM tbl_unban_requests r
        JOIN tbl_user_ukmart u ON r.fld_user_id = u.fld_user_id
        ORDER BY r.fld_created_at DESC
    ");
    $unban_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unban_requests = [];
    error_log("Unban requests query error: " . $e->getMessage());
}

// Get statistics
try {
    $stats_query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN fld_request_status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN fld_request_status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN fld_request_status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
                    FROM tbl_unban_requests";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_requests' => 0, 'pending_requests' => 0, 'approved_requests' => 0, 'rejected_requests' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unban Requests - UKMart Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Copy all your admin styles here from admin_users.php */

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
        }
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h2>UKMart Admin</h2>
            <p>Management Dashboard</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
            <li><a href="admin_sellers.php"><i class="fas fa-store"></i> Manage Sellers</a></li>
            <li><a href="admin_products.php"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="admin_reviews.php"><i class="fas fa-star"></i> Manage Reviews</a></li>
            <li><a href="admin_chats.php"><i class="fas fa-comments"></i> Chat Monitoring</a></li>
            <li><a href="admin_unban_requests.php" class="active"><i class="fas fa-user-lock"></i> Unban Requests</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-user-lock"></i> Unban Requests</h1>
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

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3><?php echo number_format($stats['total_requests']); ?></h3>
                <p>Total Requests</p>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                <p>Pending Requests</p>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo number_format($stats['approved_requests']); ?></h3>
                <p>Approved</p>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3><?php echo number_format($stats['rejected_requests']); ?></h3>
                <p>Rejected</p>
            </div>
        </div>

        <!-- Unban Requests Table -->
        <div class="table-section">
            <h3><i class="fas fa-list"></i> All Unban Requests</h3>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unban_requests)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; color: var(--text-gray); padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p style="margin-top: 15px;">No requests found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unban_requests as $req): 
                                $status_lower = strtolower(trim($req['status']));
                            ?>
                                <tr>
                                    <td>#<?php echo $req['request_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($req['fld_user_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($req['fld_user_email']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($req['reason'],0,50)) . (strlen($req['reason'])>50?'...':''); ?></td>
                                    <td>
                                        <?php if($status_lower == 'pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php elseif($status_lower == 'approved'): ?>
                                            <span class="badge badge-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <?php if($status_lower == 'pending'): ?>
                                            <a href="admin_unban_action.php?id=<?php echo $req['request_id']; ?>&action=approve" 
                                               class="btn-action btn-view"
                                               onclick="return confirm('Approve this unban request?')">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="admin_unban_action.php?id=<?php echo $req['request_id']; ?>&action=reject" 
                                               class="btn-action btn-delete"
                                               onclick="return confirm('Reject this unban request?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-gray);">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">    </script>

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