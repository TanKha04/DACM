<?php
/**
 * Đơn của tôi - Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy các đơn hàng của shipper
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, u.name as customer_name 
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    JOIN users u ON o.customer_id = u.id
    WHERE o.shipper_id = ? ORDER BY o.created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$statusLabels = [
    'confirmed' => ['label' => 'Chờ chuẩn bị', 'color' => '#3498db', 'desc' => 'Chờ người bán chuẩn bị hàng'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'color' => '#f39c12', 'desc' => 'Người bán đang chuẩn bị'],
    'ready' => ['label' => 'Sẵn sàng', 'color' => '#27ae60', 'desc' => 'Hàng đã sẵn sàng, đến lấy ngay!'],
    'picked' => ['label' => 'Đã lấy hàng', 'color' => '#9b59b6', 'desc' => 'Đang trên đường giao'],
    'delivering' => ['label' => 'Đang giao', 'color' => '#e67e22', 'desc' => 'Đang giao cho khách'],
    'delivered' => ['label' => 'Đã giao', 'color' => '#2ecc71', 'desc' => 'Hoàn thành'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => '#e74c3c', 'desc' => 'Đơn bị hủy']
];

$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn của tôi - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .order-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .order-id { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .order-status { padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .order-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .info-block { padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-label { font-size: 12px; color: #7f8c8d; margin-bottom: 5px; }
        .info-value { font-weight: 600; color: #2c3e50; }
        .info-sub { font-size: 13px; color: #95a5a6; margin-top: 3px; }
        .order-actions { display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .status-desc { font-size: 13px; color: #7f8c8d; margin-top: 5px; }
        .waiting-badge { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>🚚 Đơn của tôi</h1>
        </div>
        
        <?php if (empty($orders)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">📦</p>
            <h2>Chưa có đơn nào</h2>
            <p style="color: #7f8c8d;">Vào mục "Đơn có sẵn" để nhận đơn mới</p>
            <a href="available.php" class="btn btn-primary" style="margin-top: 20px;">📦 Xem đơn có sẵn</a>
        </div>
        <?php else: ?>
        
        <?php foreach ($orders as $order): 
            $status = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'color' => '#95a5a6', 'desc' => ''];
            $isWaiting = in_array($order['status'], ['confirmed', 'preparing']);
            $isReady = $order['status'] === 'ready';
            $isActive = in_array($order['status'], ['picked', 'delivering']);
        ?>
        <div class="order-card" style="<?= $isReady ? 'border: 2px solid #27ae60;' : '' ?>">
            <div class="order-header">
                <div>
                    <span class="order-id">#<?= $order['id'] ?></span>
                    <div class="status-desc"><?= $status['desc'] ?></div>
                </div>
                <span class="order-status <?= $isWaiting ? 'waiting-badge' : '' ?>" style="background: <?= $status['color'] ?>20; color: <?= $status['color'] ?>;">
                    <?= $status['label'] ?>
                </span>
            </div>
            
            <div class="order-info">
                <div class="info-block">
                    <div class="info-label">🏪 Lấy hàng tại</div>
                    <div class="info-value"><?= htmlspecialchars($order['shop_name']) ?></div>
                    <div class="info-sub"><?= htmlspecialchars($order['shop_address']) ?></div>
                    <div class="info-sub">📞 <?= $order['shop_phone'] ?></div>
                </div>
                <div class="info-block">
                    <div class="info-label">📍 Giao đến</div>
                    <div class="info-value"><?= htmlspecialchars($order['delivery_name']) ?></div>
                    <div class="info-sub"><?= htmlspecialchars($order['delivery_address']) ?></div>
                    <div class="info-sub">📞 <?= $order['delivery_phone'] ?></div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="color: #7f8c8d;">💵 Tiền ship:</span>
                    <strong style="color: #3498db; font-size: 18px;"><?= number_format($order['shipping_fee']) ?>đ</strong>
                </div>
                <div style="font-size: 13px; color: #95a5a6;">
                    <?= date('H:i d/m/Y', strtotime($order['created_at'])) ?>
                </div>
            </div>
            
            <?php if ($isReady): ?>
            <div class="order-actions">
                <a href="order_map.php?id=<?= $order['id'] ?>" class="btn btn-info" style="background: #17a2b8; color: white;">🗺️ Xem bản đồ</a>
                <form method="POST" action="update_status.php" style="flex: 1;">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="status" value="picked">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">📦 Đã lấy hàng</button>
                </form>
                <a href="tel:<?= $order['shop_phone'] ?>" class="btn btn-secondary">📞 Gọi shop</a>
            </div>
            <?php elseif ($isActive): ?>
            <div class="order-actions">
                <a href="order_map.php?id=<?= $order['id'] ?>" class="btn btn-info" style="background: #17a2b8; color: white;">🗺️ Bản đồ</a>
                <?php if ($order['status'] === 'picked'): ?>
                <form method="POST" action="update_status.php" style="flex: 1;">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="status" value="delivering">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🚀 Bắt đầu giao</button>
                </form>
                <?php else: ?>
                <form method="POST" action="update_status.php" style="flex: 1;">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="status" value="delivered">
                    <button type="submit" class="btn btn-success" style="width: 100%;">✅ Đã giao xong</button>
                </form>
                <?php endif; ?>
                <a href="tel:<?= $order['delivery_phone'] ?>" class="btn btn-secondary">📞 Gọi khách</a>
                <a href="chat_customer.php?order_id=<?= $order['id'] ?>" class="btn btn-info" style="background: #3498db; color: white;">💬 Nhắn tin</a>
            </div>
            <?php elseif ($isWaiting): ?>
            <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 10px; color: #856404; text-align: center;">
                ⏳ Đang chờ người bán chuẩn bị hàng...
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Auto refresh mỗi 30 giây -->
    <script>
    setTimeout(function() { location.reload(); }, 30000);
    
    // ===== CẬP NHẬT VỊ TRÍ SHIPPER REALTIME =====
    <?php 
    $hasActiveOrder = false;
    foreach ($orders as $o) {
        if (in_array($o['status'], ['ready', 'picked', 'delivering'])) {
            $hasActiveOrder = true;
            break;
        }
    }
    if ($hasActiveOrder): 
    ?>
    function updateShipperLocation(lat, lng) {
        fetch('../api/shipper_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('📍 Đã cập nhật vị trí:', lat.toFixed(6), lng.toFixed(6));
            }
        })
        .catch(err => console.log('Lỗi cập nhật vị trí:', err));
    }
    
    // Theo dõi vị trí liên tục
    if (navigator.geolocation) {
        // Lấy vị trí ngay lập tức
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                updateShipperLocation(pos.coords.latitude, pos.coords.longitude);
            },
            function(err) {
                console.log('Không lấy được vị trí:', err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
        
        // Theo dõi liên tục
        navigator.geolocation.watchPosition(
            function(pos) {
                updateShipperLocation(pos.coords.latitude, pos.coords.longitude);
            },
            function(err) {},
            { enableHighAccuracy: true, maximumAge: 5000 }
        );
        
        // Backup: cập nhật mỗi 10 giây
        setInterval(() => {
            navigator.geolocation.getCurrentPosition(
                pos => updateShipperLocation(pos.coords.latitude, pos.coords.longitude),
                err => {}
            );
        }, 10000);
    }
    <?php endif; ?>
    </script>
</body>
</html>
