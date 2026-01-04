<?php
/**
 * Shipper nhận đơn hàng
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$orderId) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getConnection();

// Kiểm tra đơn hàng còn available không
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'ready' AND shipper_id IS NULL");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if ($order) {
    // Nhận đơn
    $stmt = $pdo->prepare("UPDATE orders SET shipper_id = ?, status = 'picked' WHERE id = ?");
    $stmt->execute([$userId, $orderId]);
}

header('Location: dashboard.php');
exit;
