<?php
/**
 * Customer - Hỗ trợ tài khoản
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Xử lý gửi yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $content = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        
        if ($subject && $content) {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, category, message, priority) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $subject, $category, $content, $priority]);
            $message = 'success:Gửi yêu cầu hỗ trợ thành công! Chúng tôi sẽ phản hồi sớm nhất.';
        } else {
            $message = 'error:Vui lòng điền đầy đủ thông tin';
        }
    }
}

// Lấy danh sách yêu cầu của user
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

$categories = [
    'account' => '👤 Tài khoản',
    'order' => '📦 Đơn hàng',
    'payment' => '💳 Thanh toán',
    'technical' => '🔧 Kỹ thuật',
    'other' => '📝 Khác'
];

$statusLabels = [
    'open' => ['label' => 'Chờ xử lý', 'class' => 'pending'],
    'processing' => ['label' => 'Đang xử lý', 'class' => 'warning'],
    'resolved' => ['label' => 'Đã giải quyết', 'class' => 'active'],
    'closed' => ['label' => 'Đã đóng', 'class' => 'blocked']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hỗ trợ - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .support-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .ticket-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #3498db; }
        .ticket-card.open { border-left-color: #f39c12; }
        .ticket-card.processing { border-left-color: #3498db; }
        .ticket-card.resolved { border-left-color: #27ae60; }
        .ticket-card.closed { border-left-color: #95a5a6; }
        .ticket-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .ticket-subject { font-weight: 600; font-size: 16px; color: #2c3e50; }
        .ticket-category { font-size: 13px; color: #7f8c8d; margin-top: 5px; }
        .ticket-message { color: #555; font-size: 14px; line-height: 1.6; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .ticket-reply { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 10px; }
        .ticket-reply-label { font-size: 12px; color: #27ae60; font-weight: 600; margin-bottom: 5px; }
        .ticket-meta { display: flex; gap: 15px; font-size: 12px; color: #999; }
        .priority-high { color: #e74c3c; }
        .priority-medium { color: #f39c12; }
        .priority-low { color: #27ae60; }
        .empty-state { text-align: center; padding: 50px; color: #7f8c8d; }
        .empty-state .icon { font-size: 60px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">🎧 Hỗ trợ tài khoản</h1>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="support-grid">
            <!-- Form gửi yêu cầu -->
            <div>
                <div class="section">
                    <h2>📝 Gửi yêu cầu hỗ trợ</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_ticket">
                        
                        <div class="form-group">
                            <label>Tiêu đề *</label>
                            <input type="text" name="subject" placeholder="Mô tả ngắn gọn vấn đề của bạn" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Danh mục</label>
                            <select name="category">
                                <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Mức độ ưu tiên</label>
                            <select name="priority">
                                <option value="low">🟢 Thấp</option>
                                <option value="medium" selected>🟡 Trung bình</option>
                                <option value="high">🔴 Cao</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Nội dung chi tiết *</label>
                            <textarea name="message" rows="5" placeholder="Mô tả chi tiết vấn đề bạn gặp phải..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="width: 100%;">📤 Gửi yêu cầu</button>
                    </form>
                </div>
            </div>
            
            <!-- Danh sách yêu cầu -->
            <div>
                <h2 style="margin-bottom: 20px;">📋 Yêu cầu của bạn</h2>
                
                <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>Bạn chưa có yêu cầu hỗ trợ nào</p>
                </div>
                <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card <?= $ticket['status'] ?>">
                    <div class="ticket-header">
                        <div>
                            <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                            <div class="ticket-category"><?= $categories[$ticket['category']] ?? $ticket['category'] ?></div>
                        </div>
                        <span class="badge badge-<?= $statusLabels[$ticket['status']]['class'] ?>">
                            <?= $statusLabels[$ticket['status']]['label'] ?>
                        </span>
                    </div>
                    
                    <div class="ticket-message"><?= nl2br(htmlspecialchars($ticket['message'])) ?></div>
                    
                    <?php if ($ticket['admin_reply']): ?>
                    <div class="ticket-reply">
                        <div class="ticket-reply-label">💬 Phản hồi từ Admin:</div>
                        <?= nl2br(htmlspecialchars($ticket['admin_reply'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="ticket-meta">
                        <span class="priority-<?= $ticket['priority'] ?>">
                            <?= $ticket['priority'] === 'high' ? '🔴 Cao' : ($ticket['priority'] === 'medium' ? '🟡 TB' : '🟢 Thấp') ?>
                        </span>
                        <span>📅 <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
