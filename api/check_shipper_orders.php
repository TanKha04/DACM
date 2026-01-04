<?php
/**
 * API kiểm tra đơn hàng mới cho Shipper
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

// Đếm đơn có sẵn (chờ shipper nhận)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('confirmed', 'preparing', 'ready') AND shipper_id IS NULL");
$availableOrders = $stmt->fetch()['total'];

// Đếm đơn đang giao của shipper này
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shipper_id = ? AND status IN ('picked', 'delivering')");
$stmt->execute([$userId]);
$activeOrders = $stmt->fetch()['total'];

echo json_encode([
    'available' => (int)$availableOrders,
    'active' => (int)$activeOrders
]);
