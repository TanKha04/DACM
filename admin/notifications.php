<?php
/**
 * Admin - Quản lý thông báo
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_single') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'system';
        
        if ($userId && $title && $content) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $content, $type]);
            $message = 'success:Đã gửi thông báo thành công!';
        } else {
            $message = 'error:Vui lòng điền đầy đủ thông tin';
        }
    }
    
    if ($action === 'send_all') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'system';
        $targetRole = $_POST['target_role'] ?? 'all';
        
        if ($title && $content) {
            // Lấy danh sách user theo role
            if ($targetRole === 'all') {
                $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
                $stmt->execute([$targetRole]);
            }
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            foreach ($users as $uid) {
                $insertStmt->execute([$uid, $title, $content, $type]);
            }
            $message = 'success:Đã gửi thông báo đến ' . count($users) . ' người dùng!';
        }
    }
    
    if ($action === 'delete') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$notifId]);
        $message = 'success:Đã xóa thông báo';
    }
    
    if ($action === 'delete_all_read') {
        $pdo->query("DELETE FROM notifications WHERE is_read = 1");
        $message = 'success:Đã xóa tất cả thông báo đã đọc';
    }
}

// Lấy danh sách users
$users = $pdo->query("SELECT id, name, email, role FROM users WHERE status = 'active' ORDER BY name")->fetchAll();

// Lấy thông báo gần đây
$notifications = $pdo->query("SELECT n.*, u.name as user_name, u.email as user_email 
    FROM notifications n 
    LEFT JOIN users u ON n.user_id = u.id 
    ORDER BY n.created_at DESC 
    LIMIT 50")->fetchAll();

// Thống kê
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
    'unread' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .send-options { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .send-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .send-card h3 { margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .notif-item { display: flex; align-items: start; gap: 15px; padding: 15px; border-bottom: 1px solid #eee; }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .notif-icon.order { background: #e3f2fd; }
        .notif-icon.system { background: #f3e5f5; }
        .notif-icon.promo { background: #fff3e0; }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
        .notif-message { color: #666; font-size: 14px; line-height: 1.5; }
        .notif-meta { display: flex; gap: 15px; margin-top: 8px; font-size: 12px; color: #999; }
        .notif-unread { background: #fff3cd; }
        .type-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; }
        .type-order { background: #e3f2fd; color: #1976d2; }
        .type-system { background: #f3e5f5; color: #7b1fa2; }
        .type-promo { background: #fff3e0; color: #f57c00; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🔔 Quản lý thông báo</h1>
        </div>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-card">
                <div class="icon">📬</div>
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Tổng thông báo</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">🔴</div>
                <div class="value"><?= $stats['unread'] ?></div>
                <div class="label">Chưa đọc</div>
            </div>
            <div class="stat-card green">
                <div class="icon">📅</div>
                <div class="value"><?= $stats['today'] ?></div>
                <div class="label">Hôm nay</div>
            </div>
        </div>
        
        <!-- Send Options -->
        <div class="send-options">
            <!-- Gửi cho 1 người -->
            <div class="send-card">
                <h3>👤 Gửi cho 1 người dùng</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="send_single">
                    <div class="form-group">
                        <label>Chọn người dùng *</label>
                        <select name="user_id" required>
                            <option value="">-- Chọn người dùng --</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>) - <?= ucfirst($u['role']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loại thông báo</label>
                        <select name="type">
                            <option value="system">🔔 Hệ thống</option>
                            <option value="order">📦 Đơn hàng</option>
                            <option value="promo">🎁 Khuyến mãi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tiêu đề *</label>
                        <input type="text" name="title" placeholder="Tiêu đề thông báo" required>
                    </div>
                    <div class="form-group">
                        <label>Nội dung *</label>
                        <textarea name="message" rows="3" placeholder="Nội dung thông báo..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">📤 Gửi thông báo</button>
                </form>
            </div>
            
            <!-- Gửi cho nhiều người -->
            <div class="send-card">
                <h3>👥 Gửi cho nhiều người dùng</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="send_all">
                    <div class="form-group">
                        <label>Đối tượng nhận</label>
                        <select name="target_role">
                            <option value="all">🌐 Tất cả người dùng</option>
                            <option value="customer">🛒 Khách hàng</option>
                            <option value="seller">🏪 Người bán</option>
                            <option value="shipper">🛵 Shipper</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loại thông báo</label>
                        <select name="type">
                            <option value="system">🔔 Hệ thống</option>
                            <option value="promo">🎁 Khuyến mãi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tiêu đề *</label>
                        <input type="text" name="title" placeholder="Tiêu đề thông báo" required>
                    </div>
                    <div class="form-group">
                        <label>Nội dung *</label>
                        <textarea name="message" rows="3" placeholder="Nội dung thông báo..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;" onclick="return confirm('Gửi thông báo đến tất cả người dùng đã chọn?')">📢 Gửi hàng loạt</button>
                </form>
            </div>
        </div>
        
        <!-- Recent Notifications -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Thông báo gần đây</h2>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="action" value="delete_all_read" class="btn btn-secondary btn-sm" onclick="return confirm('Xóa tất cả thông báo đã đọc?')">🗑 Xóa đã đọc</button>
                </form>
            </div>
            
            <?php if (empty($notifications)): ?>
            <p style="text-align: center; color: #999; padding: 30px;">Chưa có thông báo nào</p>
            <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-item <?= $notif['is_read'] ? '' : 'notif-unread' ?>">
                <div class="notif-icon <?= $notif['type'] ?>">
                    <?= $notif['type'] === 'order' ? '📦' : ($notif['type'] === 'promo' ? '🎁' : '🔔') ?>
                </div>
                <div class="notif-content">
                    <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                    <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notif-meta">
                        <span>👤 <?= htmlspecialchars($notif['user_name'] ?? 'N/A') ?></span>
                        <span class="type-badge type-<?= $notif['type'] ?>"><?= ucfirst($notif['type']) ?></span>
                        <span><?= $notif['is_read'] ? '✅ Đã đọc' : '🔴 Chưa đọc' ?></span>
                        <span>📅 <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></span>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Xóa?')">🗑</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
