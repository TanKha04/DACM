<?php
/**
 * API kiểm tra đơn hàng mới cho Shipper
 * Trả về tất cả đơn có sẵn (không giới hạn khoảng cách)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy vị trí hiện tại của shipper
$stmt = $pdo->prepare("SELECT current_lat, current_lng FROM shipper_info WHERE user_id = ?");
$stmt->execute([$userId]);
$shipperInfo = $stmt->fetch();

$shipperLat = $shipperInfo['current_lat'] ?? null;
$shipperLng = $shipperInfo['current_lng'] ?? null;

// Đếm tất cả đơn có sẵn (không giới hạn khoảng cách)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('confirmed', 'preparing', 'ready') AND shipper_id IS NULL");
$availableOrders = $stmt->fetch()['total'];

// Đếm đơn đã sẵn sàng (ready) - đây là đơn người bán đã chuẩn bị xong
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'ready' AND shipper_id IS NULL");
$readyOrders = $stmt->fetch()['total'];

// Đếm đơn đang giao của shipper này
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shipper_id = ? AND status IN ('picked', 'delivering')");
$stmt->execute([$userId]);
$activeOrders = $stmt->fetch()['total'];

// Lấy thông tin đơn mới nhất để hiển thị thông báo
$newOrder = null;
if ($shipperLat && $shipperLng) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.shipping_fee, s.name as shop_name,
               CASE 
                   WHEN s.latitude IS NOT NULL AND s.longitude IS NOT NULL 
                   THEN (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude))))
                   ELSE NULL 
               END AS distance
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id
        WHERE o.status IN ('confirmed', 'preparing', 'ready') 
        AND o.shipper_id IS NULL
        ORDER BY o.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$shipperLat, $shipperLng, $shipperLat]);
} else {
    $stmt = $pdo->query("
        SELECT o.id, o.shipping_fee, s.name as shop_name, NULL AS distance
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id
        WHERE o.status IN ('confirmed', 'preparing', 'ready') 
        AND o.shipper_id IS NULL
        ORDER BY o.created_at DESC
        LIMIT 1
    ");
}
$newOrder = $stmt->fetch();

echo json_encode([
    'available' => (int)$availableOrders,
    'ready' => (int)$readyOrders,
    'active' => (int)$activeOrders,
    'new_order' => $newOrder ? [
        'id' => $newOrder['id'],
        'shop_name' => $newOrder['shop_name'],
        'shipping_fee' => $newOrder['shipping_fee'],
        'distance' => $newOrder['distance'] ? round($newOrder['distance'], 1) : null
    ] : null
]);
