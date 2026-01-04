<?php
/**
 * Thông báo cho Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy thông báo
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .notif-list { max-width: 600px; margin: 40px auto; }
        .notif-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .notif-title { font-weight: bold; color: #ff6b35; margin-bottom: 8px; }
        .notif-time { color: #7f8c8d; font-size: 13px; margin-bottom: 6px; }
        .notif-message { font-size: 15px; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>🔔 Thông báo</h1>
        </div>
        <div class="notif-list">
            <?php if (empty($notifications)): ?>
            <div class="notif-card" style="text-align:center; color:#999;">Không có thông báo nào</div>
            <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-card">
                <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notif-time"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></div>
                <div class="notif-message"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
