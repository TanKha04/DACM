<?php
/**
 * Trang chủ FastFood Express
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getConnection();
$base = getBaseUrl();
$isLoggedIn = isLoggedIn();
$isAdmin = $isLoggedIn && isAdmin();
$userRole = $isLoggedIn ? ($_SESSION['user_role'] ?? 'customer') : null;
$isCustomer = !$isLoggedIn || $userRole === 'customer';

// Lấy cấu hình khoảng cách tối đa
$stmt = $pdo->query("SELECT max_shop_distance FROM shipping_config LIMIT 1");
$configDistance = $stmt->fetch();
$maxDistance = $configDistance['max_shop_distance'] ?? 15;

// Kiểm tra vị trí user nếu đã đăng nhập
$userLat = null;
$userLng = null;
$hasLocation = false;

if ($isLoggedIn && $userRole === 'customer') {
    $stmt = $pdo->prepare("SELECT lat, lng FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userLocation = $stmt->fetch();
    if ($userLocation && $userLocation['lat'] && $userLocation['lng']) {
        $userLat = $userLocation['lat'];
        $userLng = $userLocation['lng'];
        $hasLocation = true;
    }
}

// Lấy sản phẩm nổi bật - lọc theo khoảng cách nếu có vị trí
if ($hasLocation) {
    // Lấy sản phẩm từ shop có tọa độ trong phạm vi
    $stmt = $pdo->prepare("SELECT p.*, s.name as shop_name, s.latitude as shop_lat, s.longitude as shop_lng,
            (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
            FROM products p 
            JOIN shops s ON p.shop_id = s.id 
            WHERE p.status = 'active' AND s.status = 'active' 
            AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
            HAVING distance <= ?
            ORDER BY distance ASC, RAND() LIMIT 8");
    $stmt->execute([$userLat, $userLng, $userLat, $maxDistance]);
    $products = $stmt->fetchAll();
    
    // Nếu không có sản phẩm từ shop có tọa độ, lấy tất cả sản phẩm
    if (empty($products)) {
        $stmt = $pdo->query("SELECT p.*, s.name as shop_name, s.latitude as shop_lat, s.longitude as shop_lng, NULL as distance 
                             FROM products p 
                             JOIN shops s ON p.shop_id = s.id 
                             WHERE p.status = 'active' AND s.status = 'active' 
                             ORDER BY RAND() LIMIT 8");
        $products = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->query("SELECT p.*, s.name as shop_name FROM products p 
                         JOIN shops s ON p.shop_id = s.id 
                         WHERE p.status = 'active' AND s.status = 'active' 
                         ORDER BY RAND() LIMIT 8");
    $products = $stmt->fetchAll();
}

// Lấy danh sách cửa hàng - lọc theo khoảng cách nếu có vị trí
if ($hasLocation) {
    $stmt = $pdo->prepare("SELECT s.*,
            (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
            FROM shops s 
            WHERE s.status = 'active' 
            AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL
            HAVING distance <= ?
            ORDER BY distance ASC LIMIT 6");
    $stmt->execute([$userLat, $userLng, $userLat, $maxDistance]);
    $shops = $stmt->fetchAll();
    
    // Nếu không có shop có tọa độ, lấy tất cả shop
    if (empty($shops)) {
        $stmt = $pdo->query("SELECT *, NULL as distance FROM shops WHERE status = 'active' ORDER BY created_at DESC LIMIT 6");
        $shops = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->query("SELECT * FROM shops WHERE status = 'active' ORDER BY created_at DESC LIMIT 6");
    $shops = $stmt->fetchAll();
}

// Danh mục - Lấy từ database
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY position ASC, name ASC");
$categories = $stmt->fetchAll();

// Fallback nếu chưa có bảng categories
if (empty($categories)) {
    $categories = [
        ['name' => 'Burger', 'icon' => '🍔', 'slug' => 'burger'],
        ['name' => 'Pizza', 'icon' => '🍕', 'slug' => 'pizza'],
        ['name' => 'Gà Rán', 'icon' => '🍗', 'slug' => 'ga-ran'],
        ['name' => 'Mỳ Ý', 'icon' => '🍝', 'slug' => 'my-y'],
        ['name' => 'Đồ Uống', 'icon' => '🥤', 'slug' => 'do-uong'],
        ['name' => 'Tráng Miệng', 'icon' => '🍰', 'slug' => 'trang-mieng'],
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FastFood Express - Giao hàng nhanh trong 30 phút</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fff; color: #1a1a1a; }
        
        /* Header */
        .header {
            background: #fff;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 20px;
            color: #1a1a1a;
            text-decoration: none;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            background: #ff4d4d;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        .search-box {
            flex: 1;
            max-width: 450px;
            margin: 0 40px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 30px;
            font-size: 14px;
            background: #f8f8f8;
        }
        .search-box input:focus { outline: none; border-color: #ff4d4d; background: #fff; }
        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-actions a {
            color: #1a1a1a;
            text-decoration: none;
            font-size: 22px;
            position: relative;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4d4d;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-login {
            background: #ff4d4d;
            color: white !important;
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }
        .user-dropdown {
            position: relative;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #ff6b35, #ff4d4d);
            padding: 8px 16px 8px 8px;
            border-radius: 30px;
            text-decoration: none;
            color: white !important;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .user-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,77,77,0.3);
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #ff4d4d;
            position: relative;
        }
        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: #2ecc71;
            border-radius: 50%;
            border: 2px solid #ff6b35;
        }
        .user-name {
            font-weight: 500;
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            padding-top: 10px;
            background: transparent;
            min-width: 250px;
            display: none;
            z-index: 1000;
        }
        .dropdown-menu-inner {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 15px 0;
        }
        .user-dropdown:hover .dropdown-menu {
            display: block;
        }
        .dropdown-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        .dropdown-header .avatar-large {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ff6b35, #ff4d4d);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: white;
        }
        .dropdown-header .user-info h4 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 3px;
        }
        .dropdown-header .user-info p {
            font-size: 13px;
            color: #888;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-size: 15px;
            transition: background 0.2s;
        }
        .dropdown-menu a:hover {
            background: #f5f5f5;
        }
        .dropdown-menu a.logout {
            color: #e74c3c;
            border-top: 1px solid #eee;
            margin-top: 10px;
            padding-top: 15px;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .hero-badge {
            display: inline-block;
            background: #ff4d4d;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .hero h1 {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .hero h1 span { color: #ff4d4d; font-style: italic; }
        .hero p {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .hero-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        .btn-primary {
            background: #ff4d4d;
            color: white;
            padding: 14px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(255,77,77,0.3); }
        .btn-outline {
            background: white;
            color: #1a1a1a;
            padding: 14px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border: 1px solid #ddd;
        }
        .hero-stats {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .hero-avatars {
            display: flex;
        }
        .hero-avatars img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid white;
            margin-left: -10px;
        }
        .hero-avatars img:first-child { margin-left: 0; }
        .hero-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .hero-rating .star { color: #ffc107; }
        .hero-rating strong { font-size: 15px; }
        .hero-rating span { color: #999; font-size: 13px; }
        .hero-image {
            position: relative;
        }
        .hero-image img {
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
        }
        .hero-product-card {
            position: absolute;
            bottom: 30px;
            left: 30px;
            background: white;
            padding: 12px 20px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hero-product-card img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
        }
        .hero-product-card .badge { 
            background: #ff4d4d; 
            color: white; 
            font-size: 10px; 
            padding: 3px 8px; 
            border-radius: 10px; 
            margin-bottom: 3px;
            display: inline-block;
        }
        .hero-product-card h4 { font-size: 14px; font-weight: 600; }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Section */
        .section {
            padding: 60px 0;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 26px;
            font-weight: 700;
        }
        .section-link {
            color: #ff4d4d;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
</style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <a href="home.php" class="logo">
                <img src="logo.png" alt="Logo" style="width: 36px; height: 36px; border-radius: 8px;">
                FastFood Express
            </a>
            <div class="search-box">
                <input type="text" placeholder="Tìm kiếm món ăn, đồ uống...">
            </div>
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                <?php if (!$isAdmin): ?>
                <a href="customer/cart.php" title="Giỏ hàng">🛒<span class="cart-badge">0</span></a>
                <?php endif; ?>
                <div class="user-dropdown">
                    <div class="user-profile">
                        <span class="user-avatar"><?= mb_substr($_SESSION['user_name'] ?? 'U', 0, 1) ?></span>
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-menu-inner">
                            <div class="dropdown-header">
                                <div class="avatar-large"><?= mb_substr($_SESSION['user_name'] ?? 'U', 0, 1) ?></div>
                                <div class="user-info">
                                    <h4><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></h4>
                                    <p><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                                </div>
                            </div>
                            <?php if ($isAdmin): ?>
                            <a href="admin/profile.php">👤 Hồ sơ</a>
                            <a href="admin/settings.php">⚙️ Cài đặt</a>
                            <?php else: ?>
                            <?php if ($userRole !== 'seller'): ?>
                                <a href="customer/orders.php">📦 Đơn hàng của tôi</a>
                                <a href="customer/profile.php">👤 Hồ sơ</a>
                                <a href="customer/notifications.php">🔔 Thông báo</a>
                                <a href="customer/support.php">🎧 Hỗ trợ</a>
                                <a href="customer/cart.php">🛒 Giỏ hàng</a>
                            <?php endif; ?>
                            <?php endif; ?>
                            <a href="auth/logout.php" class="logout">🚪 Đăng xuất</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <a href="auth/login.php" class="btn-login">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-content">
                <span class="hero-badge">KHUYẾN MÃI CỰC HOT 🔥</span>
                <h1>Vị Ngon Khó Cưỡng<br><span>Giao Trong 30 Phút</span></h1>
                <p>Thưởng thức ngay những món ăn nóng hổi. Giảm giá 20% cho đơn hàng đầu tiên của bạn.</p>
                <div class="hero-buttons">
                    <?php if ($isAdmin): ?>
                    <a href="admin/dashboard.php" class="btn-primary">Vào Quản Trị</a>
                    <?php elseif ($userRole === 'seller'): ?>
                    <a href="seller/dashboard.php" class="btn-primary">Quản lý cửa hàng</a>
                    <?php elseif ($userRole === 'shipper'): ?>
                    <a href="shipper/dashboard.php" class="btn-primary">Bắt đầu giao hàng</a>
                    <?php else: ?>
                    <a href="<?= $isLoggedIn ? 'customer/shops.php' : 'auth/register.php' ?>" class="btn-primary">Đặt Ngay</a>
                    <a href="customer/shops.php" class="btn-outline">Xem Menu</a>
                    <?php endif; ?>
                </div>
                <div class="hero-stats">
                    <div class="hero-avatars">
                        <img src="https://i.pravatar.cc/100?img=1" alt="">
                        <img src="https://i.pravatar.cc/100?img=2" alt="">
                        <img src="https://i.pravatar.cc/100?img=3" alt="">
                    </div>
                    <div class="hero-rating">
                        <span class="star">⭐</span>
                        <strong>4.9</strong>
                        <span>(10k+ đánh giá)</span>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/images/hero-burger.jpg" alt="Burger" onerror="this.src='https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&h=500&fit=crop'">
                <div class="hero-product-card">
                    <img src="assets/images/burger-thumb.jpg" alt="" onerror="this.src='https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=100&h=100&fit=crop'">
                    <div>
                        <span class="badge">BÁN CHẠY NHẤT</span>
                        <h4>Double Cheese Burger</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section - Ẩn với Seller -->
    <?php if ($userRole !== 'seller'): ?>
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Danh Mục Món Ăn</h2>
                <a href="customer/shops.php" class="section-link">Xem tất cả →</a>
            </div>
            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                <a href="customer/shops.php?category=<?= htmlspecialchars($cat['slug']) ?>" class="category-item">
                    <?php if (!empty($cat['image'])): ?>
                    <div class="category-icon" style="background-image: url('<?= $base . '/' . $cat['image'] ?>'); background-size: cover; background-position: center;"></div>
                    <?php else: ?>
                    <div class="category-icon"><?= $cat['icon'] ?? '🍽️' ?></div>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Products Section - Chỉ hiển thị khi có sản phẩm -->
    <?php if (!empty($products)): ?>
    <section class="section" style="background: #fafafa;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Món Ngon Phải Thử</h2>
                <?php if ($hasLocation): ?>
                <span style="color: #3498db; font-size: 14px;">📍 Trong phạm vi <?= $maxDistance ?>km</span>
                <?php endif; ?>
                <div class="nav-arrows">
                    <button class="nav-arrow">‹</button>
                    <button class="nav-arrow">›</button>
                </div>
            </div>
            <div class="products-grid">
                <?php foreach (array_slice($products, 0, 4) as $product): 
                    $productImage = $product['image'] ? (strpos($product['image'], 'http') === 0 ? $product['image'] : $base . '/' . $product['image']) : 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=300&h=250&fit=crop';
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= $productImage ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <span class="product-rating">⭐ 4.<?= rand(5,9) ?></span>
                        <?php if ($hasLocation && isset($product['distance'])): ?>
                        <span style="position: absolute; bottom: 12px; left: 12px; background: #3498db; color: white; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600;"><?= number_format($product['distance'], 1) ?>km</span>
                        <?php endif; ?>
                        <button class="product-fav">♡</button>
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars(mb_substr($product['description'] ?? 'Món ăn ngon từ ' . $product['shop_name'], 0, 60)) ?>...</p>
                        <div class="product-footer">
                            <span class="product-price"><?= number_format($product['price']) ?>đ</span>
                                <form method="POST" action="customer/cart.php" style="display:inline;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="product-add" title="Thêm vào giỏ hàng">+</button>
                                </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php elseif ($hasLocation): ?>
    <section class="section" style="background: #fafafa;">
        <div class="container">
            <div style="text-align: center; padding: 50px;">
                <p style="font-size: 50px; margin-bottom: 15px;">🍽️</p>
                <h3>Không có món ăn nào trong phạm vi <?= $maxDistance ?>km</h3>
                <p style="color: #7f8c8d; margin-top: 10px;">Hãy thử cập nhật vị trí hoặc mở rộng phạm vi tìm kiếm</p>
                <a href="customer/set_location.php?update=1" style="display: inline-block; margin-top: 20px; background: #ff4d4d; color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none;">📍 Cập nhật vị trí</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Promo Banner -->
    <section class="section">
        <div class="container">
            <div class="promo-banner">
                <div class="promo-content">
                    <span class="promo-badge">ƯU ĐÃI GIỚI HẠN</span>
                    <h2>Combo Gia Đình<br>Tiết Kiệm Đến 40%</h2>
                    <p>Đại tiệc cuối tuần với 2 Pizza lớn, 1 Gà rán xô, 4 Nước ngọt và Khoai tây chiên.</p>
                    <?php if ($userRole !== 'seller'): ?>
                    <a href="customer/shops.php" class="btn-promo">Đặt Combo Ngay</a>
                    <?php endif; ?>
                </div>
                <div class="promo-image">
                    <img src="assets/images/combo-promo.jpg" alt="Combo" onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400&h=300&fit=crop'">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <a href="home.php" class="logo" style="margin-bottom: 15px; display: inline-flex;">
                        <img src="logo.png" alt="Logo" style="width: 36px; height: 36px; border-radius: 8px;">
                        FastFood Express
                    </a>
                    <p style="color: #666; font-size: 14px; line-height: 1.6;">Trải nghiệm vị tuyệt vời trong từng bữa ăn, giao hàng nhanh chóng tận nơi.</p>
                </div>
                <div class="footer-col">
                    <h4>Về chúng tôi</h4>
                    <ul>
                        <li><a href="#">Giới thiệu</a></li>
                        <li><a href="#">Cơ hội nghề nghiệp</a></li>
                        <li><a href="#">Tin tức</a></li>
                        <li><a href="#">Đối tác</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Hỗ trợ</h4>
                    <ul>
                        <li><a href="#">Trung tâm trợ giúp</a></li>
                        <li><a href="#">Chính sách bảo mật</a></li>
                        <li><a href="#">Điều khoản sử dụng</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Nhận ưu đãi mới nhất</h4>
                    <div class="newsletter">
                        <input type="email" placeholder="Email của bạn">
                        <button>→</button>
                    </div>
                    <div class="social-links">
                        <a href="#">📘</a>
                        <a href="#">📷</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2024 FastFood Express. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <style>
        /* Categories */
        .categories-grid {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #1a1a1a;
            padding: 15px 25px;
            transition: transform 0.2s;
        }
        .category-item:hover { transform: translateY(-5px); }
        .category-icon {
            width: 70px;
            height: 70px;
            background: #fff5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            transition: background 0.2s;
        }
        .category-item:hover .category-icon { background: #ffe0e0; }
        .category-item span { font-weight: 500; font-size: 14px; }
        
        /* Products */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        .product-image {
            position: relative;
            height: 180px;
            overflow: hidden;
        }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-rating {
            position: absolute;
            top: 12px;
            left: 12px;
            background: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-fav {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            background: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .product-fav:hover { background: #ff4d4d; color: white; }
        .product-info { padding: 20px; }
        .product-info h3 { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .product-info p { color: #888; font-size: 13px; line-height: 1.5; margin-bottom: 15px; }
        .product-footer { display: flex; justify-content: space-between; align-items: center; }
        .product-price { font-size: 18px; font-weight: 700; color: #ff4d4d; }
        .product-add {
            width: 36px;
            height: 36px;
            background: #ff4d4d;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .product-add:hover { transform: scale(1.1); }
        .nav-arrows { display: flex; gap: 10px; }
        .nav-arrow {
            width: 40px;
            height: 40px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
        }
        .nav-arrow:hover { background: #f5f5f5; }
        
        /* Promo Banner */
        .promo-banner {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            border-radius: 25px;
            padding: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            overflow: hidden;
        }
        .promo-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .promo-content h2 { color: white; font-size: 36px; font-weight: 800; line-height: 1.2; margin-bottom: 15px; }
        .promo-content p { color: rgba(255,255,255,0.9); font-size: 15px; margin-bottom: 25px; }
        .btn-promo {
            display: inline-block;
            background: white;
            color: #ff6b35;
            padding: 14px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
        }
        .promo-image img { width: 100%; max-width: 350px; border-radius: 15px; }
        
        /* Footer */
        .footer { background: #fafafa; padding: 60px 0 30px; margin-top: 60px; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.5fr; gap: 40px; margin-bottom: 40px; }
        .footer-col h4 { font-size: 15px; font-weight: 600; margin-bottom: 20px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 12px; }
        .footer-col ul a { color: #666; text-decoration: none; font-size: 14px; }
        .footer-col ul a:hover { color: #ff4d4d; }
        .newsletter { display: flex; gap: 10px; margin-bottom: 20px; }
        .newsletter input { flex: 1; padding: 12px 16px; border: 1px solid #ddd; border-radius: 25px; font-size: 14px; }
        .newsletter button { width: 44px; height: 44px; background: #ff4d4d; color: white; border: none; border-radius: 50%; cursor: pointer; font-size: 18px; }
        .social-links { display: flex; gap: 10px; }
        .social-links a { width: 40px; height: 40px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; }
        .footer-bottom { border-top: 1px solid #eee; padding-top: 20px; text-align: center; color: #999; font-size: 13px; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .hero-inner { grid-template-columns: 1fr; text-align: center; }
            .hero-image { display: none; }
            .hero-buttons { justify-content: center; }
            .hero-stats { justify-content: center; }
            .products-grid { grid-template-columns: repeat(2, 1fr); }
            .promo-banner { grid-template-columns: 1fr; text-align: center; }
            .promo-image { display: none; }
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .hero h1 { font-size: 36px; }
            .products-grid { grid-template-columns: 1fr; }
            .categories-grid { gap: 10px; }
            .category-icon { width: 55px; height: 55px; font-size: 24px; }
            .search-box { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</body>
</html>
