<?php
/**
 * Shipper - Xem bản đồ đơn hàng (vị trí shop và khách hàng)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maps_helper.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: dashboard.php');
    exit;
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, 
           s.name as shop_name, s.address as shop_address, s.phone as shop_phone,
           s.latitude as shop_lat, s.longitude as shop_lng,
           c.name as customer_name, c.phone as customer_phone, c.lat as customer_lat, c.lng as customer_lng
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    JOIN users c ON o.customer_id = c.id
    WHERE o.id = ? AND (o.shipper_id = ? OR o.shipper_id IS NULL)
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

// Tọa độ giao hàng
$deliveryLat = $order['delivery_lat'] ?? $order['customer_lat'] ?? DEFAULT_LAT;
$deliveryLng = $order['delivery_lng'] ?? $order['customer_lng'] ?? DEFAULT_LNG;
$shopLat = $order['shop_lat'] ?? DEFAULT_LAT;
$shopLng = $order['shop_lng'] ?? DEFAULT_LNG;

// Lấy vị trí shipper
$stmt = $pdo->prepare("SELECT current_lat, current_lng FROM shipper_info WHERE user_id = ?");
$stmt->execute([$userId]);
$shipperInfo = $stmt->fetch();

// Tính khoảng cách
$distance = calculateDistance($shopLat, $shopLng, $deliveryLat, $deliveryLng);
$deliveryTime = estimateDeliveryTime($distance);

$statusLabels = [
    'pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'preparing' => 'Đang chuẩn bị',
    'ready' => 'Sẵn sàng giao', 'picked' => 'Đã lấy hàng', 'delivering' => 'Đang giao',
    'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bản đồ đơn #<?= $orderId ?> - Shipper</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        
        .page-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: linear-gradient(180deg, #22c55e, #16a34a); color: white; padding: 20px 0; position: fixed; height: 100vh; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar-header a { color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; justify-content: center; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.2); color: white; }
        
        /* Main content */
        .main-area { margin-left: 250px; flex: 1; display: flex; height: 100vh; }
        
        /* Map */
        .map-section { flex: 1; position: relative; }
        #map { width: 100%; height: 100%; }
        
        .legend { position: absolute; bottom: 20px; left: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 1000; }
        .legend-item { display: flex; align-items: center; gap: 10px; margin: 5px 0; font-size: 13px; }
        
        /* Info panel */
        .info-panel { width: 350px; background: white; overflow-y: auto; box-shadow: -2px 0 10px rgba(0,0,0,0.1); }
        .panel-header { padding: 20px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .panel-header h2 { margin: 0 0 5px; font-size: 20px; }
        
        .distance-card { background: #e8f5e9; padding: 15px; border-radius: 10px; margin: 15px; text-align: center; }
        .distance-card .value { font-size: 28px; font-weight: bold; color: #27ae60; }
        .distance-card .label { color: #666; font-size: 13px; }
        
        .info-section { padding: 15px; border-bottom: 1px solid #eee; }
        .info-section h3 { margin: 0 0 10px; font-size: 14px; color: #666; }
        .info-section p { margin: 5px 0; color: #333; }
        .highlight { font-weight: 600; color: #ff6b35; }
        
        .btn-action { display: block; margin: 15px; padding: 15px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-call { background: #3498db; color: white; }
        .btn-back { background: #eee; color: #333; }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="../home.php">
                    <img src="../logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px;">
                    <span style="font-size: 18px; font-weight: bold;">Shipper</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><span>📊</span> Dashboard</a>
                <a href="available.php"><span>📦</span> Đơn có sẵn</a>
                <a href="my_orders.php"><span>🚚</span> Đơn của tôi</a>
                <a href="location_tracker.php"><span>📍</span> Theo dõi vị trí</a>
                <a href="earnings.php"><span>💵</span> Thu nhập</a>
                <a href="../auth/logout.php"><span>🚪</span> Đăng xuất</a>
            </nav>
        </div>
        
        <!-- Main area -->
        <div class="main-area">
            <!-- Map -->
            <div class="map-section">
                <div id="map"></div>
                <div class="legend">
                    <div class="legend-item"><span style="font-size: 20px;">🏪</span> Cửa hàng</div>
                    <div class="legend-item"><span style="font-size: 20px;">🏠</span> Địa chỉ giao</div>
                    <div class="legend-item"><span style="font-size: 20px;">🏍️</span> Vị trí của bạn</div>
                </div>
            </div>
            
            <!-- Info panel -->
            <div class="info-panel">
                <div class="panel-header">
                    <h2>Đơn hàng #<?= $orderId ?></h2>
                    <div><?= $statusLabels[$order['status']] ?? $order['status'] ?></div>
                </div>
                
                <div class="distance-card">
                    <div class="value"><?= number_format($distance, 1) ?> km</div>
                    <div class="label">Khoảng cách giao hàng</div>
                    <div class="label">⏱️ <?= $deliveryTime['min'] ?>-<?= $deliveryTime['max'] ?> phút</div>
                </div>
                
                <div class="info-section">
                    <h3>🏪 Lấy hàng tại</h3>
                    <p class="highlight"><?= htmlspecialchars($order['shop_name']) ?></p>
                    <p>📍 <?= htmlspecialchars($order['shop_address']) ?></p>
                    <p>📞 <?= htmlspecialchars($order['shop_phone']) ?></p>
                </div>
                
                <div class="info-section">
                    <h3>🏠 Giao đến</h3>
                    <p class="highlight"><?= htmlspecialchars($order['customer_name']) ?></p>
                    <p>📍 <?= htmlspecialchars($order['delivery_address']) ?></p>
                    <p>📞 <?= htmlspecialchars($order['delivery_phone']) ?></p>
                </div>
                
                <div class="info-section">
                    <h3>💰 Thu nhập</h3>
                    <p class="highlight" style="font-size: 20px;"><?= number_format($order['shipping_fee']) ?>đ</p>
                </div>
                
                <a href="tel:<?= $order['delivery_phone'] ?>" class="btn-action btn-call">📞 Gọi khách hàng</a>
                <a href="my_orders.php" class="btn-action btn-back">← Quay lại</a>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // Tọa độ
    const shopLat = <?= $shopLat ?>;
    const shopLng = <?= $shopLng ?>;
    const deliveryLat = <?= $deliveryLat ?>;
    const deliveryLng = <?= $deliveryLng ?>;
    const shipperLat = <?= $shipperInfo['current_lat'] ?? 'null' ?>;
    const shipperLng = <?= $shipperInfo['current_lng'] ?? 'null' ?>;
    
    // Khởi tạo bản đồ
    const map = L.map('map').setView([shopLat, shopLng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    // Icons
    const shopIcon = L.divIcon({ html: '<div style="font-size: 35px;">🏪</div>', iconSize: [35, 35], className: '' });
    const customerIcon = L.divIcon({ html: '<div style="font-size: 35px;">🏠</div>', iconSize: [35, 35], className: '' });
    const shipperIcon = L.divIcon({ html: '<div style="font-size: 35px;">🏍️</div>', iconSize: [35, 35], className: '' });
    
    // Markers
    L.marker([shopLat, shopLng], {icon: shopIcon}).addTo(map)
        .bindPopup('<b>🏪 <?= addslashes($order['shop_name']) ?></b><br><?= addslashes($order['shop_address']) ?>');
    
    L.marker([deliveryLat, deliveryLng], {icon: customerIcon}).addTo(map)
        .bindPopup('<b>🏠 <?= addslashes($order['customer_name']) ?></b><br><?= addslashes($order['delivery_address']) ?>');
    
    // Shipper marker
    let shipperMarker = null;
    if (shipperLat && shipperLng) {
        shipperMarker = L.marker([shipperLat, shipperLng], {icon: shipperIcon}).addTo(map);
    }
    
    // Vẽ đường đi
    L.polyline([[shopLat, shopLng], [deliveryLat, deliveryLng]], {
        color: '#ff6b35', weight: 4, opacity: 0.8, dashArray: '10, 10'
    }).addTo(map);
    
    // Fit bounds
    map.fitBounds([[shopLat, shopLng], [deliveryLat, deliveryLng]], {padding: [50, 50]});
    
    // Cập nhật vị trí shipper
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            
            if (shipperMarker) {
                shipperMarker.setLatLng([lat, lng]);
            } else {
                shipperMarker = L.marker([lat, lng], {icon: shipperIcon}).addTo(map);
            }
            
            fetch('../api/shipper_location.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `lat=${lat}&lng=${lng}`
            });
        });
    }
    </script>
</body>
</html>
