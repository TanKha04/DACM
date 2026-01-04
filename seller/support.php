<?php
/**
 * Seller - Hỗ trợ từ quản trị viên
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole(['seller']);

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
            $message = 'success:Gửi yêu cầu hỗ trợ thành công! Quản trị viên sẽ phản hồi sớm nhất.';
        } else {
            $message = 'error:Vui lòng điền đầy đủ thông tin';
        }
    }
}

// Lấy danh sách yêu cầu của seller
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

$categories = [
    'shop' => '🏪 Cửa hàng',
    'product' => '🍔 Sản phẩm',
    'order' => '📦 Đơn hàng',
    'payment' => '💳 Thanh toán/Doanh thu',
    'technical' => '🔧 Kỹ thuật',
    'policy' => '📋 Chính sách',
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
    <title>Hỗ trợ - Seller Panel</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        .support-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
        .form-section { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); height: fit-content; }
        .form-section h2 { margin-bottom: 25px; color: #2c3e50; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px 16px; border: 2px solid #e8e8e8; border-radius: 10px; font-size: 14px; transition: all 0.3s; background: #fafafa; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #27ae60; background: white; outline: none; box-shadow: 0 0 0 4px rgba(39,174,96,0.1); }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-submit { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; border: none; padding: 16px 30px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(39,174,96,0.35); }
        
        .tickets-section { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .tickets-section h2 { margin-bottom: 25px; color: #2c3e50; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .tickets-list { max-height: 500px; overflow-y: auto; padding-right: 5px; }
        .tickets-list::-webkit-scrollbar { width: 6px; }
        .tickets-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .tickets-list::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        
        .ticket-card { background: #f8f9fa; border-radius: 12px; padding: 18px; margin-bottom: 12px; border-left: 4px solid #3498db; transition: all 0.3s; }
        .ticket-card:hover { transform: translateX(5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .ticket-card.open { border-left-color: #f39c12; }
        .ticket-card.processing { border-left-color: #3498db; }
        .ticket-card.resolved { border-left-color: #27ae60; }
        .ticket-card.closed { border-left-color: #95a5a6; }
        .ticket-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .ticket-subject { font-weight: 600; font-size: 15px; color: #2c3e50; }
        .ticket-category { font-size: 12px; color: #7f8c8d; margin-top: 4px; }
        .ticket-message { color: #555; font-size: 13px; line-height: 1.5; margin: 12px 0; padding: 12px; background: white; border-radius: 8px; border: 1px solid #eee; }
        .ticket-reply { background: #e8f5e9; padding: 12px; border-radius: 8px; margin-top: 10px; }
        .ticket-reply-label { font-size: 11px; color: #27ae60; font-weight: 600; margin-bottom: 5px; }
        .ticket-meta { display: flex; gap: 15px; font-size: 11px; color: #999; margin-top: 10px; }
        .priority-high { color: #e74c3c; }
        .priority-medium { color: #f39c12; }
        .priority-low { color: #27ae60; }
        
        .empty-state { text-align: center; padding: 60px 30px; color: #7f8c8d; }
        .empty-state .icon { font-size: 70px; margin-bottom: 15px; opacity: 0.5; }
        .empty-state p { font-size: 15px; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-warning { background: #cce5ff; color: #004085; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-blocked { background: #e2e3e5; color: #383d41; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        @media (max-width: 1024px) { .support-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>🎧 Hỗ trợ từ quản trị viên</h1>
        </div>
        
        <div class="content">
            <?php if ($message): $parts = explode(':', $message, 2); ?>
            <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
            <?php endif; ?>
            
            <div class="support-grid">
                <!-- Form gửi yêu cầu -->
                <div class="form-section">
                    <h2>📝 Gửi yêu cầu hỗ trợ</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_ticket">
                        
                        <div class="form-group">
                            <label>Tiêu đề *</label>
                            <input type="text" name="subject" placeholder="Mô tả ngắn gọn vấn đề của bạn" required>
                        </div>
                        
                        <div class="form-row">
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
                        </div>
                        
                        <div class="form-group">
                            <label>Nội dung chi tiết *</label>
                            <textarea name="message" rows="5" placeholder="Mô tả chi tiết vấn đề bạn gặp phải..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">📤 Gửi yêu cầu</button>
                    </form>
                </div>
                
                <!-- Danh sách yêu cầu -->
                <div class="tickets-section">
                    <h2>📋 Yêu cầu của bạn</h2>
                    
                    <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <p>Bạn chưa có yêu cầu hỗ trợ nào</p>
                    </div>
                    <?php else: ?>
                    <div class="tickets-list">
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
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
