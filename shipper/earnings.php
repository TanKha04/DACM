<?php
/**
 * Shipper - Thu nhập
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy shipper info
$stmt = $pdo->prepare("SELECT * FROM shipper_info WHERE user_id = ?");
$stmt->execute([$userId]);
$shipperInfo = $stmt->fetch();

// Lọc theo thời gian
$period = $_GET['period'] ?? 'today';
$startDate = date('Y-m-d');
$endDate = date('Y-m-d');

switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        break;
}

// Thống kê
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(shipping_fee), 0) as total_earnings 
    FROM orders WHERE shipper_id = ? AND status = 'delivered' AND DATE(updated_at) BETWEEN ? AND ?");
$stmt->execute([$userId, $startDate, $endDate]);
$stats = $stmt->fetch();

// Thu nhập theo ngày
$stmt = $pdo->prepare("SELECT DATE(updated_at) as date, COUNT(*) as orders, SUM(shipping_fee) as earnings 
    FROM orders WHERE shipper_id = ? AND status = 'delivered' AND DATE(updated_at) BETWEEN ? AND ?
    GROUP BY DATE(updated_at) ORDER BY date DESC");
$stmt->execute([$userId, $startDate, $endDate]);
$dailyEarnings = $stmt->fetchAll();

// Lịch sử giao hàng
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name FROM orders o JOIN shops s ON o.shop_id = s.id 
    WHERE o.shipper_id = ? AND o.status = 'delivered' 
    ORDER BY o.updated_at DESC LIMIT 20");
$stmt->execute([$userId]);
$deliveryHistory = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thu nhập - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .period-tabs { display: flex; gap: 10px; margin-bottom: 25px; }
        .period-tab { padding: 10px 20px; background: white; border-radius: 25px; text-decoration: none; color: #666; border: 2px solid #fecaca; }
        .period-tab.active { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-color: #dc2626; }
        .period-tab:hover { border-color: #fbbf24; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>💵 Thu nhập</h1>
        </div>
        
        <div class="period-tabs">
            <a href="?period=today" class="period-tab <?= $period === 'today' ? 'active' : '' ?>">Hôm nay</a>
            <a href="?period=week" class="period-tab <?= $period === 'week' ? 'active' : '' ?>">7 ngày</a>
            <a href="?period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">Tháng này</a>
        </div>
        
        <div class="earnings-card">
            <p>Tổng thu nhập</p>
            <h2><?= number_format($stats['total_earnings']) ?>đ</h2>
            <div style="display: flex; gap: 30px; margin-top: 20px;">
                <div>
                    <div style="opacity: 0.8; font-size: 14px;">Số đơn đã giao</div>
                    <div style="font-size: 20px; font-weight: bold;"><?= $stats['total_orders'] ?></div>
                </div>
                <div>
                    <div style="opacity: 0.8; font-size: 14px;">Tổng đơn (tất cả)</div>
                    <div style="font-size: 20px; font-weight: bold;"><?= $shipperInfo['total_deliveries'] ?? 0 ?></div>
                </div>
                <div>
                    <div style="opacity: 0.8; font-size: 14px;">Tổng thu nhập (tất cả)</div>
                    <div style="font-size: 20px; font-weight: bold;"><?= number_format($shipperInfo['total_earnings'] ?? 0) ?>đ</div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card">
                <h2>📊 Thu nhập theo ngày</h2>
                <?php if (empty($dailyEarnings)): ?>
                <p style="color: #999; text-align: center; padding: 30px;">Chưa có dữ liệu</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Số đơn</th>
                            <th>Thu nhập</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyEarnings as $day): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($day['date'])) ?></td>
                            <td><?= $day['orders'] ?></td>
                            <td><strong style="color: #dc2626;"><?= number_format($day['earnings']) ?>đ</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>📜 Lịch sử giao hàng</h2>
                <?php if (empty($deliveryHistory)): ?>
                <p style="color: #999; text-align: center; padding: 30px;">Chưa có lịch sử</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cửa hàng</th>
                            <th>Tiền ship</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveryHistory as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['shop_name']) ?></td>
                            <td><?= number_format($order['shipping_fee']) ?>đ</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
