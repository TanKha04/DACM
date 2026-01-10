<?php
/**
 * Customer - Chat với shipper theo đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

// Kiểm tra đơn hàng thuộc về khách hàng này
$stmt = $pdo->prepare("SELECT o.*, u.id as shipper_id, u.name as shipper_name, u.phone as shipper_phone
                       FROM orders o 
                       LEFT JOIN users u ON o.shipper_id = u.id 
                       WHERE o.id = ? AND o.customer_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order || !$order['shipper_id']) {
    header('Location: orders.php');
    exit;
}

$shipperId = $order['shipper_id'];

// Xử lý gửi tin nhắn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $userId, $shipperId, $message]);
        
        // Gửi thông báo cho shipper
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
        $notifStmt->execute([$shipperId, '💬 Tin nhắn từ khách hàng', "Khách hàng đơn #$orderId đã gửi tin nhắn cho bạn."]);
    }
    header("Location: chat_shipper.php?order_id=$orderId");
    exit;
}

// Lấy tin nhắn chỉ giữa customer và shipper
$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM order_messages m JOIN users u ON m.sender_id = u.id WHERE m.order_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)) ORDER BY m.created_at ASC");
$stmt->execute([$orderId, $userId, $shipperId, $shipperId, $userId]);
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
    <title>Chat với Shipper - Đơn #<?= $orderId ?></title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .chat-container { max-width: 600px; margin: 0 auto; height: calc(100vh - 200px); display: flex; flex-direction: column; }
        .chat-header { background: white; padding: 15px 20px; border-radius: 12px 12px 0 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 15px; }
        .chat-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #27ae60, #2ecc71); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: bold; }
        .chat-info h3 { margin: 0 0 5px 0; }
        .chat-info p { margin: 0; color: #7f8c8d; font-size: 14px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #f5f5f5; display: flex; flex-direction: column; gap: 10px; }
        .message { max-width: 75%; padding: 12px 16px; border-radius: 18px; }
        .message.sent { align-self: flex-end; background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; border-bottom-right-radius: 4px; }
        .message.received { align-self: flex-start; background: white; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message-time { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .chat-input { background: white; padding: 15px; border-radius: 0 0 12px 12px; display: flex; gap: 10px; }
        .chat-input input { flex: 1; padding: 12px 20px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
        .chat-input input:focus { border-color: #ff6b35; }
        .chat-input button { background: #ff6b35; color: white; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; }
        .chat-input button:hover { background: #e55a2b; }
        .empty-chat { text-align: center; color: #999; padding: 50px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <div style="margin-bottom: 15px;">
            <a href="order_detail.php?id=<?= $orderId ?>" class="btn-secondary" style="text-decoration: none; padding: 10px 20px; border-radius: 8px;">← Quay lại đơn hàng</a>
        </div>
        
        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-avatar">🛵</div>
                <div class="chat-info">
                    <h3><?= htmlspecialchars($order['shipper_name']) ?></h3>
                    <p>Shipper đơn hàng #<?= $orderId ?></p>
                </div>
                <a href="tel:<?= $order['shipper_phone'] ?>" class="btn-primary" style="margin-left: auto; text-decoration: none; padding: 10px 20px; border-radius: 20px;">📞 Gọi</a>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <p style="font-size: 40px;">💬</p>
                    <p>Chưa có tin nhắn</p>
                    <p style="font-size: 13px;">Gửi tin nhắn để liên hệ với shipper</p>
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
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <script>
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
        setTimeout(function() { location.reload(); }, 10000);
    </script>
</body>
</html>
