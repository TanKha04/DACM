<?php
/**
 * Xử lý yêu cầu cấp quyền seller/shipper
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests.php');
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$requestId || !in_array($action, ['approve', 'reject'])) {
    header('Location: requests.php');
    exit;
}

$pdo = getConnection();

// Lấy thông tin request
$stmt = $pdo->prepare("SELECT * FROM role_requests WHERE id = ? AND status = 'pending'");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: requests.php');
    exit;
}

if ($action === 'approve') {
    // Cập nhật role cho user
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$request['requested_role'], $request['user_id']]);
    
    // Nếu là shipper, tạo record trong shipper_info
    if ($request['requested_role'] === 'shipper') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO shipper_info (user_id) VALUES (?)");
        $stmt->execute([$request['user_id']]);
    }
    
    // Cập nhật trạng thái request
    $stmt = $pdo->prepare("UPDATE role_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
    $stmt->execute([$requestId]);
} else {
    // Từ chối request
    $stmt = $pdo->prepare("UPDATE role_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
    $stmt->execute([$requestId]);
}

header('Location: dashboard.php');
exit;
