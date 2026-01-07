<?php
/**
 * Danh sách cửa hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy vị trí user
$stmt = $pdo->prepare("SELECT lat, lng FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userLocation = $stmt->fetch();

// Kiểm tra nếu chưa có vị trí thì redirect
if (!$userLocation['lat'] || !$userLocation['lng']) {
    header('Location: set_location.php');
    exit;
}

$userLat = $userLocation['lat'];
$userLng = $userLocation['lng'];

// Lấy cấu hình khoảng cách tối đa
$stmt = $pdo->query("SELECT max_shop_distance FROM shipping_config LIMIT 1");
$config = $stmt->fetch();
$maxDistance = $config['max_shop_distance'] ?? 15;

// Tìm kiếm
$search = trim($_GET['q'] ?? '');

// Hàm tính khoảng cách Haversine trong SQL
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active') as product_count,
        (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
        (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
        FROM shops s 
        WHERE s.status = 'active' 
        AND s.latitude IS NOT NULL 
        AND s.longitude IS NOT NULL
        HAVING distance <= ?";
$params = [$userLat, $userLng, $userLat, $maxDistance];

if ($search) {
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active') as product_count,
            (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
            (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
            FROM shops s 
            WHERE s.status = 'active' 
            AND s.latitude IS NOT NULL 
            AND s.longitude IS NOT NULL
            AND (s.name LIKE ? OR s.address LIKE ?)
            HAVING distance <= ?";
    $params = [$userLat, $userLng, $userLat, "%$search%", "%$search%", $maxDistance];
}

$sql .= " ORDER BY distance ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();

// Nếu không có shop nào có tọa độ trong phạm vi, lấy tất cả shop
$shopsWithoutCoords = [];
if (empty($shops)) {
    // Lấy tất cả shop active (kể cả không có tọa độ)
    if ($search) {
        $stmt = $pdo->prepare("SELECT s.*, 
                (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active') as product_count,
                (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
                NULL as distance
                FROM shops s 
                WHERE s.status = 'active' 
                AND (s.name LIKE ? OR s.address LIKE ?)
                ORDER BY s.created_at DESC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT s.*, 
                (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active') as product_count,
                (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
                NULL as distance
                FROM shops s 
                WHERE s.status = 'active' 
                ORDER BY s.created_at DESC");
    }
    $shopsWithoutCoords = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cửa hàng - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-box input { flex: 1; padding: 15px; border: 2px solid #fecaca; border-radius: 10px; font-size: 16px; }
        .search-box input:focus { border-color: #dc2626; outline: none; }
        .search-box button { padding: 15px 30px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; border-radius: 10px; cursor: pointer; }
        .shops-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
        .shop-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(220,38,38,0.1); transition: transform 0.2s; border: 2px solid transparent; }
        .shop-card:hover { transform: translateY(-5px); border-color: #fbbf24; box-shadow: 0 10px 30px rgba(220,38,38,0.2); }
        .shop-card img { width: 100%; height: 180px; object-fit: cover; }
        .shop-info { padding: 20px; }
        .shop-info h3 { font-size: 18px; margin-bottom: 8px; }
        .shop-info .address { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; }
        .shop-meta { display: flex; justify-content: space-between; align-items: center; }
        .shop-rating { color: #f39c12; }
        .shop-products { color: #7f8c8d; font-size: 14px; }
        .shop-distance { background: #fef3c7; color: #dc2626; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .location-bar { background: linear-gradient(135deg, #fef3c7, #fde68a); padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #fbbf24; }
        
        /* Hoa mai rơi */
        @keyframes fall { 0% { transform: translateY(-10vh) rotate(0deg); } 100% { transform: translateY(100vh) rotate(360deg); } }
        @keyframes sway { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(20px); } }
        .tet-flowers { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; overflow: hidden; }
        .flower { position: absolute; animation: fall linear infinite, sway ease-in-out infinite; }
    </style>
</head>
<body>
    <!-- Hoa mai rơi -->
    <div class="tet-flowers" id="tetFlowers"></div>
    
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 20px;">🏮 Cửa hàng gần bạn</h1>
        
        <div class="location-bar">
            <span>📍 Đang hiển thị cửa hàng trong bán kính <strong><?= $maxDistance ?>km</strong></span>
            <a href="set_location.php?update=1" style="color: #3498db;">Cập nhật vị trí →</a>
        </div>
        
        <form method="GET" class="search-box">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm cửa hàng...">
            <button type="submit">🔍 Tìm kiếm</button>
        </form>
        
        <?php if (empty($shops)): ?>
        <div class="section" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🏪</p>
            <h2>Không tìm thấy cửa hàng nào trong phạm vi <?= $maxDistance ?>km</h2>
            <p style="color: #7f8c8d; margin-top: 10px;">Có thể cửa hàng chưa cập nhật vị trí trên bản đồ</p>
            <div style="margin-top: 20px;">
                <a href="set_location.php?update=1" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 12px 25px; margin-right: 10px;">📍 Cập nhật vị trí</a>
                <a href="shops_map.php" class="btn-secondary" style="display: inline-block; text-decoration: none; padding: 12px 25px;">🗺️ Xem bản đồ</a>
            </div>
        </div>
        
        <?php if (!empty($shopsWithoutCoords)): ?>
        <div style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">📋 Tất cả cửa hàng (chưa xác định khoảng cách)</h2>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Các cửa hàng này chưa cập nhật vị trí trên bản đồ</p>
            <div class="shops-grid">
                <?php foreach ($shopsWithoutCoords as $shop): 
                    $shopImage = $shop['image'] ? '../' . $shop['image'] : 'https://via.placeholder.com/400x200?text=Shop';
                ?>
                <a href="shop_detail.php?id=<?= $shop['id'] ?>" class="shop-card" style="text-decoration: none; color: inherit;">
                    <img src="<?= $shopImage ?>" alt="<?= htmlspecialchars($shop['name']) ?>" onerror="this.src='https://via.placeholder.com/400x200?text=Shop'">
                    <div class="shop-info">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <h3 style="margin: 0;"><?= htmlspecialchars($shop['name']) ?></h3>
                            <span class="shop-distance" style="background: #f8d7da; color: #721c24;">📍 Chưa xác định</span>
                        </div>
                        <p class="address"><?= htmlspecialchars($shop['address']) ?></p>
                        <div class="shop-meta">
                            <span class="shop-rating">⭐ <?= $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Chưa có' ?></span>
                            <span class="shop-products"><?= $shop['product_count'] ?> món</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <p style="color: #7f8c8d; margin-bottom: 20px;">Tìm thấy <?= count($shops) ?> cửa hàng</p>
        <div class="shops-grid">
            <?php foreach ($shops as $shop): ?>
            <?php 
            $shopImage = $shop['image'];
            if ($shopImage) {
                // Nếu không phải URL đầy đủ, thêm ../
                if (strpos($shopImage, 'http') !== 0) {
                    $shopImage = '../' . $shopImage;
                }
            } else {
                $shopImage = 'https://via.placeholder.com/400x200?text=Shop';
            }
            ?>
            <a href="shop_detail.php?id=<?= $shop['id'] ?>" class="shop-card" style="text-decoration: none; color: inherit;">
                <img src="<?= $shopImage ?>" alt="<?= htmlspecialchars($shop['name']) ?>" onerror="this.src='https://via.placeholder.com/400x200?text=Shop'">
                <div class="shop-info">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                        <h3 style="margin: 0;"><?= htmlspecialchars($shop['name']) ?></h3>
                        <span class="shop-distance">📍 <?= number_format($shop['distance'], 1) ?>km</span>
                    </div>
                    <p class="address"><?= htmlspecialchars($shop['address']) ?></p>
                    <div class="shop-meta">
                        <span class="shop-rating">⭐ <?= $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Chưa có' ?></span>
                        <span class="shop-products"><?= $shop['product_count'] ?> món</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <!-- Script hoa mai -->
    <script>
    (function() {
        const flowers = ['🌸', '🏮', '🧧', '✨', '💮'];
        const container = document.getElementById('tetFlowers');
        if (!container) return;
        
        function createFlower() {
            const flower = document.createElement('div');
            flower.className = 'flower';
            flower.textContent = flowers[Math.floor(Math.random() * flowers.length)];
            flower.style.left = Math.random() * 100 + '%';
            flower.style.fontSize = (15 + Math.random() * 15) + 'px';
            flower.style.animationDuration = (8 + Math.random() * 7) + 's, ' + (3 + Math.random() * 2) + 's';
            flower.style.animationDelay = Math.random() * 5 + 's';
            container.appendChild(flower);
            setTimeout(() => flower.remove(), 15000);
        }
        
        for (let i = 0; i < 10; i++) setTimeout(createFlower, i * 400);
        setInterval(createFlower, 1000);
    })();
    </script>
</body>
</html>
