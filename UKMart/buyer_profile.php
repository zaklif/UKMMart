<?php
session_start();
include('db_connect.php');

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch buyer information
$stmt = $conn->prepare("SELECT fld_user_name, fld_user_email FROM tbl_user_ukmart WHERE fld_user_id = ?");
$stmt->execute([$user_id]);
$buyer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$buyer) {
    header("Location: login.php");
    exit();
}

$buyer_name = $buyer['fld_user_name'];
$buyer_email = $buyer['fld_user_email'];

// Fetch buyer's chat history with sellers
$chat_stmt = $conn->prepare("
    SELECT DISTINCT 
        c.seller_id, 
        c.product_id, 
        s.fld_seller_name,
        s.fld_seller_id,
        p.fld_product_name,
        p.fld_product_price,
        p.fld_product_image,
        MAX(c.timestamp) as last_message
    FROM tbl_chat_ukmart c
    JOIN tbl_sellers_ukmart s ON c.seller_id = s.fld_seller_id
    JOIN tbl_products_ukmart p ON c.product_id = p.fld_product_id
    WHERE c.buyer_id = ?
    GROUP BY c.seller_id, c.product_id
    ORDER BY last_message DESC
");
$chat_stmt->execute([$user_id]);
$chat_history = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total messages sent by buyer
$message_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_chat_ukmart WHERE buyer_id = ?");
$message_count_stmt->execute([$user_id]);
$message_count = $message_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buyer profile image - check if user has a seller profile first
// Profile pictures are stored based on seller_id, not user_id
$profile_path = null;
$extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];

// Check if user has a seller profile
$seller_check = $conn->prepare("SELECT fld_seller_id FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
$seller_check->execute([$user_id]);
$seller_data = $seller_check->fetch(PDO::FETCH_ASSOC);

if ($seller_data) {
    // User has a seller profile, use seller_id to find profile picture
    $seller_id = $seller_data['fld_seller_id'];
    
    // Try to find the seller's profile picture using seller_id
    foreach ($extensions as $ext) {
        $test_path = "profile/{$seller_id}.{$ext}";
        if (file_exists($test_path)) {
            $profile_path = $test_path;
            break;
        }
    }
}

// If no profile picture found (either no seller profile or no picture file), use default
if (!$profile_path) {
    // Try multiple default locations
    $default_paths = [
        "profile/default_user.png",
        "images/default.png",
        "profile/default.png"
    ];
    
    foreach ($default_paths as $default_path) {
        if (file_exists($default_path)) {
            $profile_path = $default_path;
            break;
        }
    }
    
    // If still no default found, use a placeholder
    if (!$profile_path) {
        $profile_path = "images/default.png"; // Final fallback
    }
}

// Function to get seller profile picture by seller_id
function getSellerProfilePicture($seller_id) {
    $extensions = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
    
    // Try to find the seller's profile picture
    foreach ($extensions as $ext) {
        $test_path = "profile/{$seller_id}.{$ext}";
        if (file_exists($test_path)) {
            return $test_path;
        }
    }
    
    // If no profile picture found, try default paths
    $default_paths = [
        "profile/default_user.png",
        "images/default.png",
        "profile/default.png"
    ];
    
    foreach ($default_paths as $default_path) {
        if (file_exists($default_path)) {
            return $default_path;
        }
    }
    
    // Final fallback
    return "images/default.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buyer Profile | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
.bg-primary { background-color: #0d6efd !important; color: white; font-weight:bold; font-size:18px; padding:10px 15px; }
.profile-card { background: #fff; border-radius: 15px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.profile-pic { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; }
.btn-logout { background: linear-gradient(135deg, #ff4d4f, #b30000); color: white; border: none; border-radius: 12px; padding: 10px 25px; font-weight: 500; text-decoration: none; transition: 0.3s; }
.btn-logout:hover { background: linear-gradient(135deg, #ff6b6b, #d32f2f); color: white; }
.chat-card { background: #fff; border-radius: 15px; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); display: flex; align-items: center; margin-bottom: 15px; transition: 0.3s; }
.chat-card:hover { box-shadow: 0 6px 15px rgba(0,0,0,0.12); transform: translateY(-2px); }
.chat-card img { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; margin-right: 15px; }
.seller-profile-pic { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 10px; vertical-align: middle; }
.browse-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 10px; transition: background 0.3s; text-decoration: none; display: inline-block; }
.browse-btn:hover { background: #218838; color: white; }
.section-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 15px; color: #333; }
.empty-state { text-align: center; padding: 40px; color: #6c757d; background: #fff; border-radius: 15px; }
.empty-state i { font-size: 3rem; margin-bottom: 15px; }
.stat-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 10px; }
.stat-box .number { font-size: 1.8rem; font-weight: bold; color: #0d6efd; }
.stat-box .label { font-size: 0.9rem; color: #6c757d; }
.seller-info { display: flex; align-items: center; margin-bottom: 10px; }

/* Responsive Design */
@media(max-width: 992px) {
  .profile-card {
    margin-bottom: 20px;
  }
  .chat-card {
    flex-direction: column;
    text-align: center;
  }
  .chat-card img {
    margin-right: 0;
    margin-bottom: 15px;
  }
  .seller-info {
    justify-content: center;
  }
}

@media(max-width: 768px) {
  .profile-pic {
    width: 100px;
    height: 100px;
  }
  .seller-profile-pic {
    width: 40px;
    height: 40px;
  }
  .stat-box .number {
    font-size: 1.5rem;
  }
  .section-title {
    font-size: 1.1rem;
  }
  .chat-card {
    padding: 12px;
  }
  .chat-card img {
    width: 60px;
    height: 60px;
  }
  .empty-state {
    padding: 30px 20px;
  }
  .empty-state i {
    font-size: 2.5rem;
  }
  .back-btn {
    font-size: 1.3rem !important;
  }
}

@media(max-width: 576px) {
  .profile-card {
    padding: 20px;
  }
  .profile-pic {
    width: 80px;
    height: 80px;
  }
  .seller-profile-pic {
    width: 35px;
    height: 35px;
  }
  .stat-box {
    padding: 12px;
  }
  .stat-box .number {
    font-size: 1.3rem;
  }
  .btn-logout {
    padding: 8px 20px;
    font-size: 0.85rem;
  }
  .chat-card {
    padding: 10px;
  }
  .chat-card img {
    width: 50px;
    height: 50px;
  }
  .browse-btn {
    padding: 8px 16px;
    font-size: 0.9rem;
  }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<?php include('nav_bar.php'); ?>

<!-- Back Button -->
<div class="container my-3">
  <a href="buyer.php" class="back-btn" style="font-size: 1.5rem; color: #2563eb; padding: 10px; display: inline-block; margin-bottom: 15px; text-decoration: none; transition: all 0.3s ease;" title="Back to Home">
    <i class="bi bi-arrow-left-circle-fill"></i>
  </a>
</div>

<div class="container my-3">
    <div class="row">
        <!-- LEFT SIDE: Profile -->
        <div class="col-md-4">
            <div class="profile-card text-center mb-4">
                <img src="<?= $profile_path ?>" alt="Profile Picture" class="profile-pic mb-3">
                <h5 class="fw-bold"><?= htmlspecialchars($buyer_name); ?></h5>
                <p class="text-muted small"><?= htmlspecialchars($buyer_email); ?></p>
                
                <div class="my-3">
                    <span class="badge bg-info mb-3">Buyer Account</span>
                    
                    <br>
                    
                    <a href="logout.php" class="btn-logout w-100" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>

                <div class="mt-4">
                    <div class="stat-box">
                        <div class="number"><?= count($chat_history) ?></div>
                        <div class="label"><i class="bi bi-chat-dots"></i> Active Conversations</div>
                    </div>
                    <div class="stat-box">
                        <div class="number"><?= $message_count ?></div>
                        <div class="label"><i class="bi bi-envelope"></i> Total Messages Sent</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE: Chat History -->
        <div class="col-md-8">
            <!-- Chat History Section -->
            <div class="mb-4">
                <h5 class="section-title">
                    <i class="bi bi-chat-left-text"></i> Your Conversations
                </h5>

                <?php if (empty($chat_history)): ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-square-dots"></i>
                        <p class="fw-bold">No conversations yet</p>
                        <p class="small">Start chatting with sellers about products you're interested in!</p>
                        <a href="buyer.php" class="browse-btn mt-2">
                            <i class="bi bi-shop"></i> Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($chat_history as $chat): 
                        $file = $chat['fld_product_image'];
                        $extensions = ['jpg','jpeg','png'];
                        $product_img_path = "images/default.png";
                        foreach ($extensions as $ext) {
                            if (file_exists("images/" . pathinfo($file, PATHINFO_FILENAME) . "." . $ext)) {
                                $product_img_path = "images/" . pathinfo($file, PATHINFO_FILENAME) . "." . $ext;
                                break;
                            }
                        }
                        
                        // Get seller profile picture using seller_id
                        $seller_profile_pic = getSellerProfilePicture($chat['fld_seller_id']);
                        
                        // Get last message preview
                        $last_msg_stmt = $conn->prepare("
                            SELECT message, timestamp 
                            FROM tbl_chat_ukmart 
                            WHERE buyer_id = ? AND seller_id = ? AND product_id = ?
                            ORDER BY timestamp DESC LIMIT 1
                        ");
                        $last_msg_stmt->execute([$user_id, $chat['seller_id'], $chat['product_id']]);
                        $last_msg = $last_msg_stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="chat-card">
                        <img src="<?= $product_img_path ?>" alt="Product Image">
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($chat['fld_product_name']) ?></h6>
                            <div class="seller-info">
                                <img src="<?= $seller_profile_pic ?>" alt="Seller Profile" class="seller-profile-pic">
                                <p class="text-muted mb-0 small">
                                    <i class="bi bi-shop"></i> <strong><?= htmlspecialchars($chat['fld_seller_name']) ?></strong>
                                </p>
                            </div>
                            <p class="text-primary mb-1 fw-bold">RM<?= number_format($chat['fld_product_price'], 2) ?></p>
                            
                            <?php if ($last_msg): ?>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-clock"></i> 
                                    <?= date('M d, Y h:i A', strtotime($last_msg['timestamp'])) ?>
                                </p>
                            <?php endif; ?>
                            
                            <a href="chatbuyer.php?seller_id=<?= $chat['seller_id'] ?>&product_id=<?= $chat['product_id'] ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-chat"></i> Continue Chat
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Info Section -->
            <div class="mb-4">
                <div class="profile-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Quick Tips</h6>
                    <ul class="small text-muted">
                        <li class="mb-2">Browse available products and chat with sellers directly</li>
                        <li class="mb-2">Check product availability before making inquiries</li>
                        <li class="mb-2">All your conversations are saved here for easy access</li>
                        <li>Contact sellers for product details, pricing, and arrangements</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>