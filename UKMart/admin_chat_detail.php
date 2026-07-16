<?php
session_start();
include('database.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$buyer_id = isset($_GET['buyer']) ? (int)$_GET['buyer'] : 0;
$seller_id = isset($_GET['seller']) ? (int)$_GET['seller'] : 0;
$product_id = isset($_GET['product']) ? (int)$_GET['product'] : 0;

if ($buyer_id === 0 || $seller_id === 0 || $product_id === 0) {
    header("Location: admin_chats.php");
    exit;
}

// Fetch conversation
try {
    $stmt = $conn->prepare("SELECT c.*, 
                           p.fld_product_name,
                           buyer.fld_user_name as buyer_name,
                           seller_user.fld_user_name as seller_user_name,
                           s.fld_seller_name
                           FROM tbl_chat_ukmart c
                           LEFT JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
                           LEFT JOIN tbl_user_ukmart buyer ON c.buyer_id = buyer.fld_user_id
                           LEFT JOIN tbl_sellers_ukmart s ON c.seller_id = s.fld_seller_id
                           LEFT JOIN tbl_user_ukmart seller_user ON s.fld_user_id = seller_user.fld_user_id
                           WHERE c.buyer_id = :buyer_id 
                           AND c.seller_id = :seller_id 
                           AND c.product_id = :product_id
                           ORDER BY c.timestamp ASC");
    $stmt->bindParam(':buyer_id', $buyer_id);
    $stmt->bindParam(':seller_id', $seller_id);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        header("Location: admin_chats.php?error=conversation_not_found");
        exit;
    }
    
    $first_msg = $messages[0];
} catch (PDOException $e) {
    header("Location: admin_chats.php?error=database_error");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Conversation - UKMart Admin</title>
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
        .chat-info { display: flex; gap: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px; }
        .chat-info-item { flex: 1; }
        .chat-info-item label { font-weight: 600; color: #64748b; font-size: 0.85rem; display: block; margin-bottom: 5px; }
        .chat-info-item p { margin: 0; font-size: 1rem; color: #1e293b; }
        .chat-container { max-width: 800px; margin: 0 auto; }
        .message { margin-bottom: 20px; display: flex; gap: 15px; }
        .message.buyer { flex-direction: row; }
        .message.seller { flex-direction: row-reverse; }
        .message-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .message.seller .message-avatar { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .message-content { flex: 1; max-width: 70%; }
        .message-header { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .message.seller .message-header { justify-content: flex-end; }
        .message-sender { font-weight: 600; color: #1e293b; }
        .message-time { font-size: 0.75rem; color: #64748b; }
        .message-bubble { padding: 15px 20px; border-radius: 16px; line-height: 1.6; }
        .message.buyer .message-bubble { background: #e0e7ff; border-bottom-left-radius: 4px; }
        .message.seller .message-bubble { background: #fce7f3; border-bottom-right-radius: 4px; text-align: right; }
        .message-file { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: white; border-radius: 10px; border: 2px solid #e2e8f0; }
        .message-file i { font-size: 1.5rem; color: #667eea; }
        .message-file a { color: #667eea; text-decoration: none; font-weight: 600; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { padding: 15px; background: #f8fafc; border-radius: 10px; text-align: center; }
        .stat-box h4 { font-size: 1.8rem; font-weight: 800; margin: 0 0 5px; color: #667eea; }
        .stat-box p { margin: 0; color: #64748b; font-size: 0.85rem; }
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

            .message-content {
                max-width: 85%;
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
            <h1><i class="fas fa-comments"></i> Chat Conversation</h1>
            <a href="admin_chats.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Chats</a>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Conversation Details</h3>
            <div class="chat-info">
            <div class="chat-info-item">
    <label>Buyer</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($first_msg['buyer_name']) ? $first_msg['buyer_name'] : 'Unknown'
        ); 
        ?>
    </p>
</div>

<div class="chat-info-item">
    <label>Seller</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($first_msg['fld_seller_name']) ? $first_msg['fld_seller_name'] : 'Unknown'
        ); 
        ?>
    </p>
</div>

<div class="chat-info-item">
    <label>Product</label>
    <p>
        <?php 
        echo htmlspecialchars(
            isset($first_msg['fld_product_name']) ? $first_msg['fld_product_name'] : 'N/A'
        ); 
        ?>
    </p>
</div>

            </div>

            <div class="stats-row">
                <div class="stat-box">
                    <h4><?php echo count($messages); ?></h4>
                    <p>Total Messages</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo count(array_filter($messages, function($m) { return $m['sender_type'] === 'buyer'; })); ?></h4>
                    <p>From Buyer</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo count(array_filter($messages, function($m) { return $m['sender_type'] === 'seller'; })); ?></h4>
                    <p>From Seller</p>
                </div>
                <div class="stat-box">
                    <h4><?php echo count(array_filter($messages, function($m) { return $m['message_type'] === 'file'; })); ?></h4>
                    <p>Files Shared</p>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-comment-dots"></i> Messages (<?php echo count($messages); ?>)</h3>
            <div class="chat-container">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_type']; ?>">
                        <div class="message-avatar">
                            <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                        </div>
                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                <span class="message-time">
                                    <?php echo date('M d, Y h:i A', strtotime($msg['timestamp'])); ?>
                                </span>
                            </div>
                            <?php if ($msg['message_type'] === 'file'): ?>
                                <div class="message-file">
                                    <i class="fas fa-file"></i>
                                    <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" target="_blank">
                                        View File
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="message-bubble">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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