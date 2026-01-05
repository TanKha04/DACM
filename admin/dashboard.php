<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();

// Thống kê tổng quan
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$totalOrders = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status = 'delivered'");
$totalRevenue = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM shops WHERE status = 'pending'");
$pendingShops = $stmt->fetch()['total'];

// Thống kê hôm nay
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// Đơn hàng mới nhất
$stmt = $pdo->query("SELECT o.*, u.name as customer_name, s.name as shop_name FROM orders o 
    JOIN users u ON o.customer_id = u.id JOIN shops s ON o.shop_id = s.id 
    ORDER BY o.created_at DESC LIMIT 5");
$recentOrders = $stmt->fetchAll();

// Users mới
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll();

// Lời chào theo thời gian
$hour = date('H');
if ($hour < 12) $greeting = 'Chào buổi sáng';
elseif ($hour < 18) $greeting = 'Chào buổi chiều';
else $greeting = 'Chào buổi tối';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FastFood</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.3) 100%), url('https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=1200&h=400&fit=crop');
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
        }
        .welcome-banner h2 {
            font-size: 28px;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 15px;
        }
        .welcome-badges {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .welcome-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .welcome-text {
            opacity: 0.9;
            font-size: 14px;
        }
        .welcome-logo {
            text-align: center;
        }
        .welcome-logo img {
            width: 180px;
            height: 180px;
            border-radius: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .welcome-actions {
            display: flex;
            gap: 12px;
        }
        .welcome-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .welcome-btn:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            text-decoration: none;
            color: inherit;
        }
        .action-card:hover {
            transform: translateY(-5px);
        }
        .action-image {
            height: 120px;
            position: relative;
            overflow: hidden;
        }
        .action-image.users { background: linear-gradient(135deg, #ff6b35, #ff8c42); }
        .action-image.shops { background: linear-gradient(135deg, #f7931e, #ffb347); }
        .action-image.orders { background: linear-gradient(135deg, #ff4d4d, #ff6b6b); }
        .action-image.finance { background: linear-gradient(135deg, #ff9a56, #ffbe76); }
        .action-image img {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 80px;
            height: 80px;
            object-fit: contain;
            opacity: 0.9;
        }
        .action-icon {
            position: absolute;
            bottom: 15px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .action-content {
            padding: 20px;
        }
        .action-content h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .action-content p {
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.5;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .stat-box .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-box .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .stat-box.blue .value { color: #3498db; }
        .stat-box.orange .value { color: #f39c12; }
        .stat-box.green .value { color: #27ae60; }
        .stat-box.red .value { color: #e74c3c; }
        
        .today-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .today-box {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .today-box .label {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .today-box .value {
            font-size: 36px;
            font-weight: 700;
        }
        .today-box.orders .value { color: #3498db; }
        .today-box.revenue .value { color: #27ae60; }
        
        .data-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🏠 Trang chủ</h1>
            <span style="color: #7f8c8d; font-size: 15px;"><?= date('d/m/Y H:i') ?></span>
        </div>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div>
                <h2><?= $greeting ?>, Quản trị viên <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <div class="welcome-badges">
                    <span class="welcome-badge">👑 Quản trị viên</span>
                    <span class="welcome-badge">✅ Đã xác minh</span>
                </div>
                <p class="welcome-text">Chọn một chức năng từ menu bên trái để bắt đầu.</p>
            </div>
            <div class="welcome-logo">
                <img src="../logo.png" alt="Logo">
            </div>
            <div class="welcome-actions">
                <a href="shops.php?status=pending" class="welcome-btn">🏪 Duyệt Shop</a>
                <a href="orders.php" class="welcome-btn">📦 Đơn hàng</a>
                <a href="settings.php" class="welcome-btn">⚙️ Cài đặt</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users.php" class="action-card">
                <div class="action-image users">
                    <div class="action-icon">👥</div>
                    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="">
                </div>
                <div class="action-content">
                    <h3>Quản lý người dùng</h3>
                    <p>Xem, chỉnh sửa và quản lý tài khoản người dùng</p>
                </div>
            </a>
            <a href="shops.php" class="action-card">
                <div class="action-image shops">
                    <div class="action-icon">🏪</div>
                    <img src="https://cdn-icons-png.flaticon.com/512/869/869636.png" alt="">
                </div>
                <div class="action-content">
                    <h3>Quản lý cửa hàng</h3>
                    <p>Duyệt và quản lý các cửa hàng đăng ký</p>
                </div>
            </a>
            <a href="orders.php" class="action-card">
                <div class="action-image orders">
                    <div class="action-icon">📦</div>
                    <img src="https://cdn-icons-png.flaticon.com/512/3081/3081559.png" alt="">
                </div>
                <div class="action-content">
                    <h3>Quản lý đơn hàng</h3>
                    <p>Theo dõi và xử lý các đơn hàng</p>
                </div>
            </a>
            <a href="finance.php" class="action-card">
                <div class="action-image finance">
                    <div class="action-icon">💰</div>
                    <img src="https://cdn-icons-png.flaticon.com/512/2150/2150150.png" alt="">
                </div>
                <div class="action-content">
                    <h3>Báo cáo tài chính</h3>
                    <p>Xem doanh thu và thống kê tài chính</p>
                </div>
            </a>
        </div>
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box blue">
                <div class="icon">👥</div>
                <div class="value"><?= number_format($totalUsers) ?></div>
                <div class="label">Tổng người dùng</div>
            </div>
            <div class="stat-box orange">
                <div class="icon">📦</div>
                <div class="value"><?= number_format($totalOrders) ?></div>
                <div class="label">Tổng đơn hàng</div>
            </div>
            <div class="stat-box green">
                <div class="icon">💰</div>
                <div class="value"><?= number_format($totalRevenue) ?>đ</div>
                <div class="label">Tổng doanh thu</div>
            </div>
            <div class="stat-box red">
                <div class="icon">🏪</div>
                <div class="value"><?= $pendingShops ?></div>
                <div class="label">Shop chờ duyệt</div>
            </div>
        </div>
        
        <!-- Today Stats -->
        <div class="today-stats">
            <div class="today-box orders">
                <div class="label">Đơn hàng hôm nay</div>
                <div class="value"><?= $todayStats['orders'] ?></div>
            </div>
            <div class="today-box revenue">
                <div class="label">Doanh thu hôm nay</div>
                <div class="value"><?= number_format($todayStats['revenue']) ?>đ</div>
            </div>
        </div>
        
        <!-- Data Section -->
        <div class="data-section">
            <div class="card">
                <div class="card-header">
                    <h2>📦 Đơn hàng mới nhất</h2>
                    <a href="orders.php" class="btn btn-primary btn-sm">Xem tất cả</a>
                </div>
                <?php if (empty($recentOrders)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 30px;">Chưa có đơn hàng nào</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Cửa hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['shop_name']) ?></td>
                            <td><?= number_format($order['total_amount']) ?>đ</td>
                            <td><span class="badge badge-<?= $order['status'] === 'delivered' ? 'active' : 'pending' ?>"><?= ucfirst($order['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>👤 Users mới</h2>
                </div>
                <?php if (empty($recentUsers)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 30px;">Chưa có user nào</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tên</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><span class="badge badge-<?= $user['is_admin'] ? 'admin' : $user['role'] ?>"><?= $user['is_admin'] ? 'Admin' : ucfirst($user['role']) ?></span></td>
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
