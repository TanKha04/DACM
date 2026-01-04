<?php
/**
 * Shipper - Cập nhật trạng thái đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

$allowedStatuses = ['picked', 'delivering', 'delivered'];

if (!$orderId || !in_array($newStatus, $allowedStatuses)) {
    header('Location: dashboard.php');
    exit;
}

// Kiểm tra đơn thuộc về shipper này
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND shipper_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if ($order) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    
    // Gửi thông báo cho khách hàng
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
    
    if ($newStatus === 'delivering') {
        $notifStmt->execute([$order['customer_id'], '🚀 Đơn hàng đang được giao', "Đơn hàng #$orderId đang trên đường giao đến bạn!"]);
    }
    
    // Nếu đã giao xong
    if ($newStatus === 'delivered') {
        // Gửi thông báo cho khách hàng
        $notifStmt->execute([$order['customer_id'], '✅ Đơn hàng đã giao thành công!', "Đơn hàng #$orderId đã được giao. Cảm ơn bạn đã sử dụng dịch vụ!"]);
        
        // Gửi thông báo cho seller
        $sellerStmt = $pdo->prepare("SELECT user_id FROM shops WHERE id = ?");
        $sellerStmt->execute([$order['shop_id']]);
        $sellerId = $sellerStmt->fetchColumn();
        if ($sellerId) {
            $notifStmt->execute([$sellerId, '✅ Đơn hàng đã giao thành công', "Đơn hàng #$orderId đã được giao đến khách hàng."]);
        }
        
        // Cập nhật payment status
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed' WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // Cập nhật thống kê shipper
        $stmt = $pdo->prepare("UPDATE shipper_info SET total_deliveries = total_deliveries + 1, total_earnings = total_earnings + ? WHERE user_id = ?");
        $stmt->execute([$order['shipping_fee'], $userId]);
    }
}

header('Location: dashboard.php');
exit;
