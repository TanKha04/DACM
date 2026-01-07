<?php
/**
 * API cập nhật và lấy vị trí shipper realtime
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';

// POST: Shipper cập nhật vị trí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userRole !== 'shipper') {
        echo json_encode(['success' => false, 'message' => 'Chỉ shipper mới có thể cập nhật vị trí', 'debug' => ['role' => $userRole, 'session' => array_keys($_SESSION)]]);
        exit;
    }
    
    $lat = floatval($_POST['lat'] ?? 0);
    $lng = floatval($_POST['lng'] ?? 0);
    
    if (!$lat || !$lng) {
        echo json_encode(['success' => false, 'message' => 'Tọa độ không hợp lệ']);
        exit;
    }
    
    // Cập nhật vị trí shipper và thời gian cập nhật
    $stmt = $pdo->prepare("UPDATE shipper_info SET current_lat = ?, current_lng = ?, location_updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$lat, $lng, $userId]);
    
    if ($stmt->rowCount() === 0) {
        // Nếu chưa có record, tạo mới
        $stmt = $pdo->prepare("INSERT INTO shipper_info (user_id, current_lat, current_lng, location_updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE current_lat = ?, current_lng = ?, location_updated_at = NOW()");
        $stmt->execute([$userId, $lat, $lng, $lat, $lng]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật vị trí', 'time' => date('H:i:s')]);
    exit;
}

// GET: Khách hàng lấy vị trí shipper của đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
        exit;
    }
    
    // Kiểm tra đơn hàng thuộc về user này (customer hoặc shipper)
    $stmt = $pdo->prepare("SELECT o.*, s.latitude as shop_lat, s.longitude as shop_lng, s.name as shop_name, s.address as shop_address
                           FROM orders o 
                           JOIN shops s ON o.shop_id = s.id
                           WHERE o.id = ? AND (o.customer_id = ? OR o.shipper_id = ?)");
    $stmt->execute([$orderId, $userId, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }
    
    $response = [
        'success' => true,
        'order_status' => $order['status'],
        'shop' => [
            'name' => $order['shop_name'],
            'address' => $order['shop_address'],
            'lat' => floatval($order['shop_lat']),
            'lng' => floatval($order['shop_lng'])
        ],
        'delivery_address' => $order['delivery_address']
    ];
    
    // Nếu có shipper và đơn đang giao
    if ($order['shipper_id'] && in_array($order['status'], ['confirmed', 'preparing', 'ready', 'picked', 'delivering'])) {
        $stmt = $pdo->prepare("SELECT si.current_lat, si.current_lng, si.location_updated_at, u.name, u.phone 
                               FROM users u
                               LEFT JOIN shipper_info si ON si.user_id = u.id
                               WHERE u.id = ?");
        $stmt->execute([$order['shipper_id']]);
        $shipper = $stmt->fetch();
        
        if ($shipper) {
            // Tính thời gian từ lần cập nhật cuối
            $lastUpdate = $shipper['location_updated_at'] ? strtotime($shipper['location_updated_at']) : null;
            $secondsAgo = $lastUpdate ? (time() - $lastUpdate) : null;
            
            $response['shipper'] = [
                'name' => $shipper['name'],
                'phone' => $shipper['phone'],
                'lat' => $shipper['current_lat'] ? floatval($shipper['current_lat']) : null,
                'lng' => $shipper['current_lng'] ? floatval($shipper['current_lng']) : null,
                'has_location' => ($shipper['current_lat'] && $shipper['current_lng']) ? true : false,
                'last_update' => $shipper['location_updated_at'],
                'seconds_ago' => $secondsAgo,
                'is_online' => ($secondsAgo !== null && $secondsAgo < 60) // Online nếu cập nhật trong 60 giây
            ];
        }
    }
    
    echo json_encode($response);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
