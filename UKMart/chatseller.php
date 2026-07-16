<?php
session_start();
include_once 'database.php';

// Make sure seller is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in as seller to chat.");
}

// Fetch seller_id from session
$user_id = $_SESSION['user_id']; 
$stmt = $conn->prepare("SELECT fld_seller_id FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) die("You are not a seller yet.");
$seller_id = $seller['fld_seller_id'];

$buyer_id   = isset($_GET['buyer_id']) ? $_GET['buyer_id'] : null;
$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : null;

if (!$buyer_id || !$product_id) {
    die("Invalid chat request.");
}

// UPDATE ORDER STATUS
if (isset($_POST['update_status'])) {
    $new_status = $_POST['update_status'];
    $allowed_statuses = ['pending', 'paid', 'shipped', 'completed'];
    
    if (in_array($new_status, $allowed_statuses)) {
        // Get the quantity (might be NULL if just chatting)
        $qty_stmt = $conn->prepare("SELECT quantity FROM tbl_chat_ukmart 
                                    WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                    AND quantity IS NOT NULL
                                    ORDER BY timestamp DESC LIMIT 1");
        $qty_stmt->execute([$buyer_id, $seller_id, $product_id]);
        $chat_data = $qty_stmt->fetch(PDO::FETCH_ASSOC);
        $quantity_ordered = $chat_data ? (int)$chat_data['quantity'] : 0;
        
        // ONLY subtract quantity if: 
        // 1. Status is changing to 'paid' 
        // 2. An actual order was placed (quantity > 0)
        if ($new_status === 'paid' && $quantity_ordered > 0) {
            // Get current product quantity
            $prod_stmt = $conn->prepare("SELECT fld_product_quantity FROM tbl_products_ukmart WHERE fld_product_id = ?");
            $prod_stmt->execute([$product_id]);
            $product_data = $prod_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product_data) {
                $current_qty = (int)$product_data['fld_product_quantity'];
                
                // Validate we have enough stock
                if ($current_qty >= $quantity_ordered) {
                    $new_qty = $current_qty - $quantity_ordered;
                    
                    // Update product quantity
                    $update_prod = $conn->prepare("UPDATE tbl_products_ukmart 
                                                  SET fld_product_quantity = ?,
                                                      is_available = CASE WHEN ? <= 0 THEN 0 ELSE 1 END
                                                  WHERE fld_product_id = ?");
                    $update_prod->execute([$new_qty, $new_qty, $product_id]);
                } else {
                    // Not enough stock!
                    $_SESSION['error'] = "Error: Not enough stock! Current stock: $current_qty, Ordered: $quantity_ordered";
                    header("Location: chatseller.php?product_id=$product_id&buyer_id=$buyer_id");
                    exit;
                }
            }
        }
        
        // Update chat status
        $stmt = $conn->prepare("UPDATE tbl_chat_ukmart 
            SET status = ? 
            WHERE buyer_id = ? AND seller_id = ? AND product_id = ?");
        $stmt->execute([$new_status, $buyer_id, $seller_id, $product_id]);
        
        // Insert status update message
        $status_messages = [
            'paid' => $quantity_ordered > 0 
                ? "✅ Payment verified! Order is now PAID. ($quantity_ordered units reserved)" 
                : "✅ Payment verified!",
            'shipped' => $quantity_ordered > 0 
                ? "🚚 Order has been SHIPPED! ($quantity_ordered units on the way)" 
                : "🚚 Order has been SHIPPED!",
            'completed' => $quantity_ordered > 0 
                ? "🎉 Order COMPLETED! ($quantity_ordered units delivered)" 
                : "🎉 Order COMPLETED!"
        ];
        
        if (isset($status_messages[$new_status])) {
            $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
                (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, quantity, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['seller', 'Seller', $status_messages[$new_status], 'status_update', 
                           $buyer_id, $seller_id, $product_id, $quantity_ordered, $new_status]);
        }
    }
    
    header("Location: chatseller.php?product_id=$product_id&buyer_id=$buyer_id");
    exit;
}

// SEND QR CODE
if (isset($_POST['send_qr'])) {
    $file = $_FILES['qr']['name'];
    if (!empty($file)) {
        if (!is_dir('uploads')) mkdir('uploads');
        $target = "uploads/" . basename($file);
        move_uploaded_file($_FILES['qr']['tmp_name'], $target);

        // Get current status and quantity
        $status_stmt = $conn->prepare("SELECT COALESCE(status, 'pending') as status, quantity FROM tbl_chat_ukmart 
                                       WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                       ORDER BY timestamp DESC LIMIT 1");
        $status_stmt->execute([$buyer_id, $seller_id, $product_id]);
        $latest_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
        $current_status = $latest_data ? $latest_data['status'] : 'pending';
        $current_quantity = $latest_data && $latest_data['quantity'] ? $latest_data['quantity'] : null;

        $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
            (sender_type, sender_name, message, message_type, file_path, buyer_id, seller_id, product_id, quantity, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['seller', 'Seller', 'Scan this QR to make payment.', 'qr', $target, $buyer_id, $seller_id, $product_id, $current_quantity, $current_status]);
    }
    header("Location: chatseller.php?product_id=$product_id&buyer_id=$buyer_id");
    exit;
}

// CONFIRM ORDER
if (isset($_POST['confirm_order'])) {
    // Get current status
    $status_stmt = $conn->prepare("SELECT COALESCE(status, 'pending') as status, quantity FROM tbl_chat_ukmart 
                                   WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                   ORDER BY timestamp DESC LIMIT 1");
    $status_stmt->execute([$buyer_id, $seller_id, $product_id]);
    $latest_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
    $current_status = $latest_data ? $latest_data['status'] : 'pending';
    $quantity = $latest_data && $latest_data['quantity'] ? $latest_data['quantity'] : 0;

    $stmt = $conn->prepare("INSERT INTO tbl_chat_ukmart 
        (sender_type, sender_name, message, message_type, buyer_id, seller_id, product_id, quantity, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['seller', 'Seller', 'Order confirmed. Thank you!', 'confirmation', 
                   $buyer_id, $seller_id, $product_id, $quantity, $current_status]);

    echo "OK"; // Return OK for AJAX
    exit;
}

// FETCH MESSAGES FOR THIS CHAT
$stmt = $conn->prepare("SELECT * FROM tbl_chat_ukmart 
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

// Get quantity from most recent order placement message
$qty_stmt = $conn->prepare("SELECT quantity, message, timestamp FROM tbl_chat_ukmart 
                            WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                            AND message_type = 'order_placement'
                            ORDER BY timestamp DESC LIMIT 1");
$qty_stmt->execute([$buyer_id, $seller_id, $product_id]);
$chat_data = $qty_stmt->fetch(PDO::FETCH_ASSOC);
$quantity_ordered = 0;
$order_placed = false;

if ($chat_data && isset($chat_data['quantity']) && $chat_data['quantity'] > 0) {
    $quantity_ordered = (int)$chat_data['quantity'];
    $order_placed = true;
}





// Check if order already confirmed
$confirm_check = $conn->prepare("SELECT COUNT(*) FROM tbl_chat_ukmart 
                                 WHERE buyer_id = ? AND seller_id = ? AND product_id = ? 
                                 AND message_type = 'confirmation'");
$confirm_check->execute([$buyer_id, $seller_id, $product_id]);
$already_confirmed = $confirm_check->fetchColumn() > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seller Chat | UKMart</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://api.fontshare.com/v2/css?f[]=satoshi@300,400,500,600,700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Georgia&display=swap" rel="stylesheet">

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

.seller-msg {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 18px 18px 4px 18px;
  margin-left: auto;
}

.buyer-msg {
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

.status-controls {
  background: linear-gradient(to right, #f0f4ff 0%, #fafbfc 100%);
  border: 2px solid var(--primary-blue-light);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}

.status-controls h6 {
  color: var(--text-dark);
  font-weight: 600;
  margin-bottom: 16px;
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

<!-- SELLER NAVBAR -->
<?php include('seller_nav_bar.php'); ?>

<!-- Back Button -->
<div class="container my-3">
  <a href="seller.php" class="back-btn" style="font-size: 1.5rem; color: #2563eb; padding: 10px; display: inline-block; margin-bottom: 15px; text-decoration: none; transition: all 0.3s ease;" title="Back to Seller Dashboard">
    <i class="bi bi-arrow-left-circle-fill"></i> Back to Dashboard
  </a>
</div>

<div class="chat-container">
  <div class="card shadow">

    <!-- Update the header to show quantity properly -->
<div class="card-header d-flex justify-content-between align-items-center">
  <h6 class="mb-0">Chat with Buyer</h6>
  <div class="d-flex align-items-center gap-3">
    <span class="badge bg-light text-dark" id="quantityBadge">
      <?php if ($quantity_ordered > 0): ?>
        <i class="bi bi-box"></i> Order: <?= $quantity_ordered ?> units
      <?php else: ?>
        <i class="bi bi-chat"></i> Inquiry Only
      <?php endif; ?>
    </span>
    <span class="status-badge status-<?= $current_status ?>"><?= ucfirst($current_status) ?></span>
    <span class="small text-light">Seller Panel</span>
  </div>
</div>

    <div class="chat-box" id="chatBox">
      <?php if (empty($messages)): ?>
        <p class="text-center text-muted mt-5">No messages yet.</p>
      <?php endif; ?>

      <?php foreach ($messages as $m): ?>
        <div class="d-flex mb-3 <?= $m['sender_type'] === 'seller' ? 'justify-content-end' : 'justify-content-start' ?>">
          <div class="chat-bubble <?= $m['sender_type'] === 'seller' ? 'seller-msg' : (($m['message_type'] === 'status_update' || $m['message_type'] === 'order_placement') ? 'status-msg' : 'buyer-msg') ?>">

            <?php if ($m['message_type'] == 'qr' || $m['message_type'] == 'receipt'): ?>
              <img src="<?= htmlspecialchars($m['file_path']) ?>" 
                   class="img-fluid rounded mb-2" 
                   style="max-width:250px;">
              <p class="small mb-0"><?= htmlspecialchars($m['message']) ?></p>

            <?php elseif ($m['message_type'] == 'confirmation'): ?>
              <p class="text-success fw-bold mb-0">✔ Order Confirmed</p>

            <?php elseif ($m['message_type'] == 'status_update' || $m['message_type'] == 'order_placement'): ?>
              <p class="mb-0">📋 <?= htmlspecialchars($m['message']) ?></p>

            <?php else: ?>
              <?= htmlspecialchars($m['message']) ?>
            <?php endif; ?>

            <div class="timestamp text-end">
              <?= htmlspecialchars($m['timestamp']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card-footer">

      <!-- ORDER STATUS CONTROLS -->
      <div class="status-controls">
        <h6 class="mb-3">Order Status Management</h6>
        
        <?php if (!$order_placed): ?>
          <div class="alert alert-info mb-0">
            <i class="bi bi-clock"></i> Waiting for buyer to place order...
          </div>
        <?php else: ?>
          <form method="post" class="d-flex gap-2 flex-wrap">
            
            <?php if ($current_status === 'pending'): ?>
              <button type="submit" name="update_status" value="paid" 
                      class="btn btn-success btn-sm">
                ✓ Verify Payment (Mark as Paid)
              </button>
            <?php endif; ?>

            <?php if ($current_status === 'paid'): ?>
              <button type="submit" name="update_status" value="shipped" 
                      class="btn btn-info btn-sm">
                🚚 Mark as Shipped
              </button>
            <?php endif; ?>

            <?php if ($current_status === 'shipped'): ?>
              <button type="submit" name="update_status" value="completed" 
                      class="btn btn-primary btn-sm">
                ✅ Mark as Completed
              </button>
            <?php endif; ?>

            <?php if ($current_status === 'completed'): ?>
              <span class="text-success fw-bold">🎉 Order Completed!</span>
            <?php endif; ?>

          </form>
        <?php endif; ?>
      </div>

      <!-- LIVE CHAT INPUT -->
      <div class="d-flex gap-2 mb-2">
          <input type="text" id="msg" class="form-control" placeholder="Type a message…" autocomplete="off">
          <button id="sendBtn" class="btn btn-primary px-4">Send</button>
      </div>

      <!-- QR UPLOAD WITH PREVIEW -->
      <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2 w-100 mb-2">
          <input type="file" name="qr" id="qrInput" class="form-control flex-grow-1" accept="image/*" required>
          <img id="qrPreview" src="" alt="QR Preview" style="max-width: 150px; display: none; border-radius: 8px; object-fit: contain;">
          <button type="submit" name="send_qr" class="btn btn-info px-4">Send QR</button>
      </form>

      <!-- CONFIRM ORDER -->
      <?php if (!$already_confirmed): ?>
        <button id="confirmOrderBtn" class="btn btn-success w-100 py-2">Confirm Order</button>
      <?php else: ?>
        <button class="btn btn-secondary w-100 py-2" disabled>
          <i class="bi bi-check-circle"></i> Order Already Confirmed
        </button>
      <?php endif; ?>

    </div>

  </div>
</div>

<script>
const chatBox = document.getElementById("chatBox");
let lastMessageCount = <?= count($messages) ?>;
let currentQuantity = <?= $quantity_ordered ?>;

function loadMessages() {
    const buyer = <?= $buyer_id ?>;
    const seller = <?= $seller_id ?>;
    const product = <?= $product_id ?>;

    fetch(`fetch_messages.php?buyer=${buyer}&seller=${seller}&product=${product}`)
        .then(res => res.json())
        .then(data => {
            if (data.length !== lastMessageCount) {
                lastMessageCount = data.length;
                
                // Get quantity from MOST RECENT order_placement message
                let latestQuantity = 0;
                for (let i = data.length - 1; i >= 0; i--) {
                    if (data[i].message_type === 'order_placement' && data[i].quantity && parseInt(data[i].quantity) > 0) {
                        latestQuantity = parseInt(data[i].quantity);
                        break;
                    }
                }
                
                // Update quantity display ONLY if changed
                if (latestQuantity !== currentQuantity) {
                    currentQuantity = latestQuantity;
                    const quantityBadge = document.getElementById("quantityBadge");
                    if (quantityBadge) {
                        if (latestQuantity > 0) {
                            quantityBadge.innerHTML = `<i class="bi bi-box"></i> Order: ${latestQuantity} units`;
                            quantityBadge.className = "badge bg-light text-dark";
                        } else {
                            quantityBadge.innerHTML = `<i class="bi bi-chat"></i> Inquiry Only`;
                            quantityBadge.className = "badge bg-secondary";
                        }
                    }
                }
                
                // Render messages
                chatBox.innerHTML = "";
                data.forEach(msg => {
                    const wrapper = document.createElement("div");
                    wrapper.className = "d-flex mb-3 " +
                        (msg.sender_type === "seller" ? "justify-content-end" : "justify-content-start");

                    let content = "";
                    let bubbleClass = "";
                    
                    if (msg.sender_type === "seller") {
                        bubbleClass = "seller-msg";
                    } else if (msg.message_type === "status_update" || msg.message_type === "order_placement") {
                        bubbleClass = "status-msg";
                    } else {
                        bubbleClass = "buyer-msg";
                    }
                    
                    if (msg.message_type === "receipt" || msg.message_type === "qr") {
                        content = `
                            <img src='${msg.file_path}?t=${Date.now()}' 
                                 class='img-fluid rounded mb-2' 
                                 style='max-width:250px;'>
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
            sender_type: "seller"
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

// QR preview
const qrInput = document.getElementById('qrInput');
const qrPreview = document.getElementById('qrPreview');

qrInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            qrPreview.src = e.target.result;
            qrPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        qrPreview.src = '';
        qrPreview.style.display = 'none';
    }
});

// Confirm order button
const confirmOrderBtn = document.getElementById('confirmOrderBtn');
if (confirmOrderBtn) {
    confirmOrderBtn.addEventListener('click', function() {
        // Disable immediately
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirming...';

        fetch('chatseller.php?buyer_id=<?= $buyer_id ?>&product_id=<?= $product_id ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'confirm_order=1'
        })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === "OK") {
                // Reload messages
                lastMessageCount = 0;
                loadMessages();
                
                // Change button permanently
                this.innerHTML = '<i class="bi bi-check-circle"></i> Order Confirmed';
                this.className = 'btn btn-secondary w-100 py-2';
            } else {
                alert('Error: ' + data);
                this.disabled = false;
                this.innerHTML = 'Confirm Order';
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Failed to confirm order');
            this.disabled = false;
            this.innerHTML = 'Confirm Order';
        });
    });
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>