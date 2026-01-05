<?php
/**
 * Chat với Customer - Seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

// Lấy shop của seller
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Lấy thông tin đơn hàng và customer
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.id as customer_id 
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       WHERE o.id = ? AND o.shop_id = ?");
$stmt->execute([$orderId, $shop['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$customerId = $order['customer_id'];

// Gửi tin nhắn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $userId, $customerId, $message]);
    }
    header("Location: chat_customer.php?order_id=$orderId");
    exit;
}

// Đánh dấu đã đọc
$stmt = $pdo->prepare("UPDATE order_messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ?");
$stmt->execute([$orderId, $userId]);

// Lấy tin nhắn
$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name 
                       FROM order_messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE m.order_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                       ORDER BY m.created_at ASC");
$stmt->execute([$orderId, $userId, $customerId, $customerId, $userId]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat với <?= htmlspecialchars($order['customer_name']) ?> - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .chat-container { max-width: 600px; margin: 0 auto; }
        .chat-header { background: #4CAF50; color: white; padding: 15px 20px; border-radius: 12px 12px 0 0; display: flex; align-items: center; gap: 15px; }
        .chat-header .back { color: white; text-decoration: none; font-size: 20px; }
        .chat-header .info h3 { margin: 0; }
        .chat-header .info p { margin: 5px 0 0; font-size: 13px; opacity: 0.9; }
        .chat-messages { background: #f5f5f5; padding: 20px; height: 400px; overflow-y: auto; }
        .message { margin-bottom: 15px; display: flex; }
        .message.sent { justify-content: flex-end; }
        .message .bubble { max-width: 70%; padding: 12px 16px; border-radius: 18px; }
        .message.received .bubble { background: white; border-bottom-left-radius: 4px; }
        .message.sent .bubble { background: #4CAF50; color: white; border-bottom-right-radius: 4px; }
        .message .time { font-size: 11px; color: #999; margin-top: 5px; }
        .message.sent .time { text-align: right; }
        .chat-input { background: white; padding: 15px; border-radius: 0 0 12px 12px; border-top: 1px solid #eee; }
        .chat-input form { display: flex; gap: 10px; }
        .chat-input input { flex: 1; padding: 12px 16px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
        .chat-input button { padding: 12px 25px; background: #4CAF50; color: white; border: none; border-radius: 25px; cursor: pointer; }
        .empty-chat { text-align: center; padding: 50px; color: #999; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="chat-container">
            <div class="chat-header">
                <a href="order_detail.php?id=<?= $orderId ?>" class="back">←</a>
                <div class="info">
                    <h3>👤 <?= htmlspecialchars($order['customer_name']) ?></h3>
                    <p>Đơn hàng #<?= $orderId ?></p>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <p style="font-size: 40px;">💬</p>
                    <p>Chưa có tin nhắn nào</p>
                </div>
                <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                    <div>
                        <div class="bubble"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div class="time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="chat-input">
                <form method="POST">
                    <input type="text" name="message" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                    <button type="submit">Gửi</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
    setTimeout(() => location.reload(), 5000);
    </script>
</body>
</html>
