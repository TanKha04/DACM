<?php
/**
 * Customer - Thông báo
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Đánh dấu đã đọc
if (isset($_GET['read'])) {
    $notifId = (int)$_GET['read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    header('Location: notifications.php');
    exit;
}

// Đánh dấu tất cả đã đọc
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    header('Location: notifications.php');
    exit;
}

// Xóa thông báo
if (isset($_GET['delete'])) {
    $notifId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    header('Location: notifications.php');
    exit;
}

// Lấy thông báo
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .notif-actions { display: flex; gap: 10px; }
        .notif-item { 
            display: flex; 
            gap: 15px; 
            padding: 20px; 
            background: white; 
            border-radius: 12px; 
            margin-bottom: 12px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .notif-item:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .notif-item.unread { 
            background: #fff8e1; 
            border-left-color: #ff6b35;
        }
        .notif-icon { 
            width: 50px; 
            height: 50px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 24px;
            flex-shrink: 0;
        }
        .notif-icon.order { background: #e3f2fd; }
        .notif-icon.system { background: #f3e5f5; }
        .notif-icon.promo { background: #fff3e0; }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 600; font-size: 16px; color: #2c3e50; margin-bottom: 5px; }
        .notif-message { color: #666; font-size: 14px; line-height: 1.6; }
        .notif-time { font-size: 12px; color: #999; margin-top: 8px; }
        .notif-actions-item { display: flex; gap: 8px; }
        .notif-btn { 
            padding: 5px 12px; 
            border-radius: 6px; 
            font-size: 12px; 
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .notif-btn-read { background: #e8f5e9; color: #27ae60; }
        .notif-btn-delete { background: #ffebee; color: #e74c3c; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 16px; }
        .empty-state .icon { font-size: 70px; margin-bottom: 20px; }
        .unread-badge { 
            background: #ff6b35; 
            color: white; 
            padding: 3px 10px; 
            border-radius: 15px; 
            font-size: 13px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <div class="notif-header">
            <h1>🔔 Thông báo <?php if ($unreadCount > 0): ?><span class="unread-badge"><?= $unreadCount ?> mới</span><?php endif; ?></h1>
            <?php if ($unreadCount > 0): ?>
            <div class="notif-actions">
                <a href="?read_all=1" class="btn-outline" style="padding: 10px 20px; border-radius: 8px;">✅ Đánh dấu tất cả đã đọc</a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="icon">🔔</div>
            <h2>Không có thông báo</h2>
            <p style="color: #999;">Bạn sẽ nhận được thông báo khi có cập nhật mới</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($notifications as $notif): ?>
        <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
            <div class="notif-icon <?= $notif['type'] ?>">
                <?= $notif['type'] === 'order' ? '📦' : ($notif['type'] === 'promo' ? '🎁' : '🔔') ?>
            </div>
            <div class="notif-content">
                <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notif-message"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                <div class="notif-time">📅 <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></div>
            </div>
            <div class="notif-actions-item">
                <?php if (!$notif['is_read']): ?>
                <a href="?read=<?= $notif['id'] ?>" class="notif-btn notif-btn-read">✓ Đã đọc</a>
                <?php endif; ?>
                <a href="?delete=<?= $notif['id'] ?>" class="notif-btn notif-btn-delete" onclick="return confirm('Xóa thông báo này?')">✕</a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
