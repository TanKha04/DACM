<?php
/**
 * Seller - Doanh thu
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

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
    case 'year':
        $startDate = date('Y-01-01');
        break;
}

// Tổng doanh thu
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COALESCE(SUM(commission_fee), 0) as total_commission
    FROM orders WHERE shop_id = ? AND status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$shop['id'], $startDate, $endDate]);
$stats = $stmt->fetch();

$netRevenue = $stats['total_revenue'] - $stats['total_commission'];

// Doanh thu theo ngày
$stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue 
    FROM orders WHERE shop_id = ? AND status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY date DESC");
$stmt->execute([$shop['id'], $startDate, $endDate]);
$dailyRevenue = $stmt->fetchAll();

// Đơn hàng đã giao
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.customer_id = u.id 
    WHERE o.shop_id = ? AND o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
    ORDER BY o.created_at DESC LIMIT 20");
$stmt->execute([$shop['id'], $startDate, $endDate]);
$deliveredOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doanh thu - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        .period-tabs { display: flex; gap: 10px; margin-bottom: 25px; }
        .period-tab { padding: 10px 20px; background: white; border-radius: 25px; text-decoration: none; color: #666; }
        .period-tab.active { background: #27ae60; color: white; }
        .revenue-card { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 30px; border-radius: 15px; margin-bottom: 25px; }
        .revenue-card h2 { font-size: 36px; margin-bottom: 10px; }
        .revenue-details { display: flex; gap: 30px; margin-top: 20px; }
        .revenue-item { }
        .revenue-item .label { opacity: 0.8; font-size: 14px; }
        .revenue-item .value { font-size: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>💰 Doanh thu</h1>
        </div>
        
        <div class="period-tabs">
            <a href="?period=today" class="period-tab <?= $period === 'today' ? 'active' : '' ?>">Hôm nay</a>
            <a href="?period=week" class="period-tab <?= $period === 'week' ? 'active' : '' ?>">7 ngày</a>
            <a href="?period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">Tháng này</a>
            <a href="?period=year" class="period-tab <?= $period === 'year' ? 'active' : '' ?>">Năm nay</a>
        </div>
        
        <div class="revenue-card">
            <p>Doanh thu thực nhận</p>
            <h2><?= number_format($netRevenue) ?>đ</h2>
            <div class="revenue-details">
                <div class="revenue-item">
                    <div class="label">Tổng doanh thu</div>
                    <div class="value"><?= number_format($stats['total_revenue']) ?>đ</div>
                </div>
                <div class="revenue-item">
                    <div class="label">Phí hoa hồng (<?= $shop['commission_rate'] ?>%)</div>
                    <div class="value">-<?= number_format($stats['total_commission']) ?>đ</div>
                </div>
                <div class="revenue-item">
                    <div class="label">Số đơn hoàn thành</div>
                    <div class="value"><?= $stats['total_orders'] ?></div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card">
                <h2 style="margin-bottom: 20px;">📊 Doanh thu theo ngày</h2>
                <?php if (empty($dailyRevenue)): ?>
                <p style="color: #999; text-align: center; padding: 30px;">Chưa có dữ liệu</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Số đơn</th>
                            <th>Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyRevenue as $day): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($day['date'])) ?></td>
                            <td><?= $day['orders'] ?></td>
                            <td><strong><?= number_format($day['revenue']) ?>đ</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2 style="margin-bottom: 20px;">📦 Đơn hàng đã giao</h2>
                <?php if (empty($deliveredOrders)): ?>
                <p style="color: #999; text-align: center; padding: 30px;">Chưa có đơn hàng</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Số tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveredOrders as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= number_format($order['total_amount']) ?>đ</td>
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
