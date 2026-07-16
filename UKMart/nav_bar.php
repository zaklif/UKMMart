<?php
// Session and DB initialization
if (!isset($_SESSION)) {
    session_start();
}

// Validate session
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

// Get user name with error handling
$user_name = 'User'; // Default fallback
try {
    $user_id = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    if (!$user_id) throw new Exception('Invalid user ID');
    
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'buyer';

    
   if ($user_type == 'seller') {
    $stmt = $conn->prepare("SELECT fld_seller_name FROM tbl_sellers_ukmart WHERE fld_user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = isset($row['fld_seller_name']) ? $row['fld_seller_name'] : 'Seller';
} else {
    $stmt = $conn->prepare("SELECT fld_user_name FROM tbl_user_ukmart WHERE fld_user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = isset($row['fld_user_name']) ? $row['fld_user_name'] : 'User';
}

} catch (Exception $e) {
    error_log("Error fetching user name: " . $e->getMessage());
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
    
    /* Global font family for all pages */
    * {
      font-family: 'Satoshi', Georgia, serif !important;
    }
    
    /* ===== Navbar ===== */
    .navbar {
      background: #ffffff;
      border-bottom: 1px solid #e5e7eb;
      padding: 0.75rem 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 600;
      color: #2563eb;
      letter-spacing: -0.3px;
      text-decoration: none;
      display: flex;
      align-items: center;
    }
    .navbar-brand:hover {
      color: #1e40af;
    }
    .navbar-brand img {
      height: 40px;
      width: auto;
      object-fit: contain;
      transition: opacity 0.2s ease;
    }
    .navbar-brand:hover img {
      opacity: 0.8;
    }
    .nav-link, .navbar-text {
      color: #374151 !important;
      font-weight: 400;
      font-size: 0.95rem;
    }

    /* ===== Profile Button and Dropdown ===== */
    .navbar .btn-icon {
      background: transparent;
      border: none;
      color: #374151;
      font-size: 1.1rem;
      padding: 6px 10px;
      border-radius: 6px;
      transition: all 0.2s ease;
    }
    .navbar .btn-icon:hover {
      color: #2563eb;
      background: #f3f4f6;
    }
    .navbar .dropdown-toggle {
      background: transparent;
      border: none;
      color: #374151;
      font-weight: 400;
      font-size: 0.95rem;
      padding: 6px 12px;
      border-radius: 6px;
    }
    .navbar .dropdown-toggle:hover {
      color: #2563eb;
      background: #f3f4f6;
    }
    .navbar .form-control {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 16px;
      font-size: 0.9rem;
    }
    .navbar .form-control:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .navbar .btn {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 8px 20px;
      font-weight: 500;
      font-size: 0.9rem;
      transition: all 0.2s ease;
    }
    .navbar .btn:hover {
      background: #1e40af;
    }

    /* ===== Search bar and location ===== */
    .search-bar-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }

    .search-bar-container .form-control {
      width: 80%;
      border-radius: 30px;
      padding-left: 2rem;
    }

    .location-container {
      font-size: 0.9rem;
      color: #555;
    }

    /* Cart badge - with animation */
    .cart-badge {
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
      animation: pulse-red-badge 2s infinite;
      z-index: 10;
    }
    
    /* Chat notification badge - red color same as cart */
    .chat-notification-badge {
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
      animation: pulse-red-badge 2s infinite;
      z-index: 10;
    }
    
    /* Badge text overflow handling */
    .cart-badge,
    .chat-notification-badge {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 30px;
    }
    
    @keyframes pulse-red-badge {
      0%, 100% { 
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4), 0 0 0 0 rgba(220, 38, 38, 0.7);
        transform: scale(1);
      }
      50% { 
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4), 0 0 0 6px rgba(220, 38, 38, 0);
        transform: scale(1.05);
      }
    }

    /* ===== Responsive Adjustments ===== */
    /* Mobile devices (phones) */
    @media (max-width: 767px) {
      .navbar {
        padding: 0.5rem 1rem;
      }
      
      .navbar .container-fluid {
        flex-wrap: wrap;
        gap: 10px;
      }
      
      .navbar-brand {
        font-size: 1.1rem;
        gap: 6px;
        flex: 0 0 auto;
      }
      
      .navbar-brand img {
        height: 35px;
      }
      
      .navbar .btn-icon {
        font-size: 1.3rem;
        padding: 4px 8px;
      }
      
      .navbar .dropdown-toggle {
        font-size: 0.85rem;
        padding: 4px 8px;
      }
      
      /* Search form adjustments */
      .navbar #navbarContent {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e5e7eb;
      }
      
      .navbar form.d-flex {
        width: 100%;
        margin-top: 10px;
        margin-left: 0 !important;
        margin-bottom: 10px;
      }
      
      .navbar .d-flex.align-items-center {
        width: 100%;
        justify-content: space-between;
        margin-left: 0 !important;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
      }
      
      .navbar form .form-control {
        font-size: 0.85rem;
        padding: 6px 12px;
      }
      
      .navbar form .btn {
        font-size: 0.85rem;
        padding: 6px 12px;
      }
      
      /* Right side buttons container */
      .navbar .d-flex.align-items-center {
        gap: 8px;
        margin-left: auto;
      }
      
      /* Cart and chat icons spacing */
      .navbar .d-flex.align-items-center .btn-icon {
        margin-left: 8px !important;
      }
      
      /* Badge adjustments for mobile */
      .cart-badge,
      .chat-notification-badge {
        font-size: 0.7rem;
        min-width: 16px;
        height: 16px;
        padding: 1px 5px;
        top: -3px;
        right: -3px;
      }
      
      /* Live search results */
      #liveSearchResults {
        max-height: 250px;
        font-size: 0.85rem;
      }
    }
    
    /* Tablet devices */
    @media (min-width: 768px) and (max-width: 991px) {
      .navbar {
        padding: 0.6rem 1.2rem;
      }
      
      .navbar-brand {
        font-size: 1.3rem;
      }
      
      .navbar-brand img {
        height: 40px;
      }
      
      .navbar form .form-control {
        width: 70%;
      }
    }
    
    /* Large screens */
    @media (min-width: 992px) {
      .navbar form .form-control {
        min-width: 300px;
      }
    }

    
/* ===== Modern Chat Popup Theme ===== */
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
    from { 
        opacity: 0;
        backdrop-filter: blur(0px);
    }
    to { 
        opacity: 1;
        backdrop-filter: blur(4px);
    }
}

@keyframes slideUp {
    from {
        transform: translateY(20px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
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
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
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
    position: relative;
}

.chat-popup-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
}

.chat-popup-header h6 {
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-popup-header h6 i {
    font-size: 1.3rem;
}

.chat-close-btn {
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
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.chat-close-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: rotate(90deg) scale(1.1);
}

.chat-list-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    background: linear-gradient(to bottom, #fafbfc 0%, #f8fafc 100%);
    scrollbar-width: thin;
    scrollbar-color: rgba(37, 99, 235, 0.3) transparent;
    max-height: calc(100% - 80px);
}

.chat-list-container::-webkit-scrollbar {
    width: 6px;
}

.chat-list-container::-webkit-scrollbar-track {
    background: transparent;
}

.chat-list-container::-webkit-scrollbar-thumb {
    background: rgba(37, 99, 235, 0.3);
    border-radius: 10px;
}

.chat-list-container::-webkit-scrollbar-thumb:hover {
    background: rgba(37, 99, 235, 0.5);
}

.chat-conversation-item {
    padding: 16px 24px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    background: var(--bg-white);
    position: relative;
}

.chat-conversation-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleY(0);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.chat-conversation-item:hover {
    background: linear-gradient(to right, #f0f4ff 0%, #fafbfc 100%);
    padding-left: 28px;
}

.chat-conversation-item:hover::before {
    transform: scaleY(1);
}

/* Unread message styling */
.chat-conversation-item.unread {
    background: linear-gradient(to right, #f0f4ff 0%, #fafbfc 100%);
    border-left: 4px solid #667eea;
}

.chat-conversation-item.unread .chat-name {
    font-weight: 700;
    color: #1e293b;
}

.chat-conversation-item.unread .chat-last-message {
    font-weight: 600;
    color: #334155;
}

.chat-conversation-item.unread:hover {
    background: linear-gradient(to right, #e0e7ff 0%, #f0f4ff 100%);
}

.chat-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--primary-blue) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    margin-right: 16px;
    flex-shrink: 0;
    border: 3px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
    overflow: hidden;
    position: relative;
}

.chat-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-name {
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--text-dark);
    font-size: 0.95rem;
    letter-spacing: 0.2px;
}

.chat-last-message {
    font-size: 0.85rem;
    color: var(--text-gray);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}

.chat-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
    gap: 6px;
}

.chat-time {
    font-size: 0.75rem;
    color: var(--text-gray);
    font-weight: 500;
    white-space: nowrap;
}

.chat-unread-badge {
    background: red;
    color: white;
    border-radius: 12px;
    padding: 4px 10px;
    font-size: 0.7rem;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
    min-width: 20px;
    text-align: center;
}

.no-chats-message {
    text-align: center;
    padding: 80px 30px;
    color: var(--text-gray);
}

.no-chats-message i {
    font-size: 5rem;
    margin-bottom: 24px;
    opacity: 0.2;
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.no-chats-message p {
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--text-dark);
}

.no-chats-message small {
    font-size: 0.9rem;
    color: var(--text-gray);
}

/* Chat notification badge is now defined in navbar styles above */

@keyframes pulse-green {
    0%, 100% { 
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4), 0 0 0 0 rgba(16, 185, 129, 0.7);
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4), 0 0 0 8px rgba(16, 185, 129, 0);
        transform: scale(1.05);
    }
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
    
    .chat-popup-header h6 {
        font-size: 1rem;
    }
    
    .chat-conversation-item {
        padding: 14px 20px;
    }
    
    .chat-avatar {
        width: 48px;
        height: 48px;
        margin-right: 12px;
    }
    
    .chat-name {
        font-size: 0.9rem;
    }
    
    .chat-last-message {
        font-size: 0.8rem;
    }
    
    .chat-messages-container {
        padding: 15px;
    }
    
    .chat-message-bubble {
        max-width: 85%;
        font-size: 0.9rem;
        padding: 10px 14px;
    }
    
    .chat-input-container {
        padding: 12px 20px;
    }
    
    .chat-input-form .form-control {
        font-size: 0.9rem;
        padding: 6px 14px;
    }
    
    .chat-input-form .btn {
        padding: 6px 16px;
        font-size: 0.9rem;
    }
    
    .no-chats-message {
        padding: 60px 20px;
    }
    
    .no-chats-message i {
        font-size: 4rem;
    }
}

@media (max-width: 576px) {
    .chat-popup-header {
        padding: 15px 16px;
    }
    
    .chat-popup-header h6 {
        font-size: 0.95rem;
    }
    
    .chat-close-btn,
    .chat-back-btn {
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
    }
    
    .chat-conversation-item {
        padding: 12px 16px;
    }
    
    .chat-avatar {
        width: 44px;
        height: 44px;
        margin-right: 10px;
    }
    
    .chat-name {
        font-size: 0.85rem;
    }
    
    .chat-last-message {
        font-size: 0.75rem;
    }
    
    .chat-unread-badge {
        font-size: 0.65rem;
        padding: 3px 8px;
        min-width: 18px;
    }
    
    .chat-messages-container {
        padding: 12px;
        max-height: calc(100vh - 250px);
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .chat-message-bubble {
        max-width: 90%;
        font-size: 0.85rem;
        padding: 8px 12px;
    }
    
    .chat-input-container {
        padding: 10px 16px;
    }
    
    .chat-input-form {
        gap: 6px;
    }
    
    .chat-list-container {
        max-height: calc(100vh - 120px);
    }
    
    .chat-input-form .form-control {
        font-size: 0.85rem;
        padding: 5px 12px;
    }
    
    .chat-input-form .btn {
        padding: 5px 14px;
        font-size: 0.85rem;
    }
    
    .no-chats-message {
        padding: 40px 15px;
    }
    
    .no-chats-message i {
        font-size: 3rem;
    }
    
    .no-chats-message p {
        font-size: 1rem;
    }
}

/* ===== Chat View Styles ===== */
.chat-back-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    margin-right: 8px;
}

.chat-back-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}

.chat-status-bar {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 12px 24px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    display: flex;
    justify-content: center;
}

.chat-messages-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    background: linear-gradient(to bottom, #fafbfc 0%, #f8fafc 100%);
    padding: 20px;
    scrollbar-width: thin;
    scrollbar-color: rgba(37, 99, 235, 0.3) transparent;
    min-height: 0;
    max-height: calc(650px - 200px);
}

.chat-messages-container::-webkit-scrollbar {
    width: 6px;
}

.chat-messages-container::-webkit-scrollbar-track {
    background: transparent;
}

.chat-messages-container::-webkit-scrollbar-thumb {
    background: rgba(37, 99, 235, 0.3);
    border-radius: 10px;
}

.chat-messages-container::-webkit-scrollbar-thumb:hover {
    background: rgba(37, 99, 235, 0.5);
}

.chat-message-item {
    margin-bottom: 16px;
    display: flex;
}

.chat-message-item.buyer {
    justify-content: flex-end;
}

.chat-message-item.seller {
    justify-content: flex-start;
}

.chat-message-bubble {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 0.95rem;
    line-height: 1.4;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    position: relative;
}

.chat-message-bubble.buyer-msg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px 16px 4px 16px;
}

.chat-message-bubble.seller-msg {
    background: #6c757d;
    color: white;
    border-radius: 16px 16px 16px 4px;
}

.chat-message-bubble.status-msg {
    background: #e8f5e8;
    color: #155724;
    border: 1px solid #c3e6cb;
    text-align: center;
    max-width: 80%;
    margin: 0 auto;
    font-weight: 600;
}

.chat-message-image {
    max-width: 200px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.chat-message-timestamp {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 4px;
    text-align: right;
}

.chat-input-container {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    flex-shrink: 0;
}

.chat-input-form {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.chat-input-form .form-control {
    flex: 1;
    border-radius: 20px;
    border: 2px solid #e2e8f0;
    padding: 8px 16px;
    font-size: 0.95rem;
}

.chat-input-form .btn {
    border-radius: 20px;
    padding: 8px 20px;
    font-weight: 600;
}

.receipt-upload-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

.receipt-upload-form .form-control {
    flex: 1;
}

.receipt-upload-form .btn {
    white-space: nowrap;
}

.no-messages-message {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-gray);
}

.no-messages-message i {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: 0.3;
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.no-messages-message p {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 4px;
    color: var(--text-dark);
}

.chat-review-container {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    text-align: center;
    flex-shrink: 0;
}

.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    animation: pulse 2s infinite;
}

.status-pending { 
    background-color: #ffc107; 
    color: #000; 
}
.status-paid { 
    background-color: #28a745; 
    color: #fff; 
}
.status-shipped { 
    background-color: #17a2b8; 
    color: #fff; 
}
.status-completed { 
    background-color: #6f42c1; 
    color: #fff; 
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
</style>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <!-- Change the brand name and link it to buyer.php -->
    <a class="navbar-brand" href="buyer.php">
      <span>UKMart</span>
      <img src="uploads/UKMart_logo.png" alt="UKMart Logo">
    </a>
    
    <!-- Mobile menu toggle button -->
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation" style="border: none; background: transparent; padding: 4px 8px;">
      <i class="bi bi-list" style="font-size: 1.5rem; color: #374151;"></i>
    </button>
    
    <!-- Collapsible content -->
    <div class="collapse navbar-collapse" id="navbarContent">
      <form class="d-flex ms-auto position-relative" autocomplete="off" method="GET" action="search.php">
        <input id="searchInput" 
               name="q"
               class="form-control me-2" 
               type="text" 
               placeholder="Search products...">

        <button class="btn btn-light" type="submit">Search</button>

        <!-- Live Search Result Box -->
        <div id="liveSearchResults"
             class="list-group shadow"
             style="
                position:absolute; 
                top:100%;
                left:0; 
                right:0; 
                z-index:2000; 
                background:white; 
                display:none; 
                max-height:350px; 
                overflow-y:auto; 
                margin-top:5px;
                border-radius:5px;
             ">
        </div>
      </form>

      <div class="d-flex align-items-center ms-3 position-relative">
      <div class="dropdown">
        <!-- Display the user's name dynamically -->
        <button class="btn btn-icon dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          Hello, <?php echo $user_name; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
          <li><a class="dropdown-item" href="buyer_profile.php">Profile</a></li>
          <li><a class="dropdown-item" href="logout.php">Logout</a></li>
        </ul>
      </div>
      <button class="btn-icon position-relative" id="openChatPopup" style="font-size: 1.5rem;">
    <i class="bi bi-chat-dots"></i>
    <?php
    // Calculate total unread messages count
    $totalUnread = 0;
    try {
        // Create read status table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS tbl_chat_read_status (
            buyer_id INT NOT NULL,
            seller_id INT NOT NULL,
            product_id INT NOT NULL,
            last_read_at DATETIME NOT NULL,
            PRIMARY KEY (buyer_id, seller_id, product_id),
            INDEX idx_buyer (buyer_id),
            INDEX idx_conversation (buyer_id, seller_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Get all unique conversations
        $stmt = $conn->prepare("
            SELECT DISTINCT seller_id, product_id 
            FROM tbl_chat_ukmart 
            WHERE buyer_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($conversations as $conv) {
            $unread_stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM tbl_chat_ukmart
                WHERE buyer_id = ? 
                AND seller_id = ? 
                AND product_id = ?
                AND sender_type = 'seller'
                AND timestamp > COALESCE(
                    (SELECT last_read_at FROM tbl_chat_read_status 
                     WHERE buyer_id = ? AND seller_id = ? AND product_id = ?),
                    '1970-01-01 00:00:00'
                )
            ");
            $unread_stmt->execute([
                $_SESSION['user_id'], 
                $conv['seller_id'], 
                $conv['product_id'],
                $_SESSION['user_id'],
                $conv['seller_id'],
                $conv['product_id']
            ]);
            $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
            $totalUnread += isset($unread_result['unread_count']) ? (int)$unread_result['unread_count'] : 0;
        }
    } catch (Exception $e) {
        error_log("Error calculating chat count: " . $e->getMessage());
        $totalUnread = 0;
    }
    
    // Always display badge element (hidden if 0) for JavaScript updates
    $badgeText = $totalUnread > 99 ? '99+' : ($totalUnread > 0 ? $totalUnread : '0');
    $badgeStyle = $totalUnread > 0 ? '' : 'display: none;';
    echo "<span class='chat-notification-badge' id='chatNotifBadge' style='$badgeStyle'>$badgeText</span>";
    ?>
</button>

<a href="cart.php" class="btn-icon position-relative" style="font-size: 1.5rem; margin-left: 15px;">
  <i class="bi bi-cart3"></i>
  <?php
  // Cart count logic
  $cartCount = 0;
  try {
      $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = :uid");
      $stmt->bindParam(':uid', $_SESSION['user_id']);
      $stmt->execute();
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $cartCount = isset($row['total']) && $row['total'] !== null ? (int)$row['total'] : 0;
  } catch (Exception $e) {
      error_log("Error calculating cart count: " . $e->getMessage());
      $cartCount = 0;
  }
  
  // Always display badge element (hidden if 0) for JavaScript updates
  $badgeText = $cartCount > 99 ? '99+' : ($cartCount > 0 ? $cartCount : '0');
  $badgeStyle = $cartCount > 0 ? '' : 'display: none;';
  echo "<span class='cart-badge' id='cartBadge' style='$badgeStyle'>$badgeText</span>";
  ?>
</a>

      </div>
    </div>
  </div>
</nav>

<!-- CHAT POPUP OVERLAY -->
<div class="chat-popup-overlay" id="chatPopupOverlay"></div>

<!-- CHAT POPUP CONTAINER -->
<div class="chat-popup-container" id="chatPopupContainer">
    <!-- CONVERSATION LIST VIEW -->
    <div id="chatListView">
        <input type="hidden" id="buyerId" value="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>">
        <div class="chat-popup-header">
            <h6><i class="bi bi-chat-dots me-2"></i>My Conversations</h6>
            <button class="chat-close-btn" id="closeChatPopup">
                <i class="bi bi-x"></i>
            </button>
        </div>
        
        <div class="chat-list-container" id="chatListContainer">
            <div class="no-chats-message">
                <i class="bi bi-chat-dots"></i>
                <p>Loading conversations...</p>
            </div>
        </div>
    </div>

    <!-- CHAT VIEW -->
    <div id="chatView" style="display: none; flex-direction: column; overflow: hidden;">
        <div class="chat-popup-header">
            <button class="chat-back-btn" id="backToList">
                <i class="bi bi-arrow-left"></i>
            </button>
            <h6 id="chatHeaderTitle">Chat with Seller</h6>
            <button class="chat-close-btn" id="closeChatView">
                <i class="bi bi-x"></i>
            </button>
        </div>

        <div class="chat-status-bar" id="chatStatusBar">
            <span class="status-badge status-pending" id="chatStatusBadge">⏳ Pending</span>
        </div>

        <div class="chat-messages-container" id="chatMessagesContainer">
            <div class="no-messages-message">
                <i class="bi bi-chat-dots"></i>
                <p>Loading messages...</p>
            </div>
        </div>

        <div class="chat-input-container" id="chatInputContainer">
            <div class="chat-input-form">
                <input type="text" id="chatMessageInput" class="form-control" placeholder="Type a message..." autocomplete="off">
                <button id="sendChatMessage" class="btn btn-primary">Send</button>
            </div>
            <form id="receiptUploadForm" method="post" enctype="multipart/form-data" class="receipt-upload-form" style="display: none;">
                <input type="file" name="receipt" id="receiptFileInput" class="form-control" accept="image/*" required>
                <button type="submit" class="btn btn-info">Upload Receipt</button>
            </form>
            <button id="toggleReceiptUpload" class="btn btn-outline-secondary btn-sm">📎 Receipt</button>
        </div>

        <!-- REVIEW SECTION -->
        <div class="chat-review-container" id="chatReviewContainer" style="display: none;">
            <div class="text-center mt-3">
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reviewModal">
                    ⭐ Review the Seller
                </button>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="reviewForm">
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
          <button type="submit" class="btn btn-success">Submit Review</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
// Live Search Functionality
const searchInput = document.getElementById("searchInput");
if (searchInput) {
    const resultBox = document.getElementById("liveSearchResults");
    let selectedIndex = -1;
    let recent = [];

    // Fetch recent searches initially
    async function fetchRecent() {
        try {
            const res = await fetch('live_search.php?q=');
            const data = await res.json();
            recent = data.recent || [];
        } catch(e) {
            console.error("Failed to fetch recent searches:", e);
        }
    }
    fetchRecent();

    async function fetchSearch(query) {
        if (!query) return { products: [], recent: recent };
        try {
            const res = await fetch(`live_search.php?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            return data;
        } catch(e) {
            console.error("Failed to fetch search:", e);
            return { products: [], recent: recent };
        }
    }

    function renderResults(productsList, recentList, query) {
        let html = "";

        if (!query && recentList.length > 0) {
            html += `<div class="p-2"><strong>Recent Searches</strong></div>`;
            recentList.forEach((term, i) => {
                html += `<div class="list-group-item recent-item" data-value="${term}" data-index="${i}">
                            <i class="bi bi-clock-history text-muted me-2"></i> ${term}
                         </div>`;
            });
        }

        productsList.forEach((p, i) => {
            const regex = new RegExp(`(${query})`, "gi");
            const highlighted = p.name.replace(regex, "<b>$1</b>");
            html += `<a href="product_details.php?name=${encodeURIComponent(p.name)}"
                       class="list-group-item d-flex align-items-center search-item"
                       data-index="${i}">
                        <img src="${p.image}" width="45" height="45" class="me-3 rounded">
                        <div>
                            <div>${highlighted}</div>
                            <small class="text-muted">${p.category} • RM ${p.price}</small>
                        </div>
                    </a>`;
        });

        resultBox.innerHTML = html;
        resultBox.style.display = html ? "block" : "none";

        // Add click events for recent items
        document.querySelectorAll(".recent-item").forEach(item => {
            item.onclick = () => {
                searchInput.value = item.dataset.value;
                searchInput.dispatchEvent(new Event("input"));
                resultBox.style.display = "none";
            };
        });
    }

    searchInput.addEventListener("input", async function () {
        const query = this.value.trim();
        selectedIndex = -1;

        const data = await fetchSearch(query);
        renderResults(data.products, data.recent, query);
    });

    // Arrow key navigation
    searchInput.addEventListener("keydown", function(e) {
        const items = resultBox.querySelectorAll(".search-item, .recent-item");
        if (!items.length) return;

        if (e.key === "ArrowDown") {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            highlightItem(items, selectedIndex);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            highlightItem(items, selectedIndex);
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
                items[selectedIndex].click();
            }
        }
    });

    searchInput.addEventListener("focus", async function () {
        const query = this.value.trim();
        if (!query) {
            // show recent searches
            renderResults([], recent, '');
        }
    });

    function highlightItem(items, index) {
        items.forEach((el, i) => el.classList.toggle("active", i === index));
        if (items[index]) items[index].scrollIntoView({ block: "nearest" });
    }

    // Hide results when clicking outside
    document.addEventListener("click", function (e) {
        if (!searchInput.contains(e.target) && !resultBox.contains(e.target)) {
            resultBox.style.display = "none";
        }
    });
}

// Chat Popup Functionality
const openChatBtn = document.getElementById('openChatPopup');
if (openChatBtn) {
    const closeChatBtn = document.getElementById('closeChatPopup');
    const closeChatViewBtn = document.getElementById('closeChatView');
    const backToListBtn = document.getElementById('backToList');
    const chatOverlay = document.getElementById('chatPopupOverlay');
    const chatContainer = document.getElementById('chatPopupContainer');
    const chatListContainer = document.getElementById('chatListContainer');
    
    let currentChat = null;
    let lastMessageCount = 0;
    let currentStatus = 'pending';
    let lastBadgeUpdate = 0; // Track last badge update to prevent interference

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

    // Open chat popup
    openChatBtn.addEventListener('click', function() {
        chatOverlay.style.display = 'block';
        chatContainer.style.display = 'flex';
        document.getElementById('chatListView').style.display = 'block';
        document.getElementById('chatView').style.display = 'none';
        // Update badge count when opening popup (in case messages were read elsewhere)
        fetch('get_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadBadge(data.conversations || []);
                }
            })
            .catch(error => console.error('Error updating badge:', error));
        loadChatConversations();
    });

    // Close chat popup
    function closeChatPopup() {
        chatOverlay.style.display = 'none';
        chatContainer.style.display = 'none';
        currentChat = null;
        // Update badge when popup closes (in case messages were read)
        fetch('get_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadBadge(data.conversations || []);
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }

    if (closeChatBtn) closeChatBtn.addEventListener('click', closeChatPopup);
    if (closeChatViewBtn) closeChatViewBtn.addEventListener('click', closeChatPopup);
    if (chatOverlay) chatOverlay.addEventListener('click', closeChatPopup);

    // Back to list
    if (backToListBtn) {
        backToListBtn.addEventListener('click', function() {
            document.getElementById('chatView').style.display = 'none';
            document.getElementById('chatListView').style.display = 'block';
            currentChat = null;
            // Reload conversations to update unread counts and badge
            loadChatConversations();
            // Also update badge immediately
            fetch('get_chat_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadBadge(data.conversations || []);
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        });
    }

    // Load chat conversations
    function loadChatConversations() {
        fetch('get_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.conversations.length > 0) {
                    displayConversations(data.conversations);
                    updateUnreadBadge(data.conversations);
                    lastBadgeUpdate = Date.now();
                } else {
                    updateUnreadBadge([]);
                    lastBadgeUpdate = Date.now();
                    if (chatListContainer) {
                        chatListContainer.innerHTML = `
                            <div class="no-chats-message">
                                <i class="bi bi-chat-dots"></i>
                                <p>No conversations yet</p>
                                <small class="text-muted">Start shopping to chat with sellers!</small>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading conversations:', error);
                updateUnreadBadge([]);
                if (chatListContainer) {
                    chatListContainer.innerHTML = `
                        <div class="no-chats-message">
                            <i class="bi bi-exclamation-circle"></i>
                            <p>Failed to load conversations</p>
                        </div>
                    `;
                }
            });
    }

    // Update unread badge count
    function updateUnreadBadge(conversations) {
        // Calculate total unread messages (sum of all unread_count values)
        const totalUnread = conversations.reduce((sum, conv) => sum + (parseInt(conv.unread_count) || 0), 0);
        const badge = document.getElementById('chatNotifBadge');
        
        console.log('Updating badge with conversations:', conversations);
        console.log('Total unread:', totalUnread);
        
        if (badge) {
            // Remove any inline style that might interfere
            badge.removeAttribute('style');
            
            if (totalUnread > 0) {
                badge.textContent = totalUnread > 99 ? '99+' : totalUnread;
                badge.style.display = 'flex'; // Use flex to match CSS
                console.log('Badge updated to show:', badge.textContent);
            } else {
                // Hide badge when count is 0 (all messages seen)
                badge.style.display = 'none';
                badge.textContent = '0';
                console.log('Badge hidden (no unread messages)');
            }
        } else {
            console.error('Badge element not found!');
        }
    }
    
    // Make updateUnreadBadge available globally for other pages
    window.updateUnreadBadge = updateUnreadBadge;

    function displayConversations(conversations) {
        let html = '';
        
        conversations.forEach(conv => {
            const unreadCount = parseInt(conv.unread_count) || 0;
            const isUnread = unreadCount > 0;
            const unreadClass = isUnread ? ' unread' : '';
            const unreadBadge = unreadCount > 0 
                ? `<span class="chat-unread-badge">${unreadCount > 99 ? '99+' : unreadCount}</span>` 
                : '';
            
            html += `
                <div class="chat-conversation-item${unreadClass}" data-seller-name="${escapeHtml(conv.seller_name)}" onclick="openChat(${conv.seller_id}, ${conv.product_id})">
                    <div class="chat-avatar">
                        <img src="${conv.profile_pic}" alt="${escapeHtml(conv.seller_name)}" onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\\'color:white; font-weight:600;\\'>${escapeHtml(conv.seller_name).charAt(0).toUpperCase()}</span>';">
                    </div>
                    <div class="chat-info">
                        <div class="chat-name">${escapeHtml(conv.seller_name)}</div>
                        <div class="chat-last-message">
                            ${conv.last_message_type === 'qr' ? '📷 QR Code' : 
                              conv.last_message_type === 'receipt' ? '📷 Receipt' : 
                              conv.last_message_type === 'confirmation' ? '✓ Order Confirmed' : 
                              escapeHtml(conv.last_message)}
                        </div>
                    </div>
                    <div class="chat-meta">
                        <div class="chat-time">${formatTime(conv.last_message_time)}</div>
                        ${unreadBadge}
                    </div>
                </div>
            `;
        });
        
        if (chatListContainer) chatListContainer.innerHTML = html;
    }

    function openChat(sellerId, productId, sellerName = null) {
        const item = event.target.closest('.chat-conversation-item');
        const name = sellerName || (item ? item.dataset.sellerName : 'Seller');
        
        // Show popup if not already visible
        const chatOverlay = document.getElementById('chatPopupOverlay');
        const chatContainer = document.getElementById('chatPopupContainer');
        if (chatOverlay.style.display !== 'block') {
            chatOverlay.style.display = 'block';
            chatContainer.style.display = 'flex';
        }
        
        // Switch to chat view
        document.getElementById('chatListView').style.display = 'none';
        document.getElementById('chatView').style.display = 'flex';
        
        // Store current chat info
        currentChat = { sellerId, productId, sellerName: name };
        
        // Update header with seller name
        document.getElementById('chatHeaderTitle').textContent = `Chat with ${name}`;
        
        // Mark messages as read FIRST (before loading messages)
        markChatAsRead(sellerId, productId);
        
        // Load chat messages
        loadChatMessages(sellerId, productId);
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

    // Load chat messages
    function loadChatMessages(sellerId, productId) {
        const buyerId = document.getElementById('buyerId').value;
        
        fetch(`fetch_messages.php?buyer=${buyerId}&seller=${sellerId}&product=${productId}`)
            .then(res => res.json())
            .then(data => {
                if (data.length !== lastMessageCount) {
                    lastMessageCount = data.length;
                    
                    // Get latest status
                    let latestStatus = "pending";
                    if (data.length > 0) {
                        latestStatus = data[data.length - 1].status || "pending";
                    }
                    updateChatStatusDisplay(latestStatus);
                    
                    displayChatMessages(data);
                }
            })
            .catch(err => console.error("Error loading messages:", err));
    }

    // Mark chat as read
    function markChatAsRead(sellerId, productId) {
        console.log('Marking chat as read for seller:', sellerId, 'product:', productId);
        fetch(`mark_chat_read.php?seller_id=${sellerId}&product_id=${productId}`)
            .then(res => res.json())
            .then(data => {
                console.log('Mark as read response:', data);
                if (data.success) {
                    console.log('Messages marked as read successfully');
                    // Update last badge update timestamp to prevent interference
                    lastBadgeUpdate = Date.now();
                    
                    // Immediately update badge count - use a delay to ensure DB is updated
                    setTimeout(() => {
                        fetch('get_chat_list.php')
                            .then(response => response.json())
                            .then(data => {
                                console.log('Updated chat list after marking as read:', data);
                                if (data.success) {
                                    updateUnreadBadge(data.conversations || []);
                                    // Also reload conversations list to update unread badges in the list
                                    const chatListView = document.getElementById('chatListView');
                                    if (chatListView && chatListView.style.display !== 'none') {
                                        displayConversations(data.conversations || []);
                                    }
                                }
                            })
                            .catch(err => console.error('Error updating badge:', err));
                    }, 300); // Delay to ensure DB update is complete
                } else {
                    console.error('Failed to mark messages as read:', data.error);
                }
            })
            .catch(err => console.error('Error marking messages as read:', err));
    }

    // Update chat status display
    function updateChatStatusDisplay(status) {
        if (status !== currentStatus) {
            currentStatus = status;
            const info = statusInfo[status] || { icon: status, description: "Unknown status", class: "status-pending" };
            
            const statusBadge = document.getElementById('chatStatusBadge');
            if (statusBadge) {
                statusBadge.className = `status-badge ${info.class}`;
                statusBadge.textContent = info.icon;
            }

            // Show review button if completed (or paid for testing)
            const chatReviewContainer = document.getElementById('chatReviewContainer');
            if (chatReviewContainer) {
                chatReviewContainer.style.display = (status === 'completed' || status === 'paid') ? 'block' : 'none';
            }
        }
    }

    // Display chat messages
    function displayChatMessages(messages) {
        const container = document.getElementById('chatMessagesContainer');
        if (!container) return;

        if (messages.length === 0) {
            container.innerHTML = `
                <div class="no-messages-message">
                    <i class="bi bi-chat-dots"></i>
                    <p>No messages yet. Start chatting below.</p>
                </div>
            `;
            return;
        }

        let html = '';
        messages.forEach(msg => {
            const isBuyer = msg.sender_type === 'buyer';
            const bubbleClass = isBuyer ? 'buyer-msg' : (msg.message_type === 'status_update' ? 'status-msg' : 'seller-msg');
            const itemClass = isBuyer ? 'buyer' : 'seller';

            let content = '';
            if (msg.message_type === 'receipt' || msg.message_type === 'qr') {
                content = `
                    <img src='${escapeHtml(msg.file_path)}?t=${Date.now()}' 
                         class='img-fluid rounded chat-message-image' 
                         style='max-width:200px;'
                         loading="lazy">
                    <p class="small mb-0">${escapeHtml(msg.message)}</p>
                `;
            } else if (msg.message_type === 'confirmation') {
                content = `<p class="text-success fw-bold mb-0">✔ Order Confirmed</p>`;
            } else if (msg.message_type === 'status_update') {
                content = `<p class="mb-0">📋 ${escapeHtml(msg.message)}</p>`;
            } else {
                content = escapeHtml(msg.message);
            }

            html += `
                <div class="chat-message-item ${itemClass}">
                    <div class="chat-message-bubble ${bubbleClass}">
                        ${content}
                        <div class="chat-message-timestamp">${escapeHtml(msg.timestamp)}</div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    }

    // Send message
    function sendChatMessage() {
        const input = document.getElementById('chatMessageInput');
        const message = input.value.trim();
        if (!message || !currentChat) return;

        const buyerId = document.getElementById('buyerId').value;
        const btn = document.getElementById('sendChatMessage');
        btn.disabled = true;

        fetch("sent_message_ajax.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                message: message,
                buyer_id: buyerId,
                seller_id: currentChat.sellerId,
                product_id: currentChat.productId,
                sender_type: "buyer"
            })
        })
        .then(res => res.text())
        .then(data => {
            console.log("Server response:", data);
            if (data.includes("OK")) {
                input.value = "";
                lastMessageCount = 0;
                loadChatMessages(currentChat.sellerId, currentChat.productId);
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
    }

    // Event listeners for chat input
    const chatMessageInput = document.getElementById('chatMessageInput');
    const sendChatMessageBtn = document.getElementById('sendChatMessage');
    const toggleReceiptBtn = document.getElementById('toggleReceiptUpload');
    const receiptForm = document.getElementById('receiptUploadForm');

    if (sendChatMessageBtn) {
        sendChatMessageBtn.addEventListener('click', sendChatMessage);
    }

    if (chatMessageInput) {
        chatMessageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    if (toggleReceiptBtn && receiptForm) {
        toggleReceiptBtn.addEventListener('click', function() {
            const isVisible = receiptForm.style.display !== 'none';
            receiptForm.style.display = isVisible ? 'none' : 'flex';
        });
    }

    // Handle receipt upload
    if (receiptForm) {
        receiptForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('buyer_id', document.getElementById('buyerId').value);
            formData.append('seller_id', currentChat.sellerId);
            formData.append('product_id', currentChat.productId);
            formData.append('send_receipt', '1');

            fetch('chatbuyer.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                if (data.includes("OK")) {
                    // Success, reload messages
                    lastMessageCount = 0;
                    loadChatMessages(currentChat.sellerId, currentChat.productId);
                    // Hide receipt form
                    receiptForm.style.display = 'none';
                    // Clear file input
                    document.getElementById('receiptFileInput').value = '';
                } else {
                    alert('Error uploading receipt');
                }
            })
            .catch(err => {
                console.error('Error uploading receipt:', err);
                alert('Failed to upload receipt');
            });
        });
    }

    // Handle review
    const reviewForm = document.getElementById('reviewForm');
    const chatReviewContainer = document.getElementById('chatReviewContainer');

    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('buyer_id', document.getElementById('buyerId').value);
            formData.append('seller_id', currentChat.sellerId);
            formData.append('product_id', currentChat.productId);
            formData.append('submit_review', '1');

            fetch('chatbuyer.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                console.log('Review submission response:', data); // Debug log
                if (data.includes("OK")) {
                    // Success, reload messages
                    lastMessageCount = 0;
                    loadChatMessages(currentChat.sellerId, currentChat.productId);
                    // Hide review container
                    chatReviewContainer.style.display = 'none';
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
                    if (modal) modal.hide();
                    // Clear form
                    reviewForm.reset();
                } else {
                    alert('Error submitting review: ' + data);
                }
            })
            .catch(err => {
                console.error('Error submitting review:', err);
                alert('Failed to submit review');
            });
        });
    }

    // Auto-load messages when in chat view
    setInterval(() => {
        if (currentChat && document.getElementById('chatView').style.display === 'flex') {
            loadChatMessages(currentChat.sellerId, currentChat.productId);
            // Also ensure messages are marked as read periodically while viewing
            markChatAsRead(currentChat.sellerId, currentChat.productId);
        }
    }, 2000);

    // Load conversations on page load to show unread count
    loadChatConversations();

    // Check for updates from other pages (like chatbuyer.php) via sessionStorage - every 500ms for faster updates
    setInterval(() => {
        const lastUpdate = sessionStorage.getItem('chatLastUpdate');
        const markedRead = sessionStorage.getItem('chatMarkedRead');
        if (lastUpdate && (Date.now() - parseInt(lastUpdate)) < 10000) {
            // Recent update detected, refresh badge immediately
            fetch('get_chat_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadBadge(data.conversations || []);
                        // Clear the flag after updating
                        if (markedRead === 'true') {
                            sessionStorage.removeItem('chatMarkedRead');
                        }
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }
    }, 500); // Check every 500ms for faster updates from other pages

    // Update badge count faster (every 2 seconds)
    setInterval(() => {
        // Only update if it's been at least 1 second since last manual update
        const now = Date.now();
        if (now - lastBadgeUpdate < 1000) {
            return; // Skip this update, manual update just happened
        }
        
        // Always update badge count, but only reload popup content if it's open
        if (chatContainer && chatContainer.style.display === 'flex') {
            loadChatConversations();
        } else {
            // Just update badge count in background
            fetch('get_chat_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadBadge(data.conversations || []);
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }
    }, 1500); // Update every 1.5 seconds for faster response
    
    // Update badge when page becomes visible (user returns from chat page)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Page is now visible, update badge immediately
            fetch('get_chat_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadBadge(data.conversations || []);
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }
    });
    
    // Also update badge when window gains focus
    window.addEventListener('focus', function() {
        fetch('get_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadBadge(data.conversations || []);
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    });
    
    // Check sessionStorage on page load to see if messages were marked as read
    window.addEventListener('load', function() {
        const markedRead = sessionStorage.getItem('chatMarkedRead');
        if (markedRead === 'true') {
            // Messages were marked as read, update badge immediately
            fetch('get_chat_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadBadge(data.conversations || []);
                        sessionStorage.removeItem('chatMarkedRead');
                    }
                })
                .catch(error => console.error('Error updating badge:', error));
        }
    });
    
    // Also check immediately (in case page is already loaded)
    const markedRead = sessionStorage.getItem('chatMarkedRead');
    if (markedRead === 'true') {
        fetch('get_chat_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadBadge(data.conversations || []);
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }
}

// ===== Cart Badge Update Function =====
function updateCartBadge() {
    fetch('get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('cartBadge');
            if (badge) {
                const cartCount = parseInt(data.count) || 0;
                if (cartCount > 0) {
                    badge.textContent = cartCount > 99 ? '99+' : cartCount;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                    badge.textContent = '0';
                }
            }
        })
        .catch(error => {
            console.error('Error updating cart badge:', error);
        });
}

// Update cart badge on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateCartBadge);
} else {
    updateCartBadge();
}

// Update cart badge periodically (every 3 seconds)
setInterval(updateCartBadge, 3000);

// Update cart badge when page becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        updateCartBadge();
    }
});

// Update cart badge when window gains focus
window.addEventListener('focus', updateCartBadge);

// Update cart badge when items are added/removed (listen for storage events)
window.addEventListener('storage', function(e) {
    if (e.key === 'cartUpdated') {
        updateCartBadge();
    }
});

// Make updateCartBadge available globally
window.updateCartBadge = updateCartBadge;
</script>
