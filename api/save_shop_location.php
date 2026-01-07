<?php
/**
 * API lưu vị trí cửa hàng (cho seller)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Kiểm tra user có shop không
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa có cửa hàng']);
    exit;
}

$lat = floatval($_POST['lat'] ?? 0);
$lng = floatval($_POST['lng'] ?? 0);

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Tọa độ không hợp lệ']);
    exit;
}

// Lấy địa chỉ từ tọa độ (reverse geocoding)
$address = '';
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&accept-language=vi";
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => 'User-Agent: FastFood Delivery App'
    ]
];
$context = stream_context_create($opts);
$response = @file_get_contents($url, false, $context);
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['display_name'])) {
        $address = $data['display_name'];
    }
}

// Cập nhật vị trí shop
$stmt = $pdo->prepare("UPDATE shops SET latitude = ?, longitude = ?, address = ? WHERE id = ?");
$stmt->execute([$lat, $lng, $address ?: null, $shop['id']]);

echo json_encode([
    'success' => true, 
    'message' => 'Đã lưu vị trí cửa hàng',
    'address' => $address,
    'lat' => $lat,
    'lng' => $lng
]);
