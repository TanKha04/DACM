<?php
/**
 * Customer Dashboard - Trang chủ người mua
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maps_helper.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Kiểm tra nếu chưa có vị trí - hiển thị popup thay vì redirect
$needLocation = !$user['lat'] || !$user['lng'];

$userLat = $user['lat'] ?? DEFAULT_LAT;
$userLng = $user['lng'] ?? DEFAULT_LNG;

// Khoảng cách tối đa - lấy từ config hoặc mặc định 20km
$stmt = $pdo->query("SELECT max_shop_distance FROM shipping_config LIMIT 1");
$configDistance = $stmt->fetchColumn();
$maxDistance = $configDistance ?: 15; // Mặc định 15km

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

// Lấy cửa hàng gần nhất - chỉ shop có tọa độ
$nearbyShops = [];
$shopsWithoutCoords = [];

if (!$needLocation) {
    // Lấy shop có tọa độ trong phạm vi
    $nearbyShops = getNearbyShops($pdo, $userLat, $userLng, $maxDistance);
    
    // Nếu không có shop nào trong phạm vi, lấy tất cả shop active
    if (empty($nearbyShops)) {
        $stmt = $pdo->query("SELECT s.*, 
            (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE shop_id = s.id) as review_count,
            NULL as distance
            FROM shops s WHERE s.status = 'active' LIMIT 10");
        $shopsWithoutCoords = $stmt->fetchAll();
    }
}

// Sản phẩm nổi bật - chỉ từ cửa hàng trong phạm vi 5km
$products = [];
if (!$needLocation && !empty($nearbyShops)) {
    $shopIds = array_column($nearbyShops, 'id');
    $placeholders = implode(',', array_fill(0, count($shopIds), '?'));
    $stmt = $pdo->prepare("SELECT p.*, s.name as shop_name,
            (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
            FROM products p 
            JOIN shops s ON p.shop_id = s.id 
            WHERE p.status = 'active' AND s.id IN ($placeholders)
            ORDER BY RAND() LIMIT 8");
    $params = array_merge([$userLat, $userLng, $userLat], $shopIds);
    $stmt->execute($params);
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        /* Hiệu ứng Tết */
        @keyframes fall { 0% { transform: translateY(-10vh) rotate(0deg); } 100% { transform: translateY(100vh) rotate(360deg); } }
        @keyframes sway { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(20px); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .tet-flowers { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; overflow: hidden; }
        .flower { position: absolute; animation: fall linear infinite, sway ease-in-out infinite; }
        
        /* Banner Tết */
        .tet-banner {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fef3c7;
            padding: 12px 25px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(220,38,38,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .stat-card { animation: fadeInUp 0.5s ease forwards; opacity: 0; border: 2px solid #fecaca !important; }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        
        .section { animation: fadeInUp 0.5s ease 0.4s forwards; opacity: 0; border: 2px solid #fecaca !important; }
    </style>
</head>
<body>
    <!-- Hoa mai rơi -->
    <div class="tet-flowers" id="tetFlowers"></div>
    
    <?php include '../includes/customer_header.php'; ?>
    
    <!-- Popup yêu cầu định vị -->
    <?php if ($needLocation): ?>
    <div id="locationModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 20px; padding: 40px; max-width: 500px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="font-size: 60px; margin-bottom: 20px;">📍</div>
            <h2 style="color: #2c3e50; margin-bottom: 15px;">Xin chào, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p style="color: #7f8c8d; margin-bottom: 25px;">Để tìm cửa hàng gần bạn nhất và tính phí ship chính xác, vui lòng cho phép truy cập vị trí.</p>
            
            <div id="locationStatus" style="margin-bottom: 20px; padding: 15px; border-radius: 10px; display: none;"></div>
            
            <button id="btnGetLocation" onclick="requestLocation()" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; margin-bottom: 15px;">
                🎯 Cho phép truy cập vị trí
            </button>
            
            <a href="set_location.php" style="color: #7f8c8d; font-size: 14px;">Hoặc chọn vị trí thủ công →</a>
        </div>
    </div>
    
    <script>
    function requestLocation() {
        const statusEl = document.getElementById('locationStatus');
        const btn = document.getElementById('btnGetLocation');
        
        if (!navigator.geolocation) {
            statusEl.style.display = 'block';
            statusEl.style.background = '#f8d7da';
            statusEl.style.color = '#721c24';
            statusEl.textContent = 'Trình duyệt không hỗ trợ định vị. Vui lòng chọn vị trí thủ công.';
            return;
        }
        
        btn.disabled = true;
        btn.textContent = '⏳ Đang lấy vị trí...';
        statusEl.style.display = 'block';
        statusEl.style.background = '#d1ecf1';
        statusEl.style.color = '#0c5460';
        statusEl.textContent = 'Đang xác định vị trí của bạn...';
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                
                statusEl.style.background = '#d4edda';
                statusEl.style.color = '#155724';
                statusEl.textContent = '✓ Đã lấy vị trí! Đang lưu...';
                
                // Gửi lên server
                fetch('<?= getBaseUrl() ?>/api/save_location.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `lat=${lat}&lng=${lng}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        statusEl.textContent = '✓ Đã lưu vị trí! Đang tải lại trang...';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(err => {
                    statusEl.style.background = '#f8d7da';
                    statusEl.style.color = '#721c24';
                    statusEl.textContent = 'Lỗi: ' + err.message;
                    btn.disabled = false;
                    btn.textContent = '🎯 Thử lại';
                });
            },
            function(err) {
                statusEl.style.background = '#f8d7da';
                statusEl.style.color = '#721c24';
                let msg = 'Không thể lấy vị trí';
                if (err.code === 1) msg = 'Bạn đã từ chối quyền truy cập vị trí';
                else if (err.code === 2) msg = 'Không thể xác định vị trí';
                else if (err.code === 3) msg = 'Hết thời gian chờ';
                statusEl.textContent = msg + '. Vui lòng chọn vị trí thủ công.';
                btn.disabled = false;
                btn.textContent = '🎯 Thử lại';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }
    </script>
    <?php endif; ?>
    
    <div class="container">
        <!-- Banner Tết -->
        <div class="tet-banner">
            <span>🧧</span>
            <span>🌸 Chúc Mừng Năm Mới 2026 - An Khang Thịnh Vượng 🌸</span>
            <span>🧧</span>
        </div>
        
        <!-- Welcome Banner -->
        <style>
            .welcome-banner {
                background: linear-gradient(135deg, rgba(185,28,28,0.9) 0%, rgba(220,38,38,0.85) 100%), url('https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1200&h=400&fit=crop');
                background-size: cover;
                background-position: center;
                border-radius: 20px;
                padding: 30px 40px;
                color: white;
                margin-bottom: 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
                overflow: hidden;
                border: 3px solid #fbbf24;
                box-shadow: 0 10px 30px rgba(220,38,38,0.3);
            }
            .welcome-banner::before {
                content: '🏮';
                position: absolute;
                top: 10px;
                left: 15px;
                font-size: 30px;
                animation: swing 2s ease-in-out infinite;
            }
            .welcome-banner::after {
                content: '🏮';
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 30px;
                animation: swing 2s ease-in-out infinite reverse;
            }
            @keyframes swing { 0%, 100% { transform: rotate(-10deg); } 50% { transform: rotate(10deg); } }
            .welcome-banner h2 {
                font-size: 28px;
                font-weight: 700;
                font-style: italic;
                margin-bottom: 15px;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            }
            .welcome-badges {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
            }
            .welcome-badge {
                background: rgba(251,191,36,0.3);
                border: 1px solid #fbbf24;
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 6px;
                color: #fef3c7;
            }
            .welcome-text {
                opacity: 0.9;
                font-size: 14px;
            }
            .welcome-logo {
                text-align: center;
            }
            .welcome-logo img {
                width: 150px;
                height: 150px;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                border: 3px solid #fbbf24;
            }
            .welcome-actions {
                display: flex;
                gap: 12px;
            }
            .welcome-btn {
                background: rgba(251,191,36,0.3);
                border: 2px solid #fbbf24;
                color: #fef3c7;
                padding: 10px 20px;
                border-radius: 25px;
                text-decoration: none;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s;
                font-weight: 600;
            }
            .welcome-btn:hover {
                background: rgba(251,191,36,0.5);
                transform: translateY(-2px);
            }
        </style>
        
        <?php
        $hour = date('H');
        if ($hour < 12) $greeting = 'Chào buổi sáng';
        elseif ($hour < 18) $greeting = 'Chào buổi chiều';
        else $greeting = 'Chào buổi tối';
        // Lời chúc Tết
        $tetGreetings = ['🧧 Năm mới Phát Tài!', '🌸 Vạn Sự Như Ý!', '🏮 An Khang Thịnh Vượng!'];
        $tetGreeting = $tetGreetings[array_rand($tetGreetings)];
        ?>
        
        <div class="welcome-banner">
            <div>
                <h2><?= $greeting ?>, <?= htmlspecialchars($user['name']) ?>! <?= $tetGreeting ?></h2>
                <div class="welcome-badges">
                    <span class="welcome-badge">👤 Khách hàng</span>
                    <span class="welcome-badge">✅ Đã xác minh</span>
                </div>
                <p class="welcome-text">Hôm nay bạn muốn ăn gì?</p>
            </div>
            <div class="welcome-logo">
                <img src="../logo.png" alt="Logo">
            </div>
            <div class="welcome-actions">
                <a href="shops.php" class="welcome-btn">🏪 Cửa hàng</a>
                <a href="orders.php" class="welcome-btn">📦 Đơn hàng</a>
                <a href="cart.php" class="welcome-btn">🛒 Giỏ hàng</a>
            </div>
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
        
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>🏪 Cửa hàng gần bạn (trong <?= $maxDistance ?>km)</h2>
                <div>
                    <a href="shops_map.php" style="color: #3498db; font-size: 14px; margin-right: 15px;">🗺️ Xem bản đồ</a>
                    <a href="set_location.php?update=1" style="color: #27ae60; font-size: 14px;">📍 Cập nhật vị trí</a>
                </div>
            </div>
            
            <?php if (empty($nearbyShops)): ?>
            <div style="text-align: center; padding: 40px; background: #fff3cd; border-radius: 15px; border: 1px solid #ffc107;">
                <p style="font-size: 50px; margin-bottom: 15px;">🏪</p>
                <h3 style="color: #856404; margin-bottom: 10px;">Không tìm thấy cửa hàng nào trong phạm vi <?= $maxDistance ?>km</h3>
                <p style="color: #856404; margin-bottom: 20px;">Có thể cửa hàng chưa cập nhật vị trí hoặc bạn cần cập nhật vị trí của mình</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <a href="set_location.php?update=1" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 12px 25px;">📍 Cập nhật vị trí</a>
                    <a href="shops_map.php" class="btn-secondary" style="display: inline-block; text-decoration: none; padding: 12px 25px; background: #6c757d; color: white; border-radius: 8px;">🗺️ Xem bản đồ</a>
                </div>
            </div>
            
            <?php if (!empty($shopsWithoutCoords)): ?>
            <div style="margin-top: 30px;">
                <h3 style="color: #666; margin-bottom: 15px;">📋 Tất cả cửa hàng (chưa xác định khoảng cách)</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($shopsWithoutCoords as $shop): 
                        $shopImage = $shop['image'] ? '../' . $shop['image'] : 'https://via.placeholder.com/300x150?text=' . urlencode($shop['name']);
                        $rating = $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Mới';
                    ?>
                    <a href="shop_detail.php?id=<?= $shop['id'] ?>" style="text-decoration: none; color: inherit;">
                        <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s;">
                            <div style="height: 120px; background: url('<?= $shopImage ?>') center/cover; position: relative;">
                                <span style="position: absolute; top: 10px; right: 10px; background: rgba(108,117,125,0.8); color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px;">
                                    📍 Chưa xác định
                                </span>
                            </div>
                            <div style="padding: 15px;">
                                <h3 style="margin: 0 0 8px; font-size: 16px; color: #2c3e50;"><?= htmlspecialchars($shop['name']) ?></h3>
                                <p style="margin: 0 0 10px; font-size: 13px; color: #7f8c8d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($shop['address']) ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #f39c12; font-weight: 600;">⭐ <?= $rating ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach (array_slice($nearbyShops, 0, 6) as $shop): 
                    $shopImage = $shop['image'] ? '../' . $shop['image'] : 'https://via.placeholder.com/300x150?text=' . urlencode($shop['name']);
                    $rating = $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Mới';
                    $shippingResult = calculateShippingFee($shop['distance'], ['base_fee' => 15000, 'price_per_km' => 5000], 0);
                ?>
                <a href="shop_detail.php?id=<?= $shop['id'] ?>" style="text-decoration: none; color: inherit;">
                    <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s;">
                        <div style="height: 120px; background: url('<?= $shopImage ?>') center/cover; position: relative;">
                            <span style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 5px 12px; border-radius: 15px; font-size: 13px;">
                                📍 <?= number_format($shop['distance'], 1) ?>km
                            </span>
                        </div>
                        <div style="padding: 15px;">
                            <h3 style="margin: 0 0 8px; font-size: 16px; color: #2c3e50;"><?= htmlspecialchars($shop['name']) ?></h3>
                            <p style="margin: 0 0 10px; font-size: 13px; color: #7f8c8d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($shop['address']) ?></p>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #f39c12; font-weight: 600;">⭐ <?= $rating ?></span>
                                <span style="color: #27ae60; font-size: 13px;">🚚 <?= number_format($shippingResult['fee']) ?>đ</span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($nearbyShops) > 6): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="shops.php" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 12px 30px;">Xem thêm <?= count($nearbyShops) - 6 ?> cửa hàng →</a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>🍕 Món Ngon Phải Thử</h2>
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
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <!-- Script hoa mai rơi -->
    <script>
    (function() {
        const flowers = ['🌸', '🏮', '🧧', '✨', '💮', '🎊'];
        const container = document.getElementById('tetFlowers');
        if (!container) return;
        
        function createFlower() {
            const flower = document.createElement('div');
            flower.className = 'flower';
            flower.textContent = flowers[Math.floor(Math.random() * flowers.length)];
            flower.style.left = Math.random() * 100 + '%';
            flower.style.fontSize = (15 + Math.random() * 20) + 'px';
            flower.style.animationDuration = (8 + Math.random() * 7) + 's, ' + (3 + Math.random() * 2) + 's';
            flower.style.animationDelay = Math.random() * 3 + 's';
            container.appendChild(flower);
            setTimeout(() => flower.remove(), 15000);
        }
        
        // Tạo hoa ban đầu
        for (let i = 0; i < 15; i++) setTimeout(createFlower, i * 300);
        // Tiếp tục tạo hoa
        setInterval(createFlower, 800);
    })();
    </script>
</body>
</html>
