<?php
/**
 * API kiểm tra đơn hàng mới cho Seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getConnection();
$shopId = (int)($_GET['shop_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$shopId) {
    echo json_encode(['error' => 'Invalid shop']);
    exit;
}

// Kiểm tra quyền sở hữu shop
$stmt = $pdo->prepare("SELECT id FROM shops WHERE id = ? AND user_id = ?");
$stmt->execute([$shopId, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Kiểm tra đơn hàng mới
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       WHERE o.shop_id = ? AND o.id > ? 
                       ORDER BY o.id DESC LIMIT 1");
$stmt->execute([$shopId, $lastId]);
$newOrder = $stmt->fetch();

$response = ['hasNew' => false];

if ($newOrder) {
    $response['hasNew'] = true;
    $response['order'] = [
        'id' => $newOrder['id'],
        'customer_name' => $newOrder['customer_name'],
        'customer_phone' => $newOrder['customer_phone'],
        'total_amount' => $newOrder['total_amount'],
        'status' => $newOrder['status'],
        'created_at' => $newOrder['created_at']
    ];
    
    // Cập nhật thống kê
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shop_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$shopId, $today]);
    $ordersToday = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shop_id = ? AND status IN ('pending', 'confirmed')");
    $stmt->execute([$shopId]);
    $pendingOrders = $stmt->fetch()['total'];
    
    $response['stats'] = [
        'orders_today' => $ordersToday,
        'pending_orders' => $pendingOrders
    ];
}

echo json_encode($response);
