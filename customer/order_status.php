<?php
/**
 * API kiểm tra trạng thái đơn hàng (cho auto-refresh)
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
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status, shipper_id FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode([
    'status' => $order['status'],
    'has_shipper' => $order['shipper_id'] ? true : false
]);
