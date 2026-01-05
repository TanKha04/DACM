<?php
/**
 * Thông báo cho Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Đánh dấu đã đọc nếu có request
if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    header('Location: notifications.php');
    exit;
}

// Lấy thông báo
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// Đếm chưa đọc
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

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
        .notif-container { max-width: 800px; margin: 0 auto; }
        
        .notif-header {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-header-info h2 { font-size: 24px; margin-bottom: 8px; }
        .notif-header-info p { opacity: 0.9; font-size: 14px; }
        .notif-stats {
            display: flex;
            gap: 20px;
        }
        .notif-stat {
            text-align: center;
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 15px;
        }
        .notif-stat-value { font-size: 28px; font-weight: bold; }
        .notif-stat-label { font-size: 12px; opacity: 0.9; }
        
        .notif-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .notif-filter {
            display: flex;
            gap: 10px;
        }
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            background: white;
            color: #666;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filter-btn.active, .filter-btn:hover {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
        }
        .mark-read-btn {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .mark-read-btn:hover { background: #059669; }
        
        .notif-list { display: flex; flex-direction: column; gap: 15px; }
        
        .notif-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            display: flex;
            gap: 20px;
            align-items: flex-start;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .notif-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.12);
        }
        .notif-card.unread {
            border-left-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff, #f5f3ff);
        }
        .notif-card.unread::before {
            content: '';
            position: absolute;
            top: 20px;
            right: 20px;
            width: 10px;
            height: 10px;
            background: #3b82f6;
            border-radius: 50%;
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
        .notif-icon.order { background: linear-gradient(135deg, #fef3c7, #fde68a); }
        .notif-icon.delivery { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
        .notif-icon.alert { background: linear-gradient(135deg, #fee2e2, #fecaca); }
        .notif-icon.info { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        
        .notif-content { flex: 1; }
        .notif-title {
            font-weight: 600;
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notif-badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        .notif-badge.new { background: #3b82f6; color: white; }
        .notif-message {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .notif-time {
            color: #9ca3af;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notif-empty {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .notif-empty-icon { font-size: 60px; margin-bottom: 20px; }
        .notif-empty-text { color: #9ca3af; font-size: 16px; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-card { animation: slideIn 0.3s ease forwards; }
        .notif-card:nth-child(1) { animation-delay: 0.05s; }
        .notif-card:nth-child(2) { animation-delay: 0.1s; }
        .notif-card:nth-child(3) { animation-delay: 0.15s; }
        .notif-card:nth-child(4) { animation-delay: 0.2s; }
        .notif-card:nth-child(5) { animation-delay: 0.25s; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>🔔 Thông báo</h1>
            <span style="color: #7f8c8d;"><?= date('d/m/Y H:i') ?></span>
        </div>
        
        <div class="notif-container">
            <!-- Header Stats -->
            <div class="notif-header">
                <div class="notif-header-info">
                    <h2>📬 Trung tâm thông báo</h2>
                    <p>Cập nhật mới nhất về đơn hàng và hoạt động của bạn</p>
                </div>
                <div class="notif-stats">
                    <div class="notif-stat">
                        <div class="notif-stat-value"><?= count($notifications) ?></div>
                        <div class="notif-stat-label">Tổng thông báo</div>
                    </div>
                    <div class="notif-stat">
                        <div class="notif-stat-value"><?= $unreadCount ?></div>
                        <div class="notif-stat-label">Chưa đọc</div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="notif-actions">
                <div class="notif-filter">
                    <button class="filter-btn active" onclick="filterNotif('all')">📋 Tất cả</button>
                    <button class="filter-btn" onclick="filterNotif('unread')">🔵 Chưa đọc</button>
                    <button class="filter-btn" onclick="filterNotif('order')">📦 Đơn hàng</button>
                </div>
                <?php if ($unreadCount > 0): ?>
                <a href="?mark_read=all" class="mark-read-btn">✓ Đánh dấu tất cả đã đọc</a>
                <?php endif; ?>
            </div>
            
            <!-- Notification List -->
            <div class="notif-list">
                <?php if (empty($notifications)): ?>
                <div class="notif-empty">
                    <div class="notif-empty-icon">🔔</div>
                    <div class="notif-empty-text">Bạn chưa có thông báo nào</div>
                </div>
                <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    // Xác định loại thông báo
                    $iconClass = 'info';
                    $icon = '📢';
                    if (stripos($notif['title'], 'đơn hàng') !== false || stripos($notif['title'], 'order') !== false) {
                        $iconClass = 'order';
                        $icon = '📦';
                    }
                    if (stripos($notif['title'], 'giao') !== false || stripos($notif['title'], 'delivery') !== false) {
                        $iconClass = 'delivery';
                        $icon = '🚚';
                    }
                    if (stripos($notif['title'], 'cảnh báo') !== false || stripos($notif['title'], 'alert') !== false) {
                        $iconClass = 'alert';
                        $icon = '⚠️';
                    }
                    
                    $isUnread = !$notif['is_read'];
                    $timeAgo = time() - strtotime($notif['created_at']);
                    if ($timeAgo < 60) $timeText = 'Vừa xong';
                    elseif ($timeAgo < 3600) $timeText = floor($timeAgo / 60) . ' phút trước';
                    elseif ($timeAgo < 86400) $timeText = floor($timeAgo / 3600) . ' giờ trước';
                    else $timeText = date('d/m/Y H:i', strtotime($notif['created_at']));
                ?>
                <div class="notif-card <?= $isUnread ? 'unread' : '' ?>" data-type="<?= $iconClass ?>">
                    <div class="notif-icon <?= $iconClass ?>"><?= $icon ?></div>
                    <div class="notif-content">
                        <div class="notif-title">
                            <?= htmlspecialchars($notif['title']) ?>
                            <?php if ($isUnread): ?>
                            <span class="notif-badge new">Mới</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-message"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                        <div class="notif-time">🕐 <?= $timeText ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function filterNotif(type) {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        document.querySelectorAll('.notif-card').forEach(card => {
            if (type === 'all') {
                card.style.display = 'flex';
            } else if (type === 'unread') {
                card.style.display = card.classList.contains('unread') ? 'flex' : 'none';
            } else {
                card.style.display = card.dataset.type === type ? 'flex' : 'none';
            }
        });
    }
    </script>
</body>
</html>
