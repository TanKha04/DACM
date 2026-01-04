<?php
/**
 * Shipper - Chat với khách hàng theo đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

// Kiểm tra đơn hàng thuộc về shipper này
$stmt = $pdo->prepare("SELECT o.*, u.id as customer_id, u.name as customer_name, u.phone as customer_phone, u.avatar as customer_avatar
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       WHERE o.id = ? AND o.shipper_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

$customerId = $order['customer_id'];

// Xử lý gửi tin nhắn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $userId, $customerId, $message]);
        
        // Gửi thông báo cho khách
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
        $notifStmt->execute([$customerId, '💬 Tin nhắn từ Shipper', "Shipper đơn hàng #$orderId đã gửi tin nhắn cho bạn."]);
    }
    header("Location: chat_customer.php?order_id=$orderId");
    exit;
}

// Lấy tin nhắn
$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM order_messages m JOIN users u ON m.sender_id = u.id WHERE m.order_id = ? ORDER BY m.created_at ASC");
$stmt->execute([$orderId]);
$messages = $stmt->fetchAll();

// Đánh dấu đã đọc
$stmt = $pdo->prepare("UPDATE order_messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ?");
$stmt->execute([$orderId, $userId]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat với khách - Đơn #<?= $orderId ?></title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .chat-container { max-width: 600px; margin: 0 auto; height: calc(100vh - 120px); display: flex; flex-direction: column; }
        .chat-header { background: white; padding: 15px 20px; border-radius: 12px 12px 0 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; }
        .chat-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #3498db, #2980b9); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: bold; }
        .chat-info h3 { margin: 0 0 5px 0; }
        .chat-info p { margin: 0; color: #7f8c8d; font-size: 14px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #f5f5f5; display: flex; flex-direction: column; gap: 10px; }
        .message { max-width: 75%; padding: 12px 16px; border-radius: 18px; }
        .message.sent { align-self: flex-end; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; border-bottom-right-radius: 4px; }
        .message.received { align-self: flex-start; background: white; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message-time { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .chat-input { background: white; padding: 15px; border-radius: 0 0 12px 12px; display: flex; gap: 10px; }
        .chat-input input { flex: 1; padding: 12px 20px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
        .chat-input input:focus { border-color: #27ae60; }
        .chat-input button { background: #27ae60; color: white; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; }
        .chat-input button:hover { background: #219a52; }
        .empty-chat { text-align: center; color: #999; padding: 50px; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div style="margin-bottom: 15px;">
            <a href="dashboard.php" class="btn btn-secondary">← Quay lại</a>
        </div>
        
        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-avatar">
                    <?= strtoupper(substr($order['customer_name'], 0, 1)) ?>
                </div>
                <div class="chat-info">
                    <h3><?= htmlspecialchars($order['customer_name']) ?></h3>
                    <p>📞 <?= htmlspecialchars($order['customer_phone']) ?> | Đơn hàng #<?= $orderId ?></p>
                </div>
                <a href="tel:<?= $order['delivery_phone'] ?>" class="btn btn-primary" style="margin-left: auto;">📞 Gọi</a>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <p style="font-size: 40px;">💬</p>
                    <p>Chưa có tin nhắn</p>
                    <p style="font-size: 13px;">Gửi tin nhắn để liên hệ với khách hàng</p>
                </div>
                <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                    <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <div class="message-time"><?= date('H:i d/m', strtotime($msg['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="chat-input">
                <input type="text" name="message" placeholder="Nhập tin nhắn..." autocomplete="off" required autofocus>
                <button type="submit">Gửi</button>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
        // Auto refresh mỗi 10 giây
        setTimeout(function() { location.reload(); }, 10000);
    </script>
</body>
</html>
