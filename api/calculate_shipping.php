<?php
/**
 * API tính phí giao hàng theo khoảng cách
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maps_helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getConnection();

$shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
$userLat = floatval($_GET['lat'] ?? $_POST['lat'] ?? 0);
$userLng = floatval($_GET['lng'] ?? $_POST['lng'] ?? 0);
$subtotal = floatval($_GET['subtotal'] ?? $_POST['subtotal'] ?? 0);

if (!$shopId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu shop_id']);
    exit;
}

// Lấy vị trí shop
$stmt = $pdo->prepare("SELECT latitude, longitude, name, address FROM shops WHERE id = ?");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();

if (!$shop) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy cửa hàng']);
    exit;
}

// Nếu không có tọa độ user, lấy từ session hoặc database
if (!$userLat || !$userLng) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT lat, lng FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $userLat = floatval($user['lat'] ?? 0);
    $userLng = floatval($user['lng'] ?? 0);
}

// Lấy cấu hình phí ship
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$config = $stmt->fetch();

$shopLat = floatval($shop['latitude']);
$shopLng = floatval($shop['longitude']);

// Tính khoảng cách
if ($userLat && $userLng && $shopLat && $shopLng) {
    $distance = calculateDistance($userLat, $userLng, $shopLat, $shopLng);
} else {
    $distance = 3; // Mặc định 3km
}

// Tính phí ship
$shippingResult = calculateShippingFee($distance, $config, $subtotal);

// Ước tính thời gian giao hàng
$deliveryTime = estimateDeliveryTime($distance);

echo json_encode([
    'success' => true,
    'shop' => [
        'name' => $shop['name'],
        'address' => $shop['address'],
        'lat' => $shopLat,
        'lng' => $shopLng
    ],
    'user' => [
        'lat' => $userLat,
        'lng' => $userLng
    ],
    'distance_km' => round($distance, 2),
    'shipping_fee' => $shippingResult['fee'],
    'is_free_ship' => $shippingResult['is_free'],
    'is_peak_hour' => $shippingResult['is_peak'],
    'delivery_time' => $deliveryTime,
    'free_ship_min' => $config['free_ship_min'] ?? 200000
]);
