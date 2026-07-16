<?php
// Session and DB initialization for Seller
if (!isset($_SESSION)) {
    session_start();
}

// Validate session - must be logged in as seller
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($conn)) {
    if (file_exists('db_connect.php')) {
        include('db_connect.php');
    } elseif (file_exists('database.php')) {
        include_once('database.php');
    } else {
        die('Database connection failed');
    }
}

// Get seller name
$seller_name = 'Seller';
try {
    $user_id = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    if (!$user_id) throw new Exception('Invalid user ID');
    
    // Get seller info
    $stmt = $conn->prepare("SELECT fld_seller_name, fld_seller_id FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $seller_name = isset($row['fld_seller_name']) ? $row['fld_seller_name'] : 'Seller';
        $_SESSION['seller_id'] = $row['fld_seller_id'];
    }
} catch (Exception $e) {
    error_log("Error fetching seller name: " . $e->getMessage());
}

// Get chat requests count
$chat_requests_count = 0;
try {
    $seller_id = isset($_SESSION['seller_id']) ? $_SESSION['seller_id'] : 0;
    if ($seller_id > 0) {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT CONCAT(buyer_id, '-', product_id)) as total
            FROM tbl_chat_ukmart 
            WHERE seller_id = ?
        ");
        $stmt->execute([$seller_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $chat_requests_count = isset($row['total']) ? (int)$row['total'] : 0;
    }
} catch (Exception $e) {
    error_log("Error fetching chat count: " . $e->getMessage());
}
?>

<style>
    /* Import Satoshi Font */
    @import url('https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap');
    
    /* CSS Variables for consistent theme */
    :root {
      --primary-blue: #2563eb;
      --primary-blue-dark: #1e40af;
      --primary-blue-light: #3b82f6;
      --accent-orange: #f97316;
      --accent-green: #10b981;
      --bg-light: #f8fafc;
      --bg-white: #ffffff;
      --text-dark: #1e293b;
      --text-gray: #64748b;
      --border-color: #e2e8f0;
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
      --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    /* Global font family */
    * {
      font-family: 'Satoshi', Georgia, serif !important;
    }
    
    /* ===== Seller Navbar ===== */
    .seller-navbar {
      background: #ffffff;
      border-bottom: 1px solid #e5e7eb;
      padding: 0.75rem 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    
    .seller-navbar-brand {
      font-size: 1.5rem;
      font-weight: 600;
      color: #2563eb;
      letter-spacing: -0.3px;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .seller-navbar-brand:hover {
      color: #1e40af;
    }
    
    .seller-navbar-brand img {
      height: 40px;
      width: auto;
      object-fit: contain;
    }
    
    .seller-nav-link, .seller-navbar-text {
      color: #374151 !important;
      font-weight: 400;
      font-size: 0.95rem;
    }
    
    .seller-btn-icon {
      background: transparent;
      border: none;
      color: #374151;
      font-size: 1.1rem;
      padding: 6px 10px;
      border-radius: 6px;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    
    .seller-btn-icon:hover {
      color: #2563eb;
      background: #f3f4f6;
    }
    
    .seller-dropdown-toggle {
      background: transparent;
      border: none;
      color: #374151;
      font-weight: 400;
      font-size: 0.95rem;
      padding: 6px 12px;
      border-radius: 6px;
    }
    
    .seller-dropdown-toggle:hover {
      color: #2563eb;
      background: #f3f4f6;
    }
    
    .seller-chat-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: red;
      color: white;
      font-size: 0.75rem;
      border-radius: 50%;
      padding: 2px 6px;
      font-weight: 700;
      min-width: 18px;
      height: 18px;
      text-align: center;
      line-height: 1.2;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4);
      border: 2px solid white;
    }
    
    /* Responsive */
    @media (max-width: 767px) {
      .seller-navbar {
        padding: 0.5rem 1rem;
      }
      
      .seller-navbar .container-fluid {
        flex-wrap: wrap;
        gap: 10px;
      }
      
      .seller-navbar-brand {
        font-size: 1.1rem;
        gap: 6px;
      }
      
      .seller-navbar-brand img {
        height: 35px;
      }
      
      .seller-btn-icon {
        font-size: 1.3rem;
        padding: 4px 8px;
      }
      
      .seller-dropdown-toggle {
        font-size: 0.85rem;
        padding: 4px 8px;
      }
      
      .seller-navbar .d-flex.align-items-center {
        width: 100%;
        justify-content: space-between;
        margin-left: 0 !important;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
      }
      
      .seller-chat-badge {
        font-size: 0.7rem;
        min-width: 16px;
        height: 16px;
        padding: 1px 5px;
        top: -3px;
        right: -3px;
      }
    }
    
    @media (min-width: 768px) and (max-width: 991px) {
      .seller-navbar {
        padding: 0.6rem 1.2rem;
      }
      
      .seller-navbar-brand {
        font-size: 1.3rem;
      }
      
      .seller-navbar-brand img {
        height: 40px;
      }
    }
</style>

<!-- SELLER NAVBAR -->
<nav class="navbar navbar-expand-lg seller-navbar">
  <div class="container-fluid">
    <a class="navbar-brand seller-navbar-brand" href="seller.php">
      <span>UKMart</span>
      <img src="uploads/UKMart_logo.png" alt="UKMart Logo">
      <span style="font-size: 0.9rem; color: var(--accent-orange); font-weight: 600;">Seller</span>
    </a>
    
    <!-- Mobile menu toggle -->
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#sellerNavbarContent" aria-controls="sellerNavbarContent" aria-expanded="false" aria-label="Toggle navigation" style="border: none; background: transparent; padding: 4px 8px;">
      <i class="bi bi-list" style="font-size: 1.5rem; color: #374151;"></i>
    </button>
    
    <!-- Collapsible content -->
    <div class="collapse navbar-collapse" id="sellerNavbarContent">
      <div class="d-flex align-items-center ms-auto position-relative">
        <div class="dropdown">
          <button class="btn seller-btn-icon seller-dropdown-toggle" type="button" id="sellerProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Hello, <?php echo htmlspecialchars($seller_name); ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sellerProfileDropdown">
            <li><a class="dropdown-item" href="seller.php">Dashboard</a></li>
            <li><a class="dropdown-item" href="add_product.php">Add Product</a></li>
            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
          </ul>
        </div>
        
        <button type="button" class="seller-btn-icon position-relative" id="openSellerChatPopup" style="font-size: 1.5rem; margin-left: 15px; cursor: pointer;" title="Chat Requests">
          <i class="bi bi-chat-dots"></i>
          <span class="seller-chat-badge" id="sellerChatBadge" style="display: none;">0</span>
        </button>
      </div>
    </div>
  </div>
</nav>
