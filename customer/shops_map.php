<?php
/**
 * Bản đồ hiển thị các cửa hàng gần vị trí khách hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maps_helper.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy vị trí khách hàng
$stmt = $pdo->prepare("SELECT lat, lng, address FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$userLat = $user['lat'] ?? null;
$userLng = $user['lng'] ?? null;

// Lấy cấu hình
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$config = $stmt->fetch();
$maxDistance = $config['max_shop_distance'] ?? 10;

// Lấy danh sách cửa hàng
if ($userLat && $userLng) {
    $shops = getNearbyShops($pdo, $userLat, $userLng, $maxDistance);
} else {
    // Nếu chưa có vị trí, lấy tất cả shop có tọa độ
    $stmt = $pdo->query("SELECT s.*, 
                         (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
                         (SELECT COUNT(*) FROM reviews WHERE shop_id = s.id) as review_count
                         FROM shops s WHERE s.status = 'active' AND s.latitude IS NOT NULL");
    $shops = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bản đồ cửa hàng - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .map-page { display: flex; height: calc(100vh - 70px); }
        #map { flex: 1; }
        .shop-list { width: 350px; background: white; overflow-y: auto; box-shadow: -2px 0 10px rgba(0,0,0,0.1); }
        .shop-list-header { padding: 15px; background: #ff6b35; color: white; position: sticky; top: 0; z-index: 100; }
        .shop-list-header h2 { margin: 0 0 5px; font-size: 18px; }
        .shop-list-header p { margin: 0; font-size: 13px; opacity: 0.9; }
        
        .shop-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; }
        .shop-item:hover { background: #fff5f0; }
        .shop-item.active { background: #fff5f0; border-left: 3px solid #ff6b35; }
        .shop-item h3 { margin: 0 0 5px; font-size: 15px; }
        .shop-item p { margin: 0; font-size: 13px; color: #666; }
        .shop-meta { display: flex; gap: 15px; margin-top: 8px; font-size: 12px; color: #888; }
        .shop-meta span { display: flex; align-items: center; gap: 4px; }
        .shop-distance { background: #e8f4fd; color: #3498db; padding: 3px 8px; border-radius: 10px; font-weight: 600; }
        
        .no-location { padding: 30px; text-align: center; }
        .no-location .icon { font-size: 50px; margin-bottom: 15px; }
        .btn-locate { display: inline-block; padding: 12px 25px; background: #ff6b35; color: white; border-radius: 25px; text-decoration: none; font-weight: 600; }
        
        .filter-bar { padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .filter-bar select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        
        @media (max-width: 768px) {
            .map-page { flex-direction: column-reverse; }
            .shop-list { width: 100%; height: 40vh; }
            #map { height: 60vh; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="map-page">
        <div id="map"></div>
        
        <div class="shop-list">
            <div class="shop-list-header">
                <h2>🏪 Cửa hàng gần bạn</h2>
                <p><?= count($shops) ?> cửa hàng trong phạm vi <?= $maxDistance ?>km</p>
            </div>
            
            <?php if (!$userLat || !$userLng): ?>
            <div class="no-location">
                <div class="icon">📍</div>
                <h3>Chưa có vị trí của bạn</h3>
                <p>Cập nhật vị trí để xem cửa hàng gần nhất</p>
                <a href="set_location.php?update=1" class="btn-locate">Cập nhật vị trí</a>
            </div>
            <?php else: ?>
            
            <div class="filter-bar">
                <select id="sortBy" onchange="sortShops()">
                    <option value="distance">Gần nhất</option>
                    <option value="rating">Đánh giá cao</option>
                    <option value="name">Tên A-Z</option>
                </select>
            </div>
            
            <div id="shopListContent">
            <?php foreach ($shops as $shop): 
                $distance = $shop['distance'] ?? 0;
                $rating = $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Mới';
                $shippingResult = calculateShippingFee($distance, $config, 0);
            ?>
            <div class="shop-item" data-id="<?= $shop['id'] ?>" data-lat="<?= $shop['latitude'] ?>" data-lng="<?= $shop['longitude'] ?>" data-distance="<?= $distance ?>" data-rating="<?= $shop['avg_rating'] ?? 0 ?>" data-name="<?= htmlspecialchars($shop['name']) ?>">
                <h3><?= htmlspecialchars($shop['name']) ?></h3>
                <p><?= htmlspecialchars($shop['address']) ?></p>
                <div class="shop-meta">
                    <span class="shop-distance">📍 <?= number_format($distance, 1) ?>km</span>
                    <span>⭐ <?= $rating ?></span>
                    <span>🚚 <?= number_format($shippingResult['fee']) ?>đ</span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const userLat = <?= $userLat ?: DEFAULT_LAT ?>;
    const userLng = <?= $userLng ?: DEFAULT_LNG ?>;
    const hasLocation = <?= ($userLat && $userLng) ? 'true' : 'false' ?>;
    
    // Khởi tạo bản đồ
    const map = L.map('map').setView([userLat, userLng], hasLocation ? 14 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    // Icon tùy chỉnh
    const userIcon = L.divIcon({
        html: '<div style="font-size: 35px;">📍</div>',
        iconSize: [35, 35],
        className: 'custom-icon'
    });
    const shopIcon = L.divIcon({
        html: '<div style="font-size: 28px;">🏪</div>',
        iconSize: [28, 28],
        className: 'custom-icon'
    });
    
    // Marker vị trí người dùng
    if (hasLocation) {
        L.marker([userLat, userLng], {icon: userIcon}).addTo(map)
            .bindPopup('<b>Vị trí của bạn</b>');
        
        // Vẽ vòng tròn phạm vi 10km
        L.circle([userLat, userLng], {
            color: '#3498db',
            fillColor: '#3498db',
            fillOpacity: 0.08,
            radius: <?= $maxDistance ?> * 1000,
            weight: 2,
            dashArray: '5, 10'
        }).addTo(map).bindTooltip('Phạm vi giao hàng <?= $maxDistance ?>km', {
            permanent: false,
            direction: 'center'
        });
    }
    
    // Markers cửa hàng
    const shopMarkers = {};
    const shops = <?= json_encode($shops) ?>;
    
    shops.forEach(shop => {
        if (shop.latitude && shop.longitude) {
            const marker = L.marker([shop.latitude, shop.longitude], {icon: shopIcon}).addTo(map);
            marker.bindPopup(`
                <b>${shop.name}</b><br>
                ${shop.address}<br>
                <a href="shop_detail.php?id=${shop.id}" style="color: #ff6b35;">Xem menu →</a>
            `);
            shopMarkers[shop.id] = marker;
        }
    });
    
    // Click vào shop trong list
    document.querySelectorAll('.shop-item').forEach(item => {
        item.addEventListener('click', function() {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            const id = this.dataset.id;
            
            // Highlight item
            document.querySelectorAll('.shop-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            // Pan to marker
            map.setView([lat, lng], 16);
            if (shopMarkers[id]) {
                shopMarkers[id].openPopup();
            }
        });
        
        // Double click để mở shop
        item.addEventListener('dblclick', function() {
            window.location.href = 'shop_detail.php?id=' + this.dataset.id;
        });
    });
    
    // Sắp xếp danh sách
    function sortShops() {
        const sortBy = document.getElementById('sortBy').value;
        const container = document.getElementById('shopListContent');
        const items = Array.from(container.querySelectorAll('.shop-item'));
        
        items.sort((a, b) => {
            if (sortBy === 'distance') {
                return parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance);
            } else if (sortBy === 'rating') {
                return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
            } else {
                return a.dataset.name.localeCompare(b.dataset.name);
            }
        });
        
        items.forEach(item => container.appendChild(item));
    }
    
    // Fit bounds nếu có shops
    if (shops.length > 0 && hasLocation) {
        const bounds = L.latLngBounds([[userLat, userLng]]);
        shops.forEach(shop => {
            if (shop.latitude && shop.longitude) {
                bounds.extend([shop.latitude, shop.longitude]);
            }
        });
        map.fitBounds(bounds, {padding: [50, 50]});
    }
    </script>
</body>
</html>
