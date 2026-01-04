<?php
/**
 * Admin - Quản lý đánh giá (Cửa hàng & Shipper)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';
$tab = $_GET['tab'] ?? 'shop';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($reviewId && $action) {
        if ($action === 'hide') {
            $stmt = $pdo->prepare("UPDATE reviews SET status = 'hidden' WHERE id = ?");
            $stmt->execute([$reviewId]);
            $message = 'success:Đã ẩn đánh giá';
        } elseif ($action === 'show') {
            $stmt = $pdo->prepare("UPDATE reviews SET status = 'active' WHERE id = ?");
            $stmt->execute([$reviewId]);
            $message = 'success:Đã hiện đánh giá';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            $message = 'success:Đã xóa đánh giá';
        }
    }
}

// Lấy đánh giá cửa hàng
$stmt = $pdo->query("SELECT r.*, u.name as user_name, s.name as shop_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN shops s ON r.shop_id = s.id 
    WHERE r.shop_id IS NOT NULL
    ORDER BY r.created_at DESC LIMIT 100");
$shopReviews = $stmt->fetchAll();

// Lấy đánh giá shipper
$stmt = $pdo->query("SELECT r.*, u.name as user_name, sh.name as shipper_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN users sh ON r.shipper_id = sh.id 
    WHERE r.shipper_id IS NOT NULL
    ORDER BY r.created_at DESC LIMIT 100");
$shipperReviews = $stmt->fetchAll();

// Thống kê
$shopStats = [
    'total' => count($shopReviews),
    'avg_rating' => $shopReviews ? round(array_sum(array_column($shopReviews, 'rating')) / count($shopReviews), 1) : 0,
    'five_star' => count(array_filter($shopReviews, fn($r) => $r['rating'] == 5))
];

$shipperStats = [
    'total' => count($shipperReviews),
    'avg_rating' => $shipperReviews ? round(array_sum(array_column($shipperReviews, 'rating')) / count($shipperReviews), 1) : 0,
    'five_star' => count(array_filter($shipperReviews, fn($r) => $r['rating'] == 5))
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đánh giá - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .review-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .reviewer-info { display: flex; align-items: center; gap: 12px; }
        .reviewer-avatar { width: 45px; height: 45px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .reviewer-name { font-weight: 600; }
        .reviewer-target { color: #7f8c8d; font-size: 13px; }
        .review-stars { color: #f39c12; font-size: 18px; letter-spacing: 2px; }
        .review-content { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .review-meta { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #7f8c8d; }
        .rating-badge { padding: 5px 12px; border-radius: 20px; font-weight: bold; }
        .rating-5 { background: #d4edda; color: #155724; }
        .rating-4 { background: #cce5ff; color: #004085; }
        .rating-3 { background: #fff3cd; color: #856404; }
        .rating-2 { background: #f8d7da; color: #721c24; }
        .rating-1 { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>⭐ Quản lý đánh giá</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs" style="margin-bottom: 25px;">
            <a href="?tab=shop" class="tab <?= $tab === 'shop' ? 'active' : '' ?>">
                🏪 Đánh giá Cửa hàng <span class="count"><?= $shopStats['total'] ?></span>
            </a>
            <a href="?tab=shipper" class="tab <?= $tab === 'shipper' ? 'active' : '' ?>">
                🛵 Đánh giá Shipper <span class="count"><?= $shipperStats['total'] ?></span>
            </a>
        </div>
        
        <?php if ($tab === 'shop'): ?>
        <!-- Tab Đánh giá Cửa hàng -->
        <div class="stats-grid" style="margin-bottom: 25px;">
            <div class="stat-card blue">
                <div class="icon">📝</div>
                <div class="value"><?= $shopStats['total'] ?></div>
                <div class="label">Tổng đánh giá</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">⭐</div>
                <div class="value"><?= $shopStats['avg_rating'] ?></div>
                <div class="label">Điểm trung bình</div>
            </div>
            <div class="stat-card green">
                <div class="icon">🌟</div>
                <div class="value"><?= $shopStats['five_star'] ?></div>
                <div class="label">Đánh giá 5 sao</div>
            </div>
        </div>
        
        <?php if (empty($shopReviews)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🏪</p>
            <h2>Chưa có đánh giá cửa hàng</h2>
        </div>
        <?php else: ?>
        <?php foreach ($shopReviews as $review): ?>
        <div class="review-card <?= $review['status'] === 'hidden' ? 'opacity-50' : '' ?>">
            <div class="review-header">
                <div class="reviewer-info">
                    <div class="reviewer-avatar">👤</div>
                    <div>
                        <div class="reviewer-name"><?= htmlspecialchars($review['user_name']) ?></div>
                        <div class="reviewer-target">🏪 <?= htmlspecialchars($review['shop_name']) ?></div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="rating-badge rating-<?= $review['rating'] ?>"><?= $review['rating'] ?> ⭐</span>
                    <?php if ($review['status'] === 'hidden'): ?>
                    <span class="badge badge-hidden" style="margin-left: 5px;">Đã ẩn</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="review-stars">
                <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
            </div>
            
            <?php if ($review['comment']): ?>
            <div class="review-content">
                <?= nl2br(htmlspecialchars($review['comment'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="review-meta">
                <span>📅 <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></span>
                <div style="display: flex; gap: 8px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                        <?php if ($review['status'] === 'active'): ?>
                        <button type="submit" name="action" value="hide" class="btn btn-warning btn-sm">🙈 Ẩn</button>
                        <?php else: ?>
                        <button type="submit" name="action" value="show" class="btn btn-success btn-sm">👁 Hiện</button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Xóa đánh giá này?')">🗑 Xóa</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Tab Đánh giá Shipper -->
        <div class="stats-grid" style="margin-bottom: 25px;">
            <div class="stat-card blue">
                <div class="icon">📝</div>
                <div class="value"><?= $shipperStats['total'] ?></div>
                <div class="label">Tổng đánh giá</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">⭐</div>
                <div class="value"><?= $shipperStats['avg_rating'] ?></div>
                <div class="label">Điểm trung bình</div>
            </div>
            <div class="stat-card green">
                <div class="icon">🌟</div>
                <div class="value"><?= $shipperStats['five_star'] ?></div>
                <div class="label">Đánh giá 5 sao</div>
            </div>
        </div>
        
        <?php if (empty($shipperReviews)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🛵</p>
            <h2>Chưa có đánh giá shipper</h2>
        </div>
        <?php else: ?>
        <?php foreach ($shipperReviews as $review): ?>
        <div class="review-card <?= $review['status'] === 'hidden' ? 'opacity-50' : '' ?>">
            <div class="review-header">
                <div class="reviewer-info">
                    <div class="reviewer-avatar">👤</div>
                    <div>
                        <div class="reviewer-name"><?= htmlspecialchars($review['user_name']) ?></div>
                        <div class="reviewer-target">🛵 Shipper: <?= htmlspecialchars($review['shipper_name']) ?></div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="rating-badge rating-<?= $review['rating'] ?>"><?= $review['rating'] ?> ⭐</span>
                    <?php if ($review['status'] === 'hidden'): ?>
                    <span class="badge badge-hidden" style="margin-left: 5px;">Đã ẩn</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="review-stars">
                <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
            </div>
            
            <?php if ($review['comment']): ?>
            <div class="review-content">
                <?= nl2br(htmlspecialchars($review['comment'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="review-meta">
                <span>📅 <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></span>
                <div style="display: flex; gap: 8px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                        <?php if ($review['status'] === 'active'): ?>
                        <button type="submit" name="action" value="hide" class="btn btn-warning btn-sm">🙈 Ẩn</button>
                        <?php else: ?>
                        <button type="submit" name="action" value="show" class="btn btn-success btn-sm">👁 Hiện</button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Xóa đánh giá này?')">🗑 Xóa</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
