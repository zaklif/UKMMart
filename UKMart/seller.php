<?php
session_start();
include('db_connect.php');

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch seller_id for this user
$stmt = $conn->prepare("SELECT fld_seller_id, fld_seller_name, fld_profile_pic  FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    header("Location: create_seller_profile.php");
    exit();
}

$seller_id = $seller['fld_seller_id'];
$seller_name = $seller['fld_seller_name'];
$_SESSION['seller_id'] = $seller_id;


// Fetch products
$products_stmt = $conn->prepare("SELECT * FROM tbl_products_ukmart WHERE fld_seller_id = ?");
$products_stmt->execute([$seller_id]);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch buyer chat requests (only buyers who sent messages)
$chat_stmt = $conn->prepare("
    SELECT DISTINCT c.buyer_id, c.product_id, u.fld_user_name
    FROM tbl_chat_ukmart c
    JOIN tbl_user_ukmart u ON c.buyer_id = u.fld_user_id
    WHERE c.seller_id = ?
    ORDER BY c.timestamp DESC
");
$chat_stmt->execute([$seller_id]);
$chat_requests = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Example feedback and profit
$feedback = ['5' => 10, '4' => 5, '3' => 2];
$profit = 50;

// Seller profile image
$profile_path = (!empty($seller['fld_profile_pic']) && file_exists("profile/" . $seller['fld_profile_pic']))
    ? "profile/" . $seller['fld_profile_pic']
    : "profile/default_user.png";

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Page | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
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
  --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.25);
}

body { 
  background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
  font-family: 'Poppins', sans-serif; 
  color: var(--text-dark);
}
.bg-primary { 
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; 
  color: white; 
  font-weight: 700;
  font-size: 20px; 
  padding: 15px 25px; 
  box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
  text-transform: uppercase;
  letter-spacing: 1px;
}
.profile-card { 
  background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); 
  border-radius: 20px; 
  padding: 30px; 
  box-shadow: var(--shadow-lg);
  border: 2px solid var(--primary-blue-light);
  position: relative;
  overflow: hidden;
}
.profile-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.profile-pic { 
  width: 140px; 
  height: 140px; 
  border-radius: 50%; 
  object-fit: cover;
  border: 5px solid var(--primary-blue);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
  transition: transform 0.3s ease;
}
.profile-pic:hover {
  transform: scale(1.05);
}
.btn-logout { 
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
  color: white; 
  border: none; 
  border-radius: 12px; 
  padding: 12px 25px; 
  font-weight: 700; 
  text-decoration: none; 
  transition: all 0.3s ease;
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-size: 0.9rem;
}
.btn-logout:hover { 
  transform: translateY(-3px);
  box-shadow: 0 10px 30px rgba(239, 68, 68, 0.5);
  color: white;
}
.product-card { 
  background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); 
  border-radius: 18px; 
  padding: 25px; 
  box-shadow: var(--shadow-md);
  border: 2px solid var(--border-color);
  display: flex; 
  align-items: flex-start;
  transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
  position: relative;
  overflow: hidden;
}
.product-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  transform: scaleX(0);
  transition: transform 0.4s ease;
}
.product-card:hover::before {
  transform: scaleX(1);
}
.product-card:hover {
  box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
  border-color: var(--primary-blue);
  transform: translateY(-6px) scale(1.01);
}
.product-card img { 
  width: 180px; 
  height: 180px; 
  border-radius: 14px; 
  object-fit: cover; 
  margin-right: 20px;
  border: 3px solid var(--primary-blue-light);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
  transition: transform 0.4s ease;
}
.product-card:hover img {
  transform: scale(1.05) rotate(2deg);
}
.status-available { 
  background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
  color: white; 
  border-radius: 8px; 
  padding: 6px 14px; 
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.status-sold { 
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
  color: white; 
  border-radius: 8px; 
  padding: 6px 14px; 
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 3px 10px rgba(239, 68, 68, 0.3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.add-btn { 
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
  color: white; 
  border: none; 
  padding: 12px 25px; 
  border-radius: 12px; 
  transition: all 0.3s ease;
  font-weight: 700;
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-size: 0.95rem;
}
.add-btn:hover { 
  transform: translateY(-3px);
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
}
.table-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
  color: white;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.table-bordered {
  border: 2px solid var(--primary-blue-light) !important;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: var(--shadow-md);
}
.table-bordered td {
  border-color: var(--border-color) !important;
  padding: 12px !important;
}

/* Responsive Design */
@media(max-width: 992px) {
  .profile-card {
    margin-bottom: 20px;
  }
  .product-card {
    flex-direction: column;
    text-align: center;
  }
  .product-card img {
    margin-right: 0;
    margin-bottom: 15px;
    width: 100%;
    max-width: 200px;
    height: auto;
  }
}

@media(max-width: 768px) {
  .bg-primary {
    font-size: 16px;
    padding: 12px 20px;
  }
  .profile-pic {
    width: 120px;
    height: 120px;
  }
  .profile-card {
    padding: 20px;
  }
  .product-card {
    padding: 20px;
    flex-direction: column;
  }
  .product-card img {
    width: 100%;
    max-width: 180px;
    height: 180px;
    margin-right: 0;
    margin-bottom: 15px;
  }
  .btn-logout {
    padding: 10px 20px;
    font-size: 0.85rem;
  }
  .add-btn {
    padding: 10px 20px;
    font-size: 0.9rem;
    width: 100%;
    margin-top: 10px;
  }
  .table-bordered {
    font-size: 0.9rem;
  }
  .table-bordered td {
    padding: 8px !important;
  }
}

@media(max-width: 576px) {
  .bg-primary {
    font-size: 14px;
    padding: 10px 15px;
  }
  .profile-card {
    padding: 15px;
  }
  .profile-pic {
    width: 100px;
    height: 100px;
  }
  .product-card {
    padding: 15px;
  }
  .product-card img {
    width: 100%;
    max-width: 150px;
    height: 150px;
  }
  .status-available,
  .status-sold {
    font-size: 10px;
    padding: 4px 10px;
  }
  .add-btn {
    padding: 8px 16px;
    font-size: 0.85rem;
  }
  .table-bordered {
    font-size: 0.85rem;
  }
  .table-bordered td {
    padding: 6px !important;
  }
}

/* ===== Seller Chat Popup Styles ===== */
.chat-popup-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9998;
    animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeIn {
    from { opacity: 0; backdrop-filter: blur(0px); }
    to { opacity: 1; backdrop-filter: blur(4px); }
}

@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

.chat-popup-container {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 420px;
    height: 650px;
    max-height: calc(100vh - 40px);
    background: var(--bg-white);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    z-index: 9999;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    flex-direction: column;
}

.chat-popup-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
}

.chat-popup-header h6 {
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-close-btn, .chat-back-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: white;
    font-size: 1.4rem;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.chat-close-btn:hover, .chat-back-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}

.chat-list-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    background: linear-gradient(to bottom, #fafbfc 0%, #f8fafc 100%);
    scrollbar-width: thin;
    scrollbar-color: rgba(37, 99, 235, 0.3) transparent;
}

.chat-list-container::-webkit-scrollbar {
    width: 6px;
}

.chat-list-container::-webkit-scrollbar-thumb {
    background: rgba(37, 99, 235, 0.3);
    border-radius: 10px;
}

.chat-conversation-item {
    padding: 16px 24px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    background: var(--bg-white);
}

.chat-conversation-item:hover {
    background: #f8fafc;
}

.chat-avatar {
    width: 50px;
    height: 50px;
    margin-right: 12px;
    flex-shrink: 0;
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-name {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.chat-last-message {
    color: var(--text-gray);
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    flex-shrink: 0;
}

.chat-time {
    font-size: 0.75rem;
    color: var(--text-gray);
}

.chat-unread-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

.no-chats-message {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-gray);
}

.no-chats-message i {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .chat-popup-container {
        width: 100%;
        height: 100%;
        bottom: 0;
        right: 0;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .chat-popup-header {
        border-radius: 0;
        padding: 18px 20px;
    }
}
</style>
</head>
<body>
<!-- SELLER NAVBAR -->
<?php include('seller_nav_bar.php'); ?>

<!-- Header -->


<!-- Back Button -->
<div class="container my-3">
  <a href="buyer.php" class="back-btn" style="font-size: 1.5rem; color: #2563eb; padding: 10px; display: inline-block; margin-bottom: 15px; text-decoration: none; transition: all 0.3s ease;" title="Back to Home">
    <i class="bi bi-arrow-left-circle-fill"></i> Back to Home
  </a>
</div>

<div class="container my-3">
    <div class="row">
        <!-- LEFT SIDE: Profile -->
<div class="col-md-4">
    <div class="profile-card text-center mb-4">
        <!-- Profile Picture -->
        <img src="<?= $profile_path ?>" alt="Profile Picture" class="profile-pic mb-3">

        <!-- Seller Name -->
        <h5 class="fw-bold"><?= htmlspecialchars($seller_name); ?></h5>

        <!-- Account Details -->
        <div class="my-3">
            <p class="mb-1 fw-semibold">Account Details</p>
            <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>

        <!-- Feedback Table -->
        <?php
        // Fetch real feedback data from database
        $feedback_counts = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
        try {
            $feedback_stmt = $conn->prepare("SELECT rating, COUNT(*) as count FROM tbl_ratings_reviews WHERE seller_id = ? GROUP BY rating");
            $feedback_stmt->execute([$seller_id]);
            $feedback_data = $feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($feedback_data as $row) {
                $rating = (string)$row['rating'];
                if (isset($feedback_counts[$rating])) {
                    $feedback_counts[$rating] = (int)$row['count'];
                }
            }
        } catch (Exception $e) {
            // Keep default zeros if query fails
        }
        ?>
        <table class="table table-bordered feedback-table mt-3">
            <thead class="table-primary">
                <tr><th>Rating</th><th>Count</th></tr>
            </thead>
            <tbody>
                <?php for ($i=5; $i>=1; $i--): ?>
                <tr>
                    <td><?= str_repeat('⭐', $i) ?></td>
                    <td><?= $feedback_counts[$i]; ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- Buyer Reviews -->
        <div class="mt-4 text-start buyer-reviews" style="max-height:300px; overflow-y:auto;">
            <h6>Buyer Reviews</h6>
            <?php
            $reviews_stmt = $conn->prepare("
                SELECT r.rating, r.review, u.fld_user_name, r.created_at
                FROM tbl_ratings_reviews r
                JOIN tbl_user_ukmart u ON r.buyer_id = u.fld_user_id
                WHERE r.seller_id = ?
                ORDER BY r.created_at DESC
            ");
            $reviews_stmt->execute([$seller_id]);
            $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($reviews):
                foreach ($reviews as $rev):
            ?>
                <div class="card mb-2 p-2">
                    <strong><?= htmlspecialchars($rev['fld_user_name']); ?></strong>
                    <span class="text-warning"><?= str_repeat('⭐', max(1, min(5, $rev['rating']))); ?></span>
                    <p class="mb-0"><?= htmlspecialchars($rev['review']); ?></p>
                    <small class="text-muted"><?= $rev['created_at']; ?></small>
                </div>
            <?php
                endforeach;
            else:
                echo "<p class='text-muted'>No reviews yet.</p>";
            endif;
            ?>
        </div>
    </div>
</div>

        <!-- RIGHT SIDE: Products + Chat Requests -->

        
        <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Your Products</h5>
    <a href="add_product.php?id=<?= $seller_id ?>" class="btn add-btn">
        <i class="bi bi-plus-circle"></i> Add New Product
    </a>
</div>


            <?php foreach ($products as $product): 
    $quantity = isset($product['fld_product_quantity']) ? (int)$product['fld_product_quantity'] : 0;
    $status = $quantity > 0 ? 'Available' : 'Sold';

    $product_img_path = (!empty($product['fld_product_image']) 
        && file_exists("images/" . $product['fld_product_image']))
        ? "images/" . $product['fld_product_image']
        : "images/default.png";

    // Fetch chat requests for this product
    $cr_stmt = $conn->prepare("
        SELECT c.buyer_id, u.fld_user_name
        FROM tbl_chat_ukmart c
        JOIN tbl_user_ukmart u ON c.buyer_id = u.fld_user_id
        WHERE c.seller_id = ? AND c.product_id = ?
        GROUP BY c.buyer_id
    ");
    $cr_stmt->execute([$seller_id, $product['fld_product_id']]);
    $product_chats = $cr_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unique ID for collapse
    $collapseId = "chatCollapse" . $product['fld_product_id'];
?>
<div class="product-card mb-4">
    <img src="<?= $product_img_path ?>" alt="Product Image">
    <div>
        <h6 class="fw-bold mb-1"><?= htmlspecialchars($product['fld_product_name']) ?></h6>
        <p class="text-muted mb-1">RM<?= number_format($product['fld_product_price'], 2) ?></p>
        <p class="small text-secondary">
<?= isset($product['fld_product_description']) ? htmlspecialchars($product['fld_product_description']) : '' ?>
</p>

        <?php if ($status == 'Available'): ?>
            <span class="status-available">Available</span>
        <?php else: ?>
            <span class="status-sold">Sold</span>
        <?php endif; ?>

        <!-- Collapsible Chat Requests -->
        <?php if ($product_chats): ?>
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                    View Chat Requests (<?= count($product_chats) ?>)
                </button>
                <div class="collapse mt-2" id="<?= $collapseId ?>">
                    <?php foreach ($product_chats as $pc): ?>
                        <a href="chatseller.php?buyer_id=<?= $pc['buyer_id'] ?>&product_id=<?= $product['fld_product_id'] ?>" 
                           class="btn btn-sm btn-primary mb-1 d-block">
                           <?= htmlspecialchars($pc['fld_user_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
<?php endforeach; ?>



        </div>
    </div>
</div>

<!-- SELLER CHAT POPUP OVERLAY -->
<div class="chat-popup-overlay" id="sellerChatPopupOverlay"></div>

<!-- SELLER CHAT POPUP CONTAINER -->
<div class="chat-popup-container" id="sellerChatPopupContainer">
    <!-- PRODUCT LIST VIEW -->
    <div id="sellerChatListView">
        <div class="chat-popup-header">
            <h6><i class="bi bi-chat-dots me-2"></i>Chat Requests by Product</h6>
            <button class="chat-close-btn" id="closeSellerChatPopup">
                <i class="bi bi-x"></i>
            </button>
        </div>
        
        <div class="chat-list-container" id="sellerChatListContainer">
            <div class="no-chats-message">
                <i class="bi bi-chat-dots"></i>
                <p>Loading chat requests...</p>
            </div>
        </div>
    </div>

    <!-- BUYER LIST VIEW (for a specific product) -->
    <div id="sellerBuyerListView" style="display: none;">
        <div class="chat-popup-header">
            <button class="chat-back-btn" id="backToProductList">
                <i class="bi bi-arrow-left"></i>
            </button>
            <h6 id="productChatHeader">Product Chats</h6>
            <button class="chat-close-btn" id="closeSellerChatView">
                <i class="bi bi-x"></i>
            </button>
        </div>
        
        <div class="chat-list-container" id="sellerBuyerListContainer">
            <div class="no-chats-message">
                <i class="bi bi-chat-dots"></i>
                <p>Loading buyers...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Seller Chat Popup Functionality
const openSellerChatBtn = document.getElementById('openSellerChatPopup');
if (openSellerChatBtn) {
    const closeSellerChatBtn = document.getElementById('closeSellerChatPopup');
    const closeSellerChatViewBtn = document.getElementById('closeSellerChatView');
    const backToProductListBtn = document.getElementById('backToProductList');
    const sellerChatOverlay = document.getElementById('sellerChatPopupOverlay');
    const sellerChatContainer = document.getElementById('sellerChatPopupContainer');
    const sellerChatListContainer = document.getElementById('sellerChatListContainer');
    const sellerBuyerListContainer = document.getElementById('sellerBuyerListContainer');
    
    let currentProduct = null;
    
    // Open chat popup
    openSellerChatBtn.addEventListener('click', function() {
        sellerChatOverlay.style.display = 'block';
        sellerChatContainer.style.display = 'flex';
        document.getElementById('sellerChatListView').style.display = 'block';
        document.getElementById('sellerBuyerListView').style.display = 'none';
        loadSellerChatProducts();
        updateSellerChatBadge();
    });
    
    // Close chat popup
    function closeSellerChatPopup() {
        sellerChatOverlay.style.display = 'none';
        sellerChatContainer.style.display = 'none';
        currentProduct = null;
    }
    
    if (closeSellerChatBtn) closeSellerChatBtn.addEventListener('click', closeSellerChatPopup);
    if (closeSellerChatViewBtn) closeSellerChatViewBtn.addEventListener('click', closeSellerChatPopup);
    if (sellerChatOverlay) sellerChatOverlay.addEventListener('click', closeSellerChatPopup);
    
    // Back to product list
    if (backToProductListBtn) {
        backToProductListBtn.addEventListener('click', function() {
            document.getElementById('sellerBuyerListView').style.display = 'none';
            document.getElementById('sellerChatListView').style.display = 'block';
            currentProduct = null;
            loadSellerChatProducts();
        });
    }
    
    // Load products with chat requests
    function loadSellerChatProducts() {
        fetch('get_seller_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products.length > 0) {
                    displayProducts(data.products);
                    updateSellerChatBadge();
                } else {
                    if (sellerChatListContainer) {
                        sellerChatListContainer.innerHTML = `
                            <div class="no-chats-message">
                                <i class="bi bi-chat-dots"></i>
                                <p>No chat requests yet</p>
                                <small class="text-muted">Buyers will appear here when they message you!</small>
                            </div>
                        `;
                    }
                    updateSellerChatBadge();
                }
            })
            .catch(error => {
                console.error('Error loading products:', error);
                if (sellerChatListContainer) {
                    sellerChatListContainer.innerHTML = `
                        <div class="no-chats-message">
                            <i class="bi bi-exclamation-circle"></i>
                            <p>Failed to load chat requests</p>
                        </div>
                    `;
                }
            });
    }
    
    // Display products
    function displayProducts(products) {
        let html = '';
        
        products.forEach(product => {
            const unreadCount = parseInt(product.unread_count) || 0;
            const buyerCount = parseInt(product.buyer_count) || 0;
            const unreadBadge = unreadCount > 0 
                ? `<span class="chat-unread-badge">${unreadCount > 99 ? '99+' : unreadCount}</span>` 
                : '';
            
            html += `
                <div class="chat-conversation-item" onclick="openProductBuyers(${product.product_id}, '${escapeHtml(product.product_name)}')">
                    <div class="chat-avatar">
                        <img src="${product.product_image}" alt="${escapeHtml(product.product_name)}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="chat-info" style="flex: 1;">
                        <div class="chat-name">${escapeHtml(product.product_name)}</div>
                        <div class="chat-last-message">
                            ${buyerCount} buyer${buyerCount !== 1 ? 's' : ''} • ${escapeHtml(product.last_message)}
                        </div>
                    </div>
                    <div class="chat-meta">
                        <div class="chat-time">${formatTime(product.last_message_time)}</div>
                        ${unreadBadge}
                    </div>
                </div>
            `;
        });
        
        if (sellerChatListContainer) sellerChatListContainer.innerHTML = html;
    }
    
    // Open buyers for a product
    window.openProductBuyers = function(productId, productName) {
        currentProduct = { productId, productName };
        document.getElementById('sellerChatListView').style.display = 'none';
        document.getElementById('sellerBuyerListView').style.display = 'block';
        document.getElementById('productChatHeader').textContent = productName;
        
        // Load buyers for this product
        fetch('get_seller_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.products.find(p => p.product_id == productId);
                    if (product && product.buyers) {
                        displayBuyers(product.buyers, productId);
                    }
                }
            })
            .catch(error => console.error('Error loading buyers:', error));
    };
    
    // Display buyers
    function displayBuyers(buyers, productId) {
        let html = '';
        
        if (buyers.length === 0) {
            html = `
                <div class="no-chats-message">
                    <i class="bi bi-chat-dots"></i>
                    <p>No buyers for this product</p>
                </div>
            `;
        } else {
            buyers.forEach(buyer => {
                const unreadCount = parseInt(buyer.unread_count) || 0;
                const unreadBadge = unreadCount > 0 
                    ? `<span class="chat-unread-badge">${unreadCount > 99 ? '99+' : unreadCount}</span>` 
                    : '';
                
                html += `
                    <div class="chat-conversation-item" onclick="window.location.href='chatseller.php?buyer_id=${buyer.buyer_id}&product_id=${productId}'">
                        <div class="chat-avatar">
                            <span style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.2rem;">
                                ${escapeHtml(buyer.buyer_name).charAt(0).toUpperCase()}
                            </span>
                        </div>
                        <div class="chat-info" style="flex: 1;">
                            <div class="chat-name">${escapeHtml(buyer.buyer_name)}</div>
                            <div class="chat-last-message">${escapeHtml(buyer.last_message)}</div>
                        </div>
                        <div class="chat-meta">
                            <div class="chat-time">${formatTime(buyer.last_message_time)}</div>
                            ${unreadBadge}
                        </div>
                    </div>
                `;
            });
        }
        
        if (sellerBuyerListContainer) sellerBuyerListContainer.innerHTML = html;
    }
    
    // Update seller chat badge
    function updateSellerChatBadge() {
        fetch('get_seller_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const totalUnread = data.products.reduce((sum, p) => sum + (parseInt(p.unread_count) || 0), 0);
                    const badge = document.getElementById('sellerChatBadge');
                    if (badge) {
                        if (totalUnread > 0) {
                            badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                            badge.textContent = '0';
                        }
                    }
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }
    
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Update badge periodically
    setInterval(updateSellerChatBadge, 3000);
    
    // Update badge on page load
    updateSellerChatBadge();
}
</script>

</body>
</html>
