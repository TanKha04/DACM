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
        /* Hiệu ứng Tết */
        @keyframes fall { 0% { transform: translateY(-10vh) rotate(0deg); } 100% { transform: translateY(100vh) rotate(360deg); } }
        @keyframes sway { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(20px); } }
        @keyframes swing { 0%, 100% { transform: rotate(-10deg); } 50% { transform: rotate(10deg); } }
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
        .welcome-banner > div:first-child {
            flex: 1;
            min-width: 0;
        }
        .welcome-banner::before {
            content: '🏮';
            position: absolute;
            top: 10px;
            left: 15px;
            font-size: 25px;
            animation: swing 2s ease-in-out infinite;
        }
        .welcome-banner::after {
            content: '🏮';
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 25px;
            animation: swing 2s ease-in-out infinite reverse;
        }
        .welcome-banner h2 {
            font-size: 22px;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            white-space: nowrap;
        }
        .welcome-logo {
            flex-shrink: 0;
            text-align: center;
            margin: 0 20px;
        }
        .welcome-actions {
            flex-shrink: 0;
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
        .welcome-logo img {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 3px solid #fbbf24;
        }
        .welcome-actions {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
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
            box-shadow: 0 4px 15px rgba(220,38,38,0.1);
            transition: transform 0.3s;
            text-decoration: none;
            color: inherit;
            border: 2px solid #fecaca;
        }
        .action-card:hover {
            transform: translateY(-5px);
            border-color: #fbbf24;
            box-shadow: 0 10px 30px rgba(220,38,38,0.2);
        }
        .action-image {
            height: 120px;
            position: relative;
            overflow: hidden;
        }
        .action-image.users { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .action-image.shops { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .action-image.orders { background: linear-gradient(135deg, #b91c1c, #dc2626); }
        .action-image.finance { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
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
            color: #991b1b;
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
            box-shadow: 0 4px 15px rgba(220,38,38,0.1);
            border: 2px solid #fecaca;
        }
        .stat-box .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #dc2626;
        }
        .stat-box .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
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
            box-shadow: 0 4px 15px rgba(220,38,38,0.1);
            border: 2px solid #fecaca;
        }
        .today-box .label {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .today-box .value {
            font-size: 36px;
            font-weight: 700;
            color: #dc2626;
        }
        
        .data-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        
        .card {
            border: 2px solid #fecaca !important;
        }
    </style>
</head>
<body>
    <!-- Hoa mai rơi -->
    <div class="tet-flowers" id="tetFlowers"></div>
    
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content" style="background: #fff5f5;">
        <!-- Banner Tết -->
        <div class="tet-banner">
            <span>🧧</span>
            <span>🌸 Chúc Mừng Năm Mới 2026 - An Khang Thịnh Vượng 🌸</span>
            <span>🧧</span>
        </div>
        
        <div class="page-header">
            <h1>🏮 Trang chủ</h1>
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
            <div class="stat-box">
                <div class="icon">👥</div>
                <div class="value"><?= number_format($totalUsers) ?></div>
                <div class="label">Tổng người dùng</div>
            </div>
            <div class="stat-box">
                <div class="icon">📦</div>
                <div class="value"><?= number_format($totalOrders) ?></div>
                <div class="label">Tổng đơn hàng</div>
            </div>
            <div class="stat-box">
                <div class="icon">💰</div>
                <div class="value"><?= number_format($totalRevenue) ?>đ</div>
                <div class="label">Tổng doanh thu</div>
            </div>
            <div class="stat-box">
                <div class="icon">🏪</div>
                <div class="value"><?= $pendingShops ?></div>
                <div class="label">Shop chờ duyệt</div>
            </div>
        </div>
        
        <!-- Today Stats -->
        <div class="today-stats">
            <div class="today-box">
                <div class="label">Đơn hàng hôm nay</div>
                <div class="value"><?= $todayStats['orders'] ?></div>
            </div>
            <div class="today-box">
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
        
        for (let i = 0; i < 15; i++) setTimeout(createFlower, i * 300);
        setInterval(createFlower, 800);
    })();
    </script>
</body>
</html>
