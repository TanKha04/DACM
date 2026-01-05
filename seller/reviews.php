<?php
/**
 * Seller - Đánh giá từ khách hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Xử lý trả lời đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reviewId = (int)$_POST['review_id'];
    $reply = trim($_POST['reply_text'] ?? '');
    
    if ($reply) {
        $stmt = $pdo->prepare("UPDATE reviews SET reply = ?, reply_at = NOW() WHERE id = ? AND shop_id = ?");
        $stmt->execute([$reply, $reviewId, $shop['id']]);
        $message = 'success:Đã gửi phản hồi!';
    }
}

// Thống kê đánh giá
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE shop_id = ?");
$stmt->execute([$shop['id']]);
$stats = $stmt->fetch();

// Phân bố rating
$stmt = $pdo->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE shop_id = ? GROUP BY rating ORDER BY rating DESC");
$stmt->execute([$shop['id']]);
$ratingDist = [];
foreach ($stmt->fetchAll() as $row) {
    $ratingDist[$row['rating']] = $row['count'];
}

// Danh sách đánh giá
$stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.shop_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$shop['id']]);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .rating-overview { display: flex; gap: 40px; align-items: center; margin-bottom: 30px; }
        .rating-big { text-align: center; }
        .rating-big .number { font-size: 60px; font-weight: bold; color: #27ae60; }
        .rating-big .stars { color: #f39c12; font-size: 24px; }
        .rating-bars { flex: 1; }
        .rating-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .rating-bar .label { width: 60px; }
        .rating-bar .bar { flex: 1; height: 10px; background: #eee; border-radius: 5px; overflow: hidden; }
        .rating-bar .fill { height: 100%; background: #f39c12; }
        .review-card { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 15px; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .review-rating { color: #f39c12; }
        .reply-box { background: white; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 3px solid #27ae60; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>⭐ Đánh giá từ khách hàng</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="rating-overview">
                <div class="rating-big">
                    <div class="number"><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '0' ?></div>
                    <div class="stars"><?= str_repeat('⭐', round($stats['avg_rating'] ?? 0)) ?></div>
                    <div style="color: #7f8c8d;"><?= $stats['total'] ?> đánh giá</div>
                </div>
                <div class="rating-bars">
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = $ratingDist[$i] ?? 0;
                        $percent = $stats['total'] > 0 ? ($count / $stats['total']) * 100 : 0;
                    ?>
                    <div class="rating-bar">
                        <span class="label"><?= $i ?> ⭐</span>
                        <div class="bar"><div class="fill" style="width: <?= $percent ?>%"></div></div>
                        <span><?= $count ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">Tất cả đánh giá</h2>
            
            <?php if (empty($reviews)): ?>
            <p style="color: #999; text-align: center; padding: 30px;">Chưa có đánh giá nào</p>
            <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <div>
                        <strong><?= htmlspecialchars($review['user_name']) ?></strong>
                        <span style="color: #7f8c8d; margin-left: 10px;"><?= date('d/m/Y', strtotime($review['created_at'])) ?></span>
                    </div>
                    <span class="review-rating"><?= str_repeat('⭐', $review['rating']) ?></span>
                </div>
                <p><?= htmlspecialchars($review['comment']) ?></p>
                
                <?php if ($review['reply']): ?>
                <div class="reply-box">
                    <small style="color: #27ae60;">Phản hồi của bạn:</small>
                    <p style="margin-top: 5px;"><?= htmlspecialchars($review['reply']) ?></p>
                </div>
                <?php else: ?>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="reply_text" placeholder="Nhập phản hồi..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <button type="submit" name="reply" value="1" class="btn btn-primary">Gửi</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
