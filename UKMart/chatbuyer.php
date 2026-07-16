<?php
session_start();
include_once 'database.php'; // defines $conn
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create ratings_reviews table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS tbl_ratings_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_product (product_id),
    UNIQUE KEY unique_review (buyer_id, seller_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Make sure buyer is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in as buyer to chat.");
}

$buyer_id = $_SESSION['user_id'];
$seller_id  = isset($_GET['seller_id']) ? $_GET['seller_id'] : (isset($_POST['seller_id']) ? $_POST['seller_id'] : null);
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_POST['product_id']) ? $_POST['product_id'] : null);

if (!$seller_id || !$product_id) {
    die("Invalid chat request.");
}

// Check if coming from cart and get cart quantity
$from_cart = isset($_GET['from_cart']) && $_GET['from_cart'] == '1';
$cart_quantity = isset($_GET['cart_quantity']) ? (int)$_GET['cart_quantity'] : 0;

// SEND RECEIPT IMAGE
if (isset($_POST['send_receipt'])) {
    $file = $_FILES['receipt']['name'];
    if (!empty($file)) {
        if (!is_dir('uploads')) mkdir('uploads');
        $target = "uploads/" . basename($file);
        move_uploaded_file($_FILES['receipt']['tmp_name'], $target);

        // Get current status
        $status_stmt = $conn->prepare("SELECT COALESCE(status, 'pending') as status FROM tbl_chat_ukmart 
                                       WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                       ORDER BY timestamp DESC LIMIT 1");
        $status_stmt->execute([$buyer_id, $seller_id, $product_id]);
        $current_status = $status_stmt->fetchColumn();
        if (!$current_status) $current_status = 'pending';

        $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
            (sender_type, sender_name, message, message_type, file_path, buyer_id, seller_id, product_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['buyer', 'Buyer', 'Payment receipt uploaded.', 'receipt', $target, $buyer_id, $seller_id, $product_id, $current_status]);
    }
    
    // Check if it's an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        echo "OK";
        exit;
    } else {
        header("Location: chatbuyer.php?product_id=$product_id&seller_id=$seller_id");
        exit;
    }
}

// FETCH MESSAGES FOR THIS CHAT
$stmt = $conn->prepare("SELECT *, COALESCE(status, 'pending') as status FROM tbl_chat_ukmart 
    WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
    ORDER BY timestamp ASC");
$stmt->execute([$buyer_id, $seller_id, $product_id]);
$messages = $stmt->fetchAll();

// GET CURRENT ORDER STATUS
$current_status = 'pending';
if (!empty($messages)) {
    $latest_message = end($messages);
    $current_status = isset($latest_message['status']) ? $latest_message['status'] : 'pending';

}

// HANDLE REVIEW SUBMISSION (Only allow when status is 'completed')
if (isset($_POST['submit_review']) && $current_status === 'completed') {
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review']);

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        echo "Invalid rating. Please select a rating between 1 and 5.";
        exit;
    }

    // Check if review already exists
    $check_review = $conn->prepare("SELECT * FROM tbl_ratings_reviews WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
    $check_review->execute([$buyer_id, $seller_id, $product_id]);
    if ($check_review->fetch()) {
        echo "You have already submitted a review for this order.";
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO tbl_ratings_reviews (buyer_id, seller_id, product_id, rating, review) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$buyer_id, $seller_id, $product_id, $rating, $review]);

        if ($result) {
            // Insert status message in chat to show review was submitted
            $review_text = !empty($review) ? " and left a review: \"" . substr($review, 0, 50) . (strlen($review) > 50 ? '...' : '') . "\"" : "";
            $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['buyer', 'Buyer', 'Buyer rated ' . $rating . '⭐' . $review_text, 'status_update', $buyer_id, $seller_id, $product_id, $current_status]);

            // Check if it's an AJAX request
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                echo "OK";
                exit;
            } else {
                header("Location: chatbuyer.php?product_id=$product_id&seller_id=$seller_id");
                exit;
            }
        } else {
            echo "Failed to save review to database.";
            exit;
        }
    } catch (Exception $e) {
        // Check if it's a duplicate key error
        if (strpos($e->getMessage(), 'unique_review') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "You have already submitted a review for this order.";
        } else {
            echo "Database error: " . $e->getMessage();
        }
        exit;
    }
} elseif (isset($_POST['submit_review'])) {
    echo "Reviews can only be submitted for completed orders. Current status: $current_status";
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Buyer Chat | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
  font-family: 'Satoshi', Georgia, serif;
  background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
  color: var(--text-dark);
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 70px);
  width: 100%;
  max-width: 1920px;
  margin: 0 auto;
}

.card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border-radius: 20px;
  border: 2px solid var(--border-color);
  background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.card-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  padding: 20px 24px;
  border-radius: 20px 20px 0 0;
  box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
}

.status-badge {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  box-shadow: var(--shadow-sm);
}

.status-pending { 
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: #000; 
}
.status-paid { 
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff; 
}
.status-shipped { 
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: #fff; 
}
.status-completed { 
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
  color: #fff; 
}

.chat-box {
  flex: 1;
  background: var(--bg-white);
  padding: 30px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(37, 99, 235, 0.3) transparent;
}

.chat-box::-webkit-scrollbar {
  width: 6px;
}

.chat-box::-webkit-scrollbar-track {
  background: transparent;
}

.chat-box::-webkit-scrollbar-thumb {
  background: rgba(37, 99, 235, 0.3);
  border-radius: 10px;
}

.chat-box::-webkit-scrollbar-thumb:hover {
  background: rgba(37, 99, 235, 0.5);
}

.chat-bubble {
  padding: 14px 18px;
  max-width: 55%;
  border-radius: 18px;
  font-size: 1rem;
  line-height: 1.6;
  box-shadow: var(--shadow-md);
  margin-bottom: 12px;
  word-wrap: break-word;
  overflow-wrap: break-word;
  position: relative;
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.buyer-msg {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 18px 18px 4px 18px;
  margin-left: auto;
}

.seller-msg {
  background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
  color: #fff;
  border-radius: 18px 18px 18px 4px;
  margin-right: auto;
}

.status-msg {
  background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
  color: #155724;
  border: 2px solid #c3e6cb;
  text-align: center;
  font-weight: 600;
  max-width: 85%;
  margin: 12px auto;
  padding: 12px 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(21, 87, 36, 0.15);
}

.timestamp {
  font-size: 0.78rem;
  color: #94a3b8;
  margin-top: 4px;
  text-align: right;
}

.card-footer {
  background: linear-gradient(to top, #f8fafc 0%, #ffffff 100%);
  border-top: 2px solid var(--primary-blue-light);
  padding: 20px 24px;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border: none;
  border-radius: 12px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-success:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.btn-info {
  background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-info:hover {
  background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.form-control {
  border: 2px solid var(--border-color);
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.form-control:focus {
  border-color: var(--primary-blue);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.alert {
  border-radius: 12px;
  border: none;
  box-shadow: var(--shadow-sm);
}

@media (max-width: 1199px) {
  .chat-bubble { 
    max-width: 70%; 
    font-size: 0.95rem; 
    padding: 12px 16px;
  }
}

@media (max-width: 768px) {
  .chat-container {
    height: calc(100vh - 60px);
  }
  
  .card {
    border-radius: 0;
  }
  
  .card-header {
    border-radius: 0;
    padding: 16px 20px;
  }
  
  .chat-box {
    padding: 15px;
  }
  
  .chat-bubble {
    max-width: 85%;
    font-size: 0.9rem;
    padding: 12px 14px;
    margin-bottom: 10px;
  }
  
  .status-msg {
    max-width: 95%;
    font-size: 0.85rem;
    padding: 10px 16px;
  }
  
  .card-footer {
    padding: 16px 20px;
  }
  
  .timestamp {
    font-size: 0.7rem;
  }
}

@media (max-width: 576px) {
  .chat-bubble {
    max-width: 90%;
    font-size: 0.85rem;
    padding: 10px 12px;
  }
  
  .status-msg {
    max-width: 98%;
    font-size: 0.8rem;
    padding: 8px 12px;
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
    <i class="bi bi-arrow-left-circle-fill"></i> Back to Home
  </a>
</div>

<div class="chat-container">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Chat with Seller</h6>
            <div class="d-flex align-items-center gap-3">
            <span class="status-badge status-<?php echo $current_status; ?>" id="statusBadge">

                    <?php 
                    $status_icons = [
                        'pending' => '⏳ Pending',
                        'paid' => '✅ Paid',
                        'shipped' => '🚚 Shipped',
                        'completed' => '🎉 Completed'
                    ];
                    echo isset($status_icons[$current_status]) ? $status_icons[$current_status] : ucfirst($current_status);

                    ?>
                </span>
                <span class="small text-light">Buyer Panel</span>
            </div>
        </div>

        <div class="chat-box" id="chatBox">
            <?php if (empty($messages)): ?>
                <p class="text-center text-muted mt-5">No messages yet. Start chatting below.</p>
            <?php endif; ?>

            <?php foreach ($messages as $m): ?>
                <div class="d-flex mb-3 <?= $m['sender_type'] === 'buyer' ? 'justify-content-end' : 'justify-content-start' ?>">
                    <div class="chat-bubble <?= $m['sender_type'] === 'buyer' ? 'buyer-msg' : ($m['message_type'] === 'status_update' ? 'status-msg' : 'seller-msg') ?>">

                        <?php if ($m['message_type'] === 'qr' || $m['message_type'] === 'receipt'): ?>
                            <img src="<?= htmlspecialchars($m['file_path']) ?>" 
                                 class="img-fluid rounded mb-2" 
                                 style="max-width:250px;">
                            <p class="small mb-0"><?= htmlspecialchars($m['message']) ?></p>

                        <?php elseif ($m['message_type'] === 'confirmation'): ?>
                            <p class="text-success fw-bold mb-0">✔ Order Confirmed</p>

                        <?php elseif ($m['message_type'] === 'status_update'): ?>
                            <p class="mb-0">📋 <?= htmlspecialchars($m['message']) ?></p>

                        <?php else: ?>
                            <?= htmlspecialchars($m['message']) ?>
                        <?php endif; ?>

                        <div class="timestamp text-end"><?= htmlspecialchars($m['timestamp']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card-footer">
    
    <!-- ORDER STATUS INFO -->
    <div class="alert alert-light mb-3" id="statusInfo">
        <small class="text-muted">
            <strong>Order Status:</strong> 
            <span id="statusText">
                <?php 
                $status_descriptions = [
                    'pending' => '⏳ Waiting for payment verification by seller',
                    'paid' => '✅ Payment verified! Seller will prepare your order',
                    'shipped' => '🚚 Your order is on the way!',
                    'completed' => '🎉 Order completed! Thank you for shopping with us!'
                ];
                echo isset($status_descriptions[$current_status])
    ? $status_descriptions[$current_status]
    : ucfirst($current_status);

                ?>
            </span>
        </small>
    </div>

    <?php
// Check if order already placed - Look for MOST RECENT order_placement message
$order_check = $conn->prepare("SELECT quantity FROM tbl_chat_ukmart 
                               WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                               AND message_type = 'order_placement'
                               ORDER BY timestamp DESC
                               LIMIT 1");
$order_check->execute([$buyer_id, $seller_id, $product_id]);
$existing_order = $order_check->fetch(PDO::FETCH_ASSOC);
$order_already_placed = !empty($existing_order);

// Get the actual quantity from the order
$order_quantity = $order_already_placed ? (int)$existing_order['quantity'] : 0;
?>

<!-- ORDER PLACEMENT (Only show if NO order placed yet) -->
<?php if ($current_status === 'pending' && !$order_already_placed): ?>
<div class="alert alert-info mb-3">
    <h6><i class="bi bi-cart-check"></i> Ready to Buy?</h6>
    <p class="mb-2">Specify how many units you want to purchase:</p>
    <form method="post" action="place_order.php" class="d-flex gap-2 align-items-end" id="placeOrderForm">
        <input type="hidden" name="seller_id" value="<?= $seller_id ?>">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <div class="flex-grow-1">
            <label class="form-label small mb-1">Quantity</label>
            <input type="number" name="quantity" id="orderQuantityInput" class="form-control" 
                   value="<?= $from_cart && $cart_quantity > 0 ? $cart_quantity : 1 ?>" min="1" 
                   max="<?php 
                       $prod_stmt = $conn->prepare("SELECT fld_product_quantity FROM tbl_products_ukmart WHERE fld_product_id = ?");
                       $prod_stmt->execute([$product_id]);
                       $prod_data = $prod_stmt->fetch(PDO::FETCH_ASSOC);
                       echo $prod_data ? $prod_data['fld_product_quantity'] : 999;
                   ?>" 
                   required>
            <small class="text-muted">
                Available: <?php echo $prod_data ? $prod_data['fld_product_quantity'] : 'N/A'; ?> units
                <?php if ($from_cart && $cart_quantity > 0): ?>
                    <br><span class="text-primary"><i class="bi bi-cart"></i> From cart: <?= $cart_quantity ?> unit(s)</span>
                <?php endif; ?>
            </small>
        </div>
        <button type="submit" class="btn btn-success" id="placeOrderBtn">
            <i class="bi bi-cart-check"></i> Place Order
        </button>
    </form>
</div>
<?php elseif ($order_already_placed && $current_status === 'pending'): ?>
<div class="alert alert-success mb-3">
    <i class="bi bi-check-circle"></i> 
    <strong>Order Placed!</strong> You ordered <?= $order_quantity ?> unit(s). 
    Waiting for seller to send payment details.
</div>
<?php elseif ($order_already_placed && $current_status === 'paid'): ?>
<div class="alert alert-success mb-3">
    <i class="bi bi-check-circle-fill"></i> 
    <strong>Payment Verified!</strong> Your order of <?= $order_quantity ?> unit(s) is being prepared.
</div>
<?php elseif ($order_already_placed && $current_status === 'shipped'): ?>
<div class="alert alert-info mb-3">
    <i class="bi bi-truck"></i> 
    <strong>Order Shipped!</strong> Your order of <?= $order_quantity ?> unit(s) is on the way!
</div>
<?php endif; ?>

    <!-- REVIEW SECTION (Show when completed and haven't reviewed yet) -->
    <?php
    $canReview = false;
    $hasReview = false;
    if ($current_status === 'completed') {
        $review_check = $conn->prepare("SELECT * FROM tbl_ratings_reviews WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
        $review_check->execute([$buyer_id, $seller_id, $product_id]);
        $review_data = $review_check->fetch(PDO::FETCH_ASSOC);
        if (!$review_data) {
            $canReview = true;
        } else {
            $hasReview = true;
        }
    }
    ?>
    
    <?php if ($canReview): ?>
    <div class="alert alert-warning mb-3 text-center">
        <h6 class="mb-2"><i class="bi bi-star-fill"></i> Order Completed!</h6>
        <p class="mb-3 small">How was your experience with this seller?</p>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reviewModal">
            <i class="bi bi-star"></i> Write a Review
        </button>
    </div>
    <?php elseif ($hasReview): ?>
    <div class="alert alert-success mb-3 text-center">
        <i class="bi bi-check-circle-fill"></i> 
        <strong>Review Submitted!</strong> Thank you for your feedback.
        <?php if ($review_data): ?>
        <div class="mt-2">
            <small>Your rating: <?= str_repeat('⭐', $review_data['rating']) ?> (<?= $review_data['rating'] ?>/5)</small>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LIVE CHAT INPUT -->
    <div class="d-flex gap-2 mb-2">
        <input type="text" id="msg" class="form-control" placeholder="Type a message…" autocomplete="off">
        <button id="sendBtn" class="btn btn-primary px-4">Send</button>
    </div>

    <!-- UPLOAD RECEIPT (Only show if order placed but not completed) -->
    <?php if ($order_already_placed && $current_status !== 'completed'): ?>
    <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2 w-100">
        <input type="file" name="receipt" id="receiptInput" class="form-control flex-grow-1" accept="image/*" required>
        <img id="receiptPreview" src="" alt="Receipt Preview" style="max-width: 150px; display: none; border-radius: 8px; object-fit: contain;">
        <button type="submit" name="send_receipt" class="btn btn-info px-4">Upload Receipt</button>
    </form>
    <?php endif; ?>

</div>




<script>
const chatBox = document.getElementById("chatBox");
const statusBadge = document.getElementById("statusBadge");
const statusText = document.getElementById("statusText");
let lastMessageCount = <?= count($messages) ?>;
let currentStatus = "<?= $current_status ?>";

// Status descriptions and icons
const statusInfo = {
    pending: { 
        icon: "⏳ Pending", 
        description: "Waiting for payment verification by seller",
        class: "status-pending"
    },
    paid: { 
        icon: "✅ Paid", 
        description: "Payment verified! Seller will prepare your order",
        class: "status-paid"
    },
    shipped: { 
        icon: "🚚 Shipped", 
        description: "Your order is on the way! 🚚",
        class: "status-shipped"
    },
    completed: { 
        icon: "🎉 Completed", 
        description: "Order completed! Thank you for shopping with us! 🎉",
        class: "status-completed"
    }
};

function updateStatusDisplay(status) {
    if (status !== currentStatus) {
        currentStatus = status;
        const info = statusInfo[status] || { icon: status, description: "Unknown status", class: "status-pending" };
        
        // Update badge
        statusBadge.className = `status-badge ${info.class}`;
        statusBadge.textContent = info.icon;
        
        // Update description
        statusText.textContent = info.description;
        
        // Add a subtle animation
        statusBadge.style.transform = "scale(1.1)";
        setTimeout(() => {
            statusBadge.style.transform = "scale(1)";
        }, 200);
    }
}

function loadMessages() {
    const buyer = <?= $buyer_id ?>;
    const seller = <?= $seller_id ?>;
    const product = <?= $product_id ?>;

    fetch(`fetch_messages.php?buyer=${buyer}&seller=${seller}&product=${product}`)
        .then(res => res.json())
        .then(data => {
            if (data.length !== lastMessageCount) {
                lastMessageCount = data.length;
                
                // Get latest status
                let latestStatus = "pending";
                if (data.length > 0) {
                    latestStatus = data[data.length - 1].status || "pending";
                }
                
                // Update status display
                updateStatusDisplay(latestStatus);
                
                // If status changed to completed, reload page to show review button
                if (latestStatus === 'completed' && currentStatus !== 'completed') {
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
                
                chatBox.innerHTML = "";

                data.forEach(msg => {
                    const wrapper = document.createElement("div");
                    wrapper.className = "d-flex mb-3 " +
                        (msg.sender_type === "buyer" ? "justify-content-end" : "justify-content-start");

                    let content = "";
                    let bubbleClass = "";
                    
                    if (msg.sender_type === "buyer") {
                        bubbleClass = "buyer-msg";
                    } else if (msg.message_type === "status_update" || msg.message_type === "order_placement") {
                        bubbleClass = "status-msg";
                    } else {
                        bubbleClass = "seller-msg";
                    }
                    
                    if (msg.message_type === "receipt" || msg.message_type === "qr") {
                        content = `
                            <img src='${msg.file_path}?t=${Date.now()}' 
                                 class='img-fluid rounded mb-2' 
                                 style='max-width:250px;'
                                 loading="lazy">
                            <p class="small mb-0">${escapeHtml(msg.message)}</p>
                        `;
                    } else if (msg.message_type === "confirmation") {
                        content = `<p class="text-success fw-bold mb-0">✔ Order Confirmed</p>`;
                    } else if (msg.message_type === "status_update" || msg.message_type === "order_placement") {
                        content = `<p class="mb-0">📋 ${escapeHtml(msg.message)}</p>`;
                    } else {
                        content = escapeHtml(msg.message);
                    }

                    wrapper.innerHTML = `
                        <div class="chat-bubble ${bubbleClass}">
                            ${content}
                            <div class="timestamp text-end">${msg.timestamp}</div>
                        </div>
                    `;
                    chatBox.appendChild(wrapper);
                });

                chatBox.scrollTop = chatBox.scrollHeight;
            }
        })
        .catch(err => console.error("Error loading messages:", err));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load messages every 2 seconds
// Mark messages as read when chat is opened
function markMessagesAsRead() {
    const buyer = <?= $buyer_id ?>;
    const seller = <?= $seller_id ?>;
    const product = <?= $product_id ?>;
    
    // Mark as read immediately
    fetch(`mark_chat_read.php?seller_id=${seller}&product_id=${product}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('Messages marked as read');
                // Store timestamp in sessionStorage so nav_bar can detect the update
                sessionStorage.setItem('chatLastUpdate', Date.now());
                sessionStorage.setItem('chatMarkedRead', 'true');
                
                // Also update badge count immediately by fetching updated chat list
                fetch('get_chat_list.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Store updated count in sessionStorage for nav_bar to pick up
                            const totalUnread = data.conversations.reduce((sum, conv) => sum + (parseInt(conv.unread_count) || 0), 0);
                            sessionStorage.setItem('chatUnreadCount', totalUnread);
                            console.log('Updated unread count:', totalUnread);
                        }
                    })
                    .catch(err => console.error('Error updating badge:', err));
            }
        })
        .catch(err => console.error('Error marking messages as read:', err));
}

// Mark messages as read IMMEDIATELY when page loads (before loading messages)
markMessagesAsRead();

// Check if cart was updated (from place_order.php redirect)
if (window.location.search.includes('cart_updated=1')) {
    // Update cart badge immediately
    setTimeout(() => {
        if (typeof updateCartBadge === 'function') {
            updateCartBadge();
        } else if (window.updateCartBadge) {
            window.updateCartBadge();
        } else {
            // Fallback: fetch cart count directly
            fetch('get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('cartBadge');
                    if (badge) {
                        const cartCount = parseInt(data.cartCount) || 0;
                        if (cartCount > 0) {
                            badge.textContent = cartCount > 99 ? '99+' : cartCount;
                            badge.style.display = 'block';
                        } else {
                            badge.style.display = 'none';
                            badge.textContent = '0';
                        }
                    }
                })
                .catch(error => console.error('Error updating cart badge:', error));
        }
    }, 500);
}

setInterval(loadMessages, 2000);
loadMessages();

// Send message via AJAX
document.getElementById("sendBtn").onclick = function () {
    const msg = document.getElementById("msg").value.trim();
    if (msg === "") return;

    const btn = document.getElementById("sendBtn");
    btn.disabled = true;

    fetch("sent_message_ajax.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            message: msg,
            buyer_id: <?= $buyer_id ?>,
            seller_id: <?= $seller_id ?>,
            product_id: <?= $product_id ?>,
            sender_type: "buyer"
        })
    })
    .then(res => res.text())
    .then(data => {
        console.log("Server response:", data);
        if (data.includes("OK")) {
            document.getElementById("msg").value = "";
            lastMessageCount = 0;
            loadMessages();
        } else {
            alert("Error: " + data);
        }
        btn.disabled = false;
    })
    .catch(err => {
        console.error("Error sending message:", err);
        alert("Failed to send message. Check console.");
        btn.disabled = false;
    });
};

// Allow sending with Enter key
document.getElementById("msg").addEventListener("keypress", function(e) {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        document.getElementById("sendBtn").click();
    }
});

// Preview selected image
const receiptInput = document.getElementById("receiptInput");
const receiptPreview = document.getElementById("receiptPreview");

receiptInput.addEventListener("change", function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            receiptPreview.src = e.target.result;
            receiptPreview.style.display = "block";
        };
        reader.readAsDataURL(file);
    } else {
        receiptPreview.src = "";
        receiptPreview.style.display = "none";
    }
});

</script>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="reviewModalLabel">Rate & Review Seller</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label for="rating" class="form-label">Rating:</label>
                <select name="rating" id="rating" class="form-select" required>
                    <option value="">Select rating</option>
                    <?php for ($i=1; $i<=5; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> ⭐</option>

                    <?php endfor; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="review" class="form-label">Review:</label>
                <textarea name="review" id="review" class="form-control" rows="4" placeholder="Write your review..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add JavaScript to prevent double submission and handle review submission -->
<script>
document.getElementById('placeOrderForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Placing Order...';
    
    // After form submission, update cart badge
    // The page will reload after order is placed, but we can trigger an update now
    setTimeout(() => {
        if (typeof updateCartBadge === 'function') {
            updateCartBadge();
        } else if (window.updateCartBadge) {
            window.updateCartBadge();
        }
    }, 1000);
});

// Handle review modal submission via AJAX
const reviewModal = document.getElementById('reviewModal');
if (reviewModal) {
    const reviewForm = reviewModal.querySelector('form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const submitBtn = this.querySelector('button[name="submit_review"]');
            const rating = this.querySelector('select[name="rating"]').value;
            const review = this.querySelector('textarea[name="review"]').value;
            
            if (!rating) {
                alert('Please select a rating');
                return;
            }
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
            }
            
            // Submit via AJAX
            const formData = new FormData();
            formData.append('submit_review', '1');
            formData.append('rating', rating);
            formData.append('review', review);
            
            fetch('chatbuyer.php?product_id=<?= $product_id ?>&seller_id=<?= $seller_id ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                console.log('Review submission response:', data);
                if (data.includes("OK") || data.trim() === "OK") {
                    // Success - close modal and reload page
                    const modal = bootstrap.Modal.getInstance(reviewModal);
                    if (modal) modal.hide();
                    
                    // Reload page to show updated review status
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                } else {
                    // Error
                    alert('Error submitting review: ' + data);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Review';
                    }
                }
            })
            .catch(err => {
                console.error('Error submitting review:', err);
                alert('Failed to submit review. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Review';
                }
            });
        });
    }
}
</script>

</body>
</html>