<?php
/**
 * Admin - Quản lý tài chính
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();

// Lọc theo thời gian
$period = $_GET['period'] ?? 'month';
$startDate = date('Y-m-01');
$endDate = date('Y-m-d');

switch ($period) {
    case 'today':
        $startDate = date('Y-m-d');
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'year':
        $startDate = date('Y-01-01');
        break;
}

// Tổng doanh thu
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COALESCE(SUM(shipping_fee), 0) as total_shipping,
    COALESCE(SUM(commission_fee), 0) as total_commission
    FROM orders WHERE status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$stats = $stmt->fetch();

// Doanh thu theo ngày
$stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue, SUM(commission_fee) as commission
    FROM orders WHERE status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at) ORDER BY date DESC");
$stmt->execute([$startDate, $endDate]);
$dailyRevenue = $stmt->fetchAll();

// Top shops
$stmt = $pdo->prepare("SELECT s.name, COUNT(o.id) as orders, SUM(o.total_amount) as revenue
    FROM orders o JOIN shops s ON o.shop_id = s.id
    WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.shop_id ORDER BY revenue DESC LIMIT 10");
$stmt->execute([$startDate, $endDate]);
$topShops = $stmt->fetchAll();

// Top shippers
$stmt = $pdo->prepare("SELECT u.name, COUNT(o.id) as deliveries, SUM(o.shipping_fee) as earnings
    FROM orders o JOIN users u ON o.shipper_id = u.id
    WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.shipper_id ORDER BY deliveries DESC LIMIT 10");
$stmt->execute([$startDate, $endDate]);
$topShippers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài chính - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>💰 Quản lý tài chính</h1>
        </div>
        
        <div class="tabs">
            <a href="?period=today" class="tab <?= $period === 'today' ? 'active' : '' ?>">Hôm nay</a>
            <a href="?period=week" class="tab <?= $period === 'week' ? 'active' : '' ?>">7 ngày</a>
            <a href="?period=month" class="tab <?= $period === 'month' ? 'active' : '' ?>">Tháng này</a>
            <a href="?period=year" class="tab <?= $period === 'year' ? 'active' : '' ?>">Năm nay</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="icon">💰</div>
                <div class="value"><?= number_format($stats['total_revenue']) ?>đ</div>
                <div class="label">Tổng doanh thu</div>
            </div>
            <div class="stat-card blue">
                <div class="icon">🚚</div>
                <div class="value"><?= number_format($stats['total_shipping']) ?>đ</div>
                <div class="label">Phí ship</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">📊</div>
                <div class="value"><?= number_format($stats['total_commission']) ?>đ</div>
                <div class="label">Hoa hồng hệ thống</div>
            </div>
            <div class="stat-card">
                <div class="icon">📦</div>
                <div class="value"><?= $stats['total_orders'] ?></div>
                <div class="label">Đơn hoàn thành</div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card">
                <h2 style="margin-bottom: 20px;">📊 Doanh thu theo ngày</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Đơn</th>
                            <th>Doanh thu</th>
                            <th>Hoa hồng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyRevenue as $day): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($day['date'])) ?></td>
                            <td><?= $day['orders'] ?></td>
                            <td><?= number_format($day['revenue']) ?>đ</td>
                            <td style="color: #27ae60;"><?= number_format($day['commission']) ?>đ</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div>
                <div class="card">
                    <h2 style="margin-bottom: 20px;">🏪 Top cửa hàng</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Cửa hàng</th>
                                <th>Đơn</th>
                                <th>Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topShops as $shop): ?>
                            <tr>
                                <td><?= htmlspecialchars($shop['name']) ?></td>
                                <td><?= $shop['orders'] ?></td>
                                <td><?= number_format($shop['revenue']) ?>đ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card">
                    <h2 style="margin-bottom: 20px;">🛵 Top shipper</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Shipper</th>
                                <th>Đơn</th>
                                <th>Thu nhập</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topShippers as $shipper): ?>
                            <tr>
                                <td><?= htmlspecialchars($shipper['name']) ?></td>
                                <td><?= $shipper['deliveries'] ?></td>
                                <td><?= number_format($shipper['earnings']) ?>đ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
