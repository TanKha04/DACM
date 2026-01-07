<?php
/**
 * API lưu vị trí người dùng
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

// Cập nhật vị trí user
$stmt = $pdo->prepare("UPDATE users SET lat = ?, lng = ?, address = ? WHERE id = ?");
$stmt->execute([$lat, $lng, $address ?: null, $userId]);

// Cập nhật session
$_SESSION['user_lat'] = $lat;
$_SESSION['user_lng'] = $lng;

echo json_encode([
    'success' => true, 
    'message' => 'Đã lưu vị trí',
    'address' => $address
]);
