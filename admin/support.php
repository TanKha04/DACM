<?php
/**
 * Admin - Quản lý yêu cầu hỗ trợ
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$adminId = $_SESSION['user_id'];
$message = '';
$filter = $_GET['status'] ?? 'all';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        $newStatus = $_POST['new_status'] ?? 'processing';
        
        if ($ticketId && $reply) {
            $stmt = $pdo->prepare("UPDATE support_tickets SET admin_reply = ?, admin_id = ?, status = ?, replied_at = NOW() WHERE id = ?");
            $stmt->execute([$reply, $adminId, $newStatus, $ticketId]);
            $message = 'success:Đã gửi phản hồi thành công!';
        }
    }
    
    if ($action === 'update_status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        
        if ($ticketId && $newStatus) {
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $ticketId]);
            $message = 'success:Đã cập nhật trạng thái';
        }
    }
    
    if ($action === 'delete') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $message = 'success:Đã xóa yêu cầu';
    }
}

// Lấy danh sách tickets
$sql = "SELECT t.*, u.name as user_name, u.email as user_email 
        FROM support_tickets t 
        JOIN users u ON t.user_id = u.id";
if ($filter !== 'all') {
    $sql .= " WHERE t.status = ?";
}
$sql .= " ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter !== 'all') {
    $stmt->execute([$filter]);
} else {
    $stmt->execute();
}
$tickets = $stmt->fetchAll();

// Thống kê
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn(),
    'open' => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn(),
    'processing' => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'processing'")->fetchColumn(),
    'resolved' => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'resolved'")->fetchColumn(),
];

$categories = [
    'account' => '👤 Tài khoản',
    'order' => '📦 Đơn hàng', 
    'payment' => '💳 Thanh toán',
    'technical' => '🔧 Kỹ thuật',
    'other' => '📝 Khác'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hỗ trợ - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .ticket-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #3498db; }
        .ticket-card.open { border-left-color: #f39c12; }
        .ticket-card.processing { border-left-color: #3498db; }
        .ticket-card.resolved { border-left-color: #27ae60; }
        .ticket-card.closed { border-left-color: #95a5a6; }
        .ticket-card.high { box-shadow: 0 0 0 2px rgba(231,76,60,0.3); }
        .ticket-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .ticket-user { display: flex; align-items: center; gap: 10px; }
        .ticket-avatar { width: 40px; height: 40px; background: #ff6b35; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .ticket-subject { font-weight: 600; font-size: 16px; color: #2c3e50; margin-bottom: 5px; }
        .ticket-meta { font-size: 12px; color: #999; }
        .ticket-message { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; line-height: 1.6; }
        .ticket-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .reply-form { background: #f0f8ff; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .reply-form textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 10px; }
        .priority-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .priority-high { background: #fde8e8; color: #e74c3c; }
        .priority-medium { background: #fef3e2; color: #f39c12; }
        .priority-low { background: #e8f5e9; color: #27ae60; }
        .old-reply { background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 10px 0; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🎧 Quản lý hỗ trợ</h1>
        </div>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📋</div>
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Tổng yêu cầu</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">⏳</div>
                <div class="value"><?= $stats['open'] ?></div>
                <div class="label">Chờ xử lý</div>
            </div>
            <div class="stat-card blue">
                <div class="icon">🔄</div>
                <div class="value"><?= $stats['processing'] ?></div>
                <div class="label">Đang xử lý</div>
            </div>
            <div class="stat-card green">
                <div class="icon">✅</div>
                <div class="value"><?= $stats['resolved'] ?></div>
                <div class="label">Đã giải quyết</div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="tabs">
            <a href="?status=all" class="tab <?= $filter === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="?status=open" class="tab <?= $filter === 'open' ? 'active' : '' ?>">⏳ Chờ xử lý <span class="count"><?= $stats['open'] ?></span></a>
            <a href="?status=processing" class="tab <?= $filter === 'processing' ? 'active' : '' ?>">🔄 Đang xử lý</a>
            <a href="?status=resolved" class="tab <?= $filter === 'resolved' ? 'active' : '' ?>">✅ Đã giải quyết</a>
            <a href="?status=closed" class="tab <?= $filter === 'closed' ? 'active' : '' ?>">🔒 Đã đóng</a>
        </div>
        
        <!-- Tickets List -->
        <?php if (empty($tickets)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 50px;">📭</p>
            <h3>Không có yêu cầu nào</h3>
        </div>
        <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
        <div class="ticket-card <?= $ticket['status'] ?> <?= $ticket['priority'] ?>">
            <div class="ticket-header">
                <div class="ticket-user">
                    <div class="ticket-avatar"><?= mb_substr($ticket['user_name'], 0, 1) ?></div>
                    <div>
                        <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                        <div class="ticket-meta">
                            <?= htmlspecialchars($ticket['user_name']) ?> • <?= htmlspecialchars($ticket['user_email']) ?>
                            • <?= $categories[$ticket['category']] ?? $ticket['category'] ?>
                            • <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="priority-badge priority-<?= $ticket['priority'] ?>">
                        <?= $ticket['priority'] === 'high' ? '🔴 Cao' : ($ticket['priority'] === 'medium' ? '🟡 TB' : '🟢 Thấp') ?>
                    </span>
                    <br>
                    <span class="badge badge-<?= $ticket['status'] === 'open' ? 'pending' : ($ticket['status'] === 'resolved' ? 'active' : 'warning') ?>" style="margin-top: 5px; display: inline-block;">
                        <?= ucfirst($ticket['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="ticket-message"><?= nl2br(htmlspecialchars($ticket['message'])) ?></div>
            
            <?php if ($ticket['admin_reply']): ?>
            <div class="old-reply">
                <strong>💬 Phản hồi trước đó:</strong><br>
                <?= nl2br(htmlspecialchars($ticket['admin_reply'])) ?>
                <div style="font-size: 12px; color: #999; margin-top: 10px;">
                    <?= $ticket['replied_at'] ? date('d/m/Y H:i', strtotime($ticket['replied_at'])) : '' ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="ticket-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <select name="status" onchange="this.form.submit()" style="padding: 5px 10px; border-radius: 5px; border: 1px solid #ddd;">
                        <option value="">-- Đổi trạng thái --</option>
                        <option value="open">Chờ xử lý</option>
                        <option value="processing">Đang xử lý</option>
                        <option value="resolved">Đã giải quyết</option>
                        <option value="closed">Đã đóng</option>
                    </select>
                    <input type="hidden" name="action" value="update_status">
                </form>
                <button class="btn btn-primary btn-sm" onclick="toggleReply(<?= $ticket['id'] ?>)">💬 Phản hồi</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa yêu cầu này?')">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">🗑 Xóa</button>
                </form>
            </div>
            
            <div class="reply-form" id="reply-<?= $ticket['id'] ?>" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <textarea name="reply" rows="3" placeholder="Nhập phản hồi..." required></textarea>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select name="new_status" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                            <option value="processing">Đang xử lý</option>
                            <option value="resolved">Đã giải quyết</option>
                            <option value="closed">Đã đóng</option>
                        </select>
                        <button type="submit" class="btn btn-primary">📤 Gửi phản hồi</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleReply(id) {
        var el = document.getElementById('reply-' + id);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html>
