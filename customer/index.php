<?php
/**
 * Customer Dashboard - Trang chủ người mua
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Kiểm tra nếu chưa có vị trí thì redirect
if (!$user['lat'] || !$user['lng']) {
    header('Location: set_location.php');
    exit;
}

$userLat = $user['lat'];
$userLng = $user['lng'];

// Lấy cấu hình khoảng cách tối đa
$stmt = $pdo->query("SELECT max_shop_distance FROM shipping_config LIMIT 1");
$config = $stmt->fetch();
$maxDistance = $config['max_shop_distance'] ?? 15;

// Thống kê
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
$stmt->execute([$userId]);
$totalOrders = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ? AND status NOT IN ('delivered', 'cancelled')");
$stmt->execute([$userId]);
$pendingOrders = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
$stmt->execute([$userId]);
$cartCount = $stmt->fetch()['total'];

// Đơn hàng gần đây
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name FROM orders o JOIN shops s ON o.shop_id = s.id WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentOrders = $stmt->fetchAll();

// Sản phẩm nổi bật - chỉ từ cửa hàng trong phạm vi
$stmt = $pdo->prepare("SELECT p.*, s.name as shop_name,
        (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
        FROM products p 
        JOIN shops s ON p.shop_id = s.id 
        WHERE p.status = 'active' AND s.status = 'active' 
        AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
        HAVING distance <= ?
        ORDER BY RAND() LIMIT 8");
$stmt->execute([$userLat, $userLng, $userLat, $maxDistance]);
$products = $stmt->fetchAll();

// Nếu không có sản phẩm từ shop có tọa độ, lấy tất cả sản phẩm
if (empty($products)) {
    $stmt = $pdo->query("SELECT p.*, s.name as shop_name, NULL as distance 
                         FROM products p 
                         JOIN shops s ON p.shop_id = s.id 
                         WHERE p.status = 'active' AND s.status = 'active' 
                         ORDER BY RAND() LIMIT 8");
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Xin chào Khách hàng, <?= htmlspecialchars($user['name']) ?>! 👋</h1>
            <p>Hôm nay bạn muốn ăn gì?</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $totalOrders ?></span>
                    <span class="stat-label">Tổng đơn hàng</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $pendingOrders ?></span>
                    <span class="stat-label">Đang xử lý</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $cartCount ?></span>
                    <span class="stat-label">Giỏ hàng</span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($recentOrders)): ?>
        <div class="section">
            <h2>📋 Đơn hàng gần đây</h2>
            <div class="orders-list">
                <?php foreach ($recentOrders as $order): ?>
                <div class="order-item">
                    <div class="order-info">
                        <span class="order-id">#<?= $order['id'] ?></span>
                        <span class="order-shop"><?= htmlspecialchars($order['shop_name']) ?></span>
                    </div>
                    <div class="order-amount"><?= number_format($order['total_amount']) ?>đ</div>
                    <div class="order-status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></div>
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-view">Xem</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>🍕 Món Ngon Phải Thử</h2>
                <a href="set_location.php?update=1" style="color: #3498db; font-size: 14px;">📍 Cập nhật vị trí</a>
            </div>
            <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px;">
                <p style="font-size: 40px;">🍽️</p>
                <p style="color: #7f8c8d;">Không có món ăn nào trong phạm vi <?= $maxDistance ?>km</p>
                <a href="set_location.php?update=1" class="btn-primary" style="display: inline-block; margin-top: 15px; text-decoration: none;">Cập nhật vị trí</a>
            </div>
            <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): 
                    $productImage = $product['image'] ? (strpos($product['image'], 'http') === 0 ? $product['image'] : $base . '/' . $product['image']) : 'https://via.placeholder.com/200x150?text=Food';
                ?>
                <div class="product-card">
                    <img src="<?= $productImage ?>" alt="">
                    <div class="product-info">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="shop-name">🏪 <?= htmlspecialchars($product['shop_name']) ?> <span style="color: #3498db; font-size: 12px;">(<?= number_format($product['distance'], 1) ?>km)</span></p>
                        <p class="price"><?= number_format($product['price']) ?>đ</p>
                        <form method="POST" action="cart.php">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn-add-cart">🛒 Thêm vào giỏ</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
