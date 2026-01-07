<?php
/**
 * Theo dõi trạng thái đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone,
           u.name as shipper_name, u.phone as shipper_phone
    FROM orders o
    JOIN shops s ON o.shop_id = s.id
    LEFT JOIN users u ON o.shipper_id = u.id
    WHERE o.id = ? AND o.customer_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$statusLabels = [
    'pending' => ['label' => 'Chờ xác nhận', 'icon' => '⏳', 'color' => '#f39c12'],
    'confirmed' => ['label' => 'Đã xác nhận', 'icon' => '✅', 'color' => '#3498db'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'icon' => '👨‍🍳', 'color' => '#9b59b6'],
    'ready' => ['label' => 'Sẵn sàng giao', 'icon' => '📦', 'color' => '#1abc9c'],
    'picked' => ['label' => 'Shipper đã lấy hàng', 'icon' => '🏃', 'color' => '#e67e22'],
    'delivering' => ['label' => 'Đang giao hàng', 'icon' => '🚀', 'color' => '#e74c3c'],
    'delivered' => ['label' => 'Đã giao hàng', 'icon' => '🎉', 'color' => '#27ae60'],
    'cancelled' => ['label' => 'Đã hủy', 'icon' => '❌', 'color' => '#95a5a6']
];

$currentStatus = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'icon' => '📋', 'color' => '#666'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theo dõi đơn hàng #<?= $orderId ?> - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .track-page { max-width: 700px; margin: 0 auto; padding: 15px; }
        .track-header { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .order-status { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .status-icon { font-size: 40px; }
        .status-info h2 { margin: 0 0 5px; color: #333; }
        .status-badge { display: inline-block; padding: 6px 15px; border-radius: 20px; color: white; font-weight: 600; }
        
        .info-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .info-card { background: white; border-radius: 12px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .info-card h3 { margin: 0 0 10px; font-size: 14px; color: #666; }
        .info-card p { margin: 5px 0; color: #333; }
        .info-card .highlight { font-size: 18px; font-weight: 600; color: #ff6b35; }
        
        .timeline { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .timeline-item { display: flex; gap: 15px; padding: 15px 0; border-left: 3px solid #eee; margin-left: 15px; padding-left: 20px; position: relative; }
        .timeline-item::before { content: ''; position: absolute; left: -8px; top: 20px; width: 12px; height: 12px; border-radius: 50%; background: #ddd; }
        .timeline-item.active { border-left-color: #27ae60; }
        .timeline-item.active::before { background: #27ae60; }
        .timeline-item.current::before { background: #ff6b35; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }
        
        .shipper-card { background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; }
        .shipper-info { display: flex; align-items: center; gap: 15px; }
        .shipper-avatar { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        .shipper-details h3 { margin: 0 0 5px; }
        .shipper-details p { margin: 0; opacity: 0.9; }
        .call-btn { display: inline-flex; align-items: center; gap: 8px; background: white; color: #ff6b35; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="track-page">
        <div class="track-header">
            <div class="order-status">
                <span class="status-icon"><?= $currentStatus['icon'] ?></span>
                <div class="status-info">
                    <h2>Đơn hàng #<?= $orderId ?></h2>
                    <span class="status-badge" style="background: <?= $currentStatus['color'] ?>"><?= $currentStatus['label'] ?></span>
                </div>
            </div>
            <p>📍 Giao đến: <?= htmlspecialchars($order['delivery_address']) ?></p>
        </div>
        
        <?php if ($order['shipper_id']): ?>
        <div class="shipper-card">
            <div class="shipper-info">
                <div class="shipper-avatar">🏍️</div>
                <div class="shipper-details">
                    <h3><?= htmlspecialchars($order['shipper_name']) ?></h3>
                    <?php if ($order['status'] === 'ready'): ?>
                    <p>Shipper đang đến lấy hàng</p>
                    <?php elseif ($order['status'] === 'picked'): ?>
                    <p>Đã lấy hàng, đang trên đường giao</p>
                    <?php elseif ($order['status'] === 'delivering'): ?>
                    <p>Đang giao hàng cho bạn</p>
                    <?php else: ?>
                    <p>Shipper phụ trách đơn hàng</p>
                    <?php endif; ?>
                    <a href="tel:<?= $order['shipper_phone'] ?>" class="call-btn">📞 Gọi shipper</a>
                </div>
            </div>
        </div>
        <?php elseif (in_array($order['status'], ['ready'])): ?>
        <div class="shipper-card" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d);">
            <div class="shipper-info">
                <div class="shipper-avatar">⏳</div>
                <div class="shipper-details">
                    <h3>Đang tìm shipper</h3>
                    <p>Hệ thống đang tìm shipper gần nhất...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-cards">
            <div class="info-card">
                <h3>🏪 Cửa hàng</h3>
                <p class="highlight"><?= htmlspecialchars($order['shop_name']) ?></p>
                <p><?= htmlspecialchars($order['shop_address']) ?></p>
                <p>📞 <?= htmlspecialchars($order['shop_phone']) ?></p>
            </div>
            <div class="info-card">
                <h3>💰 Tổng tiền</h3>
                <p class="highlight"><?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ</p>
                <p>Phí ship: <?= number_format($order['shipping_fee']) ?>đ</p>
            </div>
        </div>
        
        <div class="timeline">
            <h3 style="margin: 0 0 15px;">📋 Trạng thái đơn hàng</h3>
            <?php 
            $steps = ['pending', 'confirmed', 'preparing', 'ready', 'picked', 'delivering', 'delivered'];
            $currentIndex = array_search($order['status'], $steps);
            foreach ($steps as $i => $step): 
                $stepInfo = $statusLabels[$step];
                $isActive = $i <= $currentIndex;
                $isCurrent = $step === $order['status'];
            ?>
            <div class="timeline-item <?= $isActive ? 'active' : '' ?> <?= $isCurrent ? 'current' : '' ?>">
                <div>
                    <strong><?= $stepInfo['icon'] ?> <?= $stepInfo['label'] ?></strong>
                    <?php if ($isCurrent): ?>
                    <p style="color: #666; font-size: 13px; margin: 5px 0 0;">Đang xử lý...</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="order_detail.php?id=<?= $orderId ?>" style="color: #ff6b35;">← Xem chi tiết đơn hàng</a>
        </div>
    </div>
    
    <script>
    // Cập nhật trạng thái đơn hàng mỗi 10 giây
    setInterval(() => {
        fetch('order_status.php?id=<?= $orderId ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status && data.status !== '<?= $order['status'] ?>') {
                    location.reload();
                }
            });
    }, 10000);
    </script>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
