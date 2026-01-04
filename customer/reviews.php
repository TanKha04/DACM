<?php
/**
 * Đánh giá của tôi
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Xử lý xóa đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $reviewId = (int)$_POST['review_id'];
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
    $stmt->execute([$reviewId, $userId]);
    $message = 'success:Đã xóa đánh giá';
}

// Xử lý sửa đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
    $reviewId = (int)$_POST['review_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$rating, $comment, $reviewId, $userId]);
    $message = 'success:Đã cập nhật đánh giá';
}

// Lấy danh sách đánh giá
$stmt = $pdo->prepare("SELECT r.*, s.name as shop_name, o.id as order_id 
    FROM reviews r 
    LEFT JOIN shops s ON r.shop_id = s.id 
    LEFT JOIN orders o ON r.order_id = o.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC");
$stmt->execute([$userId]);
$reviews = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->prepare("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM reviews WHERE user_id = ?");
$stmt->execute([$userId]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá của tôi - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .review-stats { display: flex; gap: 30px; margin-bottom: 30px; padding: 20px; background: white; border-radius: 12px; }
        .review-stat { text-align: center; }
        .review-stat .value { font-size: 28px; font-weight: bold; color: #ff6b35; }
        .review-stat .label { color: #7f8c8d; font-size: 14px; }
        .review-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .review-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .review-shop { font-weight: bold; color: #2c3e50; }
        .review-rating { color: #f39c12; font-size: 18px; }
        .review-content { color: #555; line-height: 1.6; margin-bottom: 15px; }
        .review-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #eee; }
        .review-date { color: #7f8c8d; font-size: 13px; }
        .review-actions { display: flex; gap: 10px; }
        .reply-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 3px solid #ff6b35; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 15px; padding: 25px; width: 100%; max-width: 450px; }
        .modal h3 { margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">⭐ Đánh giá của tôi</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="review-stats">
            <div class="review-stat">
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Tổng đánh giá</div>
            </div>
            <div class="review-stat">
                <div class="value"><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '0' ?> ⭐</div>
                <div class="label">Điểm trung bình</div>
            </div>
        </div>
        
        <?php if (empty($reviews)): ?>
        <div class="section" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">⭐</p>
            <h2>Chưa có đánh giá nào</h2>
            <p style="color: #7f8c8d; margin-top: 10px;">Đánh giá đơn hàng sau khi nhận hàng để giúp cửa hàng cải thiện dịch vụ</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($reviews as $review): ?>
        <div class="review-card">
            <div class="review-header">
                <div>
                    <div class="review-shop">🏪 <?= htmlspecialchars($review['shop_name'] ?? 'Cửa hàng') ?></div>
                    <div style="color: #7f8c8d; font-size: 13px; margin-top: 5px;">Đơn hàng #<?= $review['order_id'] ?></div>
                </div>
                <div class="review-rating"><?= str_repeat('⭐', $review['rating']) ?></div>
            </div>
            
            <div class="review-content">
                <?= htmlspecialchars($review['comment']) ?: '<em style="color: #999;">Không có nhận xét</em>' ?>
            </div>
            
            <?php if ($review['reply']): ?>
            <div class="reply-box">
                <small style="color: #ff6b35; font-weight: 500;">Phản hồi từ cửa hàng:</small>
                <p style="margin-top: 8px;"><?= htmlspecialchars($review['reply']) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="review-footer">
                <span class="review-date"><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></span>
                <div class="review-actions">
                    <button class="btn-secondary" style="padding: 6px 12px; font-size: 13px;" onclick="openEditModal(<?= $review['id'] ?>, <?= $review['rating'] ?>, '<?= htmlspecialchars(addslashes($review['comment'])) ?>')">Sửa</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                        <button type="submit" name="delete_review" value="1" class="btn-danger" style="padding: 6px 12px; font-size: 13px;" onclick="return confirm('Xóa đánh giá này?')">Xóa</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal sửa đánh giá -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3>✏️ Sửa đánh giá</h3>
            <form method="POST">
                <input type="hidden" name="review_id" id="edit_review_id">
                <div class="form-group">
                    <label>Đánh giá</label>
                    <select name="rating" id="edit_rating" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <option value="5">⭐⭐⭐⭐⭐ (5 sao)</option>
                        <option value="4">⭐⭐⭐⭐ (4 sao)</option>
                        <option value="3">⭐⭐⭐ (3 sao)</option>
                        <option value="2">⭐⭐ (2 sao)</option>
                        <option value="1">⭐ (1 sao)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nhận xét</label>
                    <textarea name="comment" id="edit_comment" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()" style="flex: 1;">Hủy</button>
                    <button type="submit" name="edit_review" value="1" class="btn-primary" style="flex: 1;">Lưu</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function openEditModal(id, rating, comment) {
        document.getElementById('edit_review_id').value = id;
        document.getElementById('edit_rating').value = rating;
        document.getElementById('edit_comment').value = comment;
        document.getElementById('editModal').classList.add('active');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }
    
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    </script>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
