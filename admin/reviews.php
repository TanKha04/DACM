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
$selectedShopId = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : null;
$selectedShipperId = isset($_GET['shipper_id']) ? (int)$_GET['shipper_id'] : null;

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

// Lấy danh sách cửa hàng có đánh giá
$stmt = $pdo->query("SELECT s.id, s.name, s.image,
    COUNT(r.id) as total_reviews,
    ROUND(AVG(r.rating), 1) as avg_rating,
    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star
    FROM shops s 
    JOIN reviews r ON r.shop_id = s.id
    GROUP BY s.id
    ORDER BY total_reviews DESC");
$shopList = $stmt->fetchAll();

// Lấy danh sách shipper có đánh giá
$stmt = $pdo->query("SELECT u.id, u.name, u.avatar,
    COUNT(r.id) as total_reviews,
    ROUND(AVG(r.rating), 1) as avg_rating,
    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star
    FROM users u 
    JOIN reviews r ON r.shipper_id = u.id
    WHERE u.role = 'shipper'
    GROUP BY u.id
    ORDER BY total_reviews DESC");
$shipperList = $stmt->fetchAll();

// Lấy đánh giá cửa hàng (nếu có chọn shop)
$shopReviews = [];
$selectedShop = null;
if ($selectedShopId) {
    $stmt = $pdo->prepare("SELECT r.*, u.name as user_name, s.name as shop_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN shops s ON r.shop_id = s.id 
        WHERE r.shop_id = ?
        ORDER BY r.created_at DESC LIMIT 100");
    $stmt->execute([$selectedShopId]);
    $shopReviews = $stmt->fetchAll();
    
    // Lấy thông tin shop được chọn
    foreach ($shopList as $shop) {
        if ($shop['id'] == $selectedShopId) {
            $selectedShop = $shop;
            break;
        }
    }
}

// Lấy đánh giá shipper (nếu có chọn shipper)
$shipperReviews = [];
$selectedShipper = null;
if ($selectedShipperId) {
    $stmt = $pdo->prepare("SELECT r.*, u.name as user_name, sh.name as shipper_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN users sh ON r.shipper_id = sh.id 
        WHERE r.shipper_id = ?
        ORDER BY r.created_at DESC LIMIT 100");
    $stmt->execute([$selectedShipperId]);
    $shipperReviews = $stmt->fetchAll();
    
    // Lấy thông tin shipper được chọn
    foreach ($shipperList as $shipper) {
        if ($shipper['id'] == $selectedShipperId) {
            $selectedShipper = $shipper;
            break;
        }
    }
}

// Thống kê tổng cho shop
$totalShopReviews = array_sum(array_column($shopList, 'total_reviews'));

// Thống kê tổng cho shipper
$totalShipperReviews = array_sum(array_column($shipperList, 'total_reviews'));
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
        
        /* Shop/Shipper list styles */
        .entity-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .entity-card { background: white; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.3s; border: 2px solid transparent; }
        .entity-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .entity-card.active { border-color: #e74c3c; background: #fff5f5; }
        .entity-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .entity-avatar { width: 50px; height: 50px; border-radius: 10px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 24px; overflow: hidden; }
        .entity-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .entity-name { font-weight: 600; font-size: 15px; color: #2c3e50; }
        .entity-stats { display: flex; justify-content: space-between; background: #f8f9fa; border-radius: 8px; padding: 10px; }
        .entity-stat { text-align: center; }
        .entity-stat-value { font-weight: bold; font-size: 16px; color: #e74c3c; }
        .entity-stat-label { font-size: 11px; color: #7f8c8d; }
        
        .back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; text-decoration: none; margin-bottom: 20px; transition: all 0.3s; }
        .back-btn:hover { background: #e9ecef; }
        
        .selected-entity-header { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .selected-entity-info { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .selected-entity-avatar { width: 60px; height: 60px; border-radius: 12px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 30px; overflow: hidden; }
        .selected-entity-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .selected-entity-name { font-size: 20px; font-weight: bold; color: #2c3e50; }
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
                🏪 Đánh giá Cửa hàng <span class="count"><?= $totalShopReviews ?></span>
            </a>
            <a href="?tab=shipper" class="tab <?= $tab === 'shipper' ? 'active' : '' ?>">
                🛵 Đánh giá Shipper <span class="count"><?= $totalShipperReviews ?></span>
            </a>
        </div>
        
        <?php if ($tab === 'shop'): ?>
        <!-- Tab Đánh giá Cửa hàng -->
        
        <?php if ($selectedShopId && $selectedShop): ?>
        <!-- Hiển thị đánh giá của cửa hàng được chọn -->
        <a href="?tab=shop" class="back-btn">← Quay lại danh sách cửa hàng</a>
        
        <div class="selected-entity-header">
            <div class="selected-entity-info">
                <div class="selected-entity-avatar">
                    <?php if ($selectedShop['image']): ?>
                    <img src="../<?= htmlspecialchars($selectedShop['image']) ?>" alt="<?= htmlspecialchars($selectedShop['name']) ?>">
                    <?php else: ?>
                    🏪
                    <?php endif; ?>
                </div>
                <div class="selected-entity-name"><?= htmlspecialchars($selectedShop['name']) ?></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="icon">📝</div>
                    <div class="value"><?= $selectedShop['total_reviews'] ?></div>
                    <div class="label">Tổng đánh giá</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon">⭐</div>
                    <div class="value"><?= $selectedShop['avg_rating'] ?? 0 ?></div>
                    <div class="label">Điểm trung bình</div>
                </div>
                <div class="stat-card green">
                    <div class="icon">🌟</div>
                    <div class="value"><?= $selectedShop['five_star'] ?></div>
                    <div class="label">Đánh giá 5 sao</div>
                </div>
            </div>
        </div>
        
        <?php if (empty($shopReviews)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🏪</p>
            <h2>Chưa có đánh giá cho cửa hàng này</h2>
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
        <!-- Hiển thị danh sách cửa hàng -->
        <h3 style="margin-bottom: 15px;">📋 Chọn cửa hàng để xem đánh giá</h3>
        
        <?php if (empty($shopList)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🏪</p>
            <h2>Chưa có cửa hàng nào được đánh giá</h2>
        </div>
        <?php else: ?>
        <div class="entity-list">
            <?php foreach ($shopList as $shop): ?>
            <a href="?tab=shop&shop_id=<?= $shop['id'] ?>" class="entity-card">
                <div class="entity-card-header">
                    <div class="entity-avatar">
                        <?php if ($shop['image']): ?>
                        <img src="../<?= htmlspecialchars($shop['image']) ?>" alt="<?= htmlspecialchars($shop['name']) ?>">
                        <?php else: ?>
                        🏪
                        <?php endif; ?>
                    </div>
                    <div class="entity-name"><?= htmlspecialchars($shop['name']) ?></div>
                </div>
                <div class="entity-stats">
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shop['total_reviews'] ?></div>
                        <div class="entity-stat-label">Đánh giá</div>
                    </div>
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shop['avg_rating'] ?? 0 ?> ⭐</div>
                        <div class="entity-stat-label">Trung bình</div>
                    </div>
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shop['five_star'] ?></div>
                        <div class="entity-stat-label">5 sao</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Tab Đánh giá Shipper -->
        
        <?php if ($selectedShipperId && $selectedShipper): ?>
        <!-- Hiển thị đánh giá của shipper được chọn -->
        <a href="?tab=shipper" class="back-btn">← Quay lại danh sách shipper</a>
        
        <div class="selected-entity-header">
            <div class="selected-entity-info">
                <div class="selected-entity-avatar">
                    <?php if ($selectedShipper['avatar']): ?>
                    <img src="../<?= htmlspecialchars($selectedShipper['avatar']) ?>" alt="<?= htmlspecialchars($selectedShipper['name']) ?>">
                    <?php else: ?>
                    🛵
                    <?php endif; ?>
                </div>
                <div class="selected-entity-name"><?= htmlspecialchars($selectedShipper['name']) ?></div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="icon">📝</div>
                    <div class="value"><?= $selectedShipper['total_reviews'] ?></div>
                    <div class="label">Tổng đánh giá</div>
                </div>
                <div class="stat-card orange">
                    <div class="icon">⭐</div>
                    <div class="value"><?= $selectedShipper['avg_rating'] ?? 0 ?></div>
                    <div class="label">Điểm trung bình</div>
                </div>
                <div class="stat-card green">
                    <div class="icon">🌟</div>
                    <div class="value"><?= $selectedShipper['five_star'] ?></div>
                    <div class="label">Đánh giá 5 sao</div>
                </div>
            </div>
        </div>
        
        <?php if (empty($shipperReviews)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🛵</p>
            <h2>Chưa có đánh giá cho shipper này</h2>
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
        
        <?php else: ?>
        <!-- Hiển thị danh sách shipper -->
        <h3 style="margin-bottom: 15px;">📋 Chọn shipper để xem đánh giá</h3>
        
        <?php if (empty($shipperList)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🛵</p>
            <h2>Chưa có shipper nào được đánh giá</h2>
        </div>
        <?php else: ?>
        <div class="entity-list">
            <?php foreach ($shipperList as $shipper): ?>
            <a href="?tab=shipper&shipper_id=<?= $shipper['id'] ?>" class="entity-card">
                <div class="entity-card-header">
                    <div class="entity-avatar">
                        <?php if ($shipper['avatar']): ?>
                        <img src="../<?= htmlspecialchars($shipper['avatar']) ?>" alt="<?= htmlspecialchars($shipper['name']) ?>">
                        <?php else: ?>
                        🛵
                        <?php endif; ?>
                    </div>
                    <div class="entity-name"><?= htmlspecialchars($shipper['name']) ?></div>
                </div>
                <div class="entity-stats">
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shipper['total_reviews'] ?></div>
                        <div class="entity-stat-label">Đánh giá</div>
                    </div>
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shipper['avg_rating'] ?? 0 ?> ⭐</div>
                        <div class="entity-stat-label">Trung bình</div>
                    </div>
                    <div class="entity-stat">
                        <div class="entity-stat-value"><?= $shipper['five_star'] ?></div>
                        <div class="entity-stat-label">5 sao</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</body>
</html>
