<?php
/**
 * Seller Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

$isApproved = $shop && $shop['status'] === 'active';
$isPending = $shop && $shop['status'] === 'pending';
$hasNoShop = !$shop;

// Thống kê
$stats = ['orders_today' => 0, 'revenue_today' => 0, 'pending_orders' => 0, 'products' => 0];
$recentOrders = [];

if ($isApproved) {
    $today = date('Y-m-d');
    
    // Đơn hôm nay
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shop_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$shop['id'], $today]);
    $stats['orders_today'] = $stmt->fetch()['total'];
    
    // Doanh thu hôm nay
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE shop_id = ? AND DATE(created_at) = ? AND status = 'delivered'");
    $stmt->execute([$shop['id'], $today]);
    $stats['revenue_today'] = $stmt->fetch()['total'];
    
    // Đơn chờ xử lý
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shop_id = ? AND status IN ('pending', 'confirmed')");
    $stmt->execute([$shop['id']]);
    $stats['pending_orders'] = $stmt->fetch()['total'];
    
    // Tổng sản phẩm
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE shop_id = ? AND status = 'active'");
    $stmt->execute([$shop['id']]);
    $stats['products'] = $stmt->fetch()['total'];
    
    // Đơn hàng mới
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.shop_id = ? ORDER BY o.created_at DESC LIMIT 10");
    $stmt->execute([$shop['id']]);
    $recentOrders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - FastFood</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #newOrderAlert.show { animation: slideIn 0.5s ease, pulse 2s infinite; }
        .stat-card { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .card { animation: fadeInUp 0.5s ease 0.5s forwards; opacity: 0; }
        
        /* Status text mapping */
        .badge-pending::after { content: 'Chờ xác nhận'; }
        .badge-confirmed::after { content: 'Đã xác nhận'; }
        .badge-preparing::after { content: 'Đang chuẩn bị'; }
        .badge-ready::after { content: 'Sẵn sàng'; }
        .badge-shipping::after { content: 'Đang giao'; }
        .badge-delivered::after { content: 'Đã giao'; }
        .badge-cancelled::after { content: 'Đã hủy'; }
        .badge { font-size: 0; }
        .badge::after { font-size: 12px; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>👋 Xin chào, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
            <div class="header-actions">
                <span><?= date('d/m/Y') ?></span>
            </div>
        </div>
        
        <!-- Thông báo đơn hàng mới -->
        <div id="newOrderAlert" style="display: none; position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 20px 25px; border-radius: 15px; box-shadow: 0 10px 40px rgba(39,174,96,0.4); z-index: 9999; max-width: 350px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 40px;">🔔</div>
                <div>
                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">Đơn hàng mới!</div>
                    <div id="newOrderInfo" style="font-size: 14px; opacity: 0.9;"></div>
                </div>
            </div>
            <button onclick="closeNewOrderAlert()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
            <button onclick="viewOrder()" style="display: block; width: 100%; margin-top: 15px; background: white; color: #27ae60; padding: 10px 20px; border-radius: 8px; border: none; text-align: center; font-weight: bold; cursor: pointer;">Xem đơn hàng</button>
        </div>
        
        <?php if ($hasNoShop): ?>
        <div class="alert alert-warning">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <strong>Chưa có cửa hàng!</strong><br>
                Bạn cần <a href="register_shop.php" style="color: #ffc107; font-weight: bold; text-decoration: underline;">đăng ký mở cửa hàng</a> để có thể đăng sản phẩm và nhận đơn hàng.
            </div>
        </div>
        <?php elseif ($isPending): ?>
        <div class="alert alert-info">
            <span style="font-size: 24px;">⏳</span>
            <div>
                <strong>Đang chờ duyệt!</strong><br>
                Yêu cầu mở cửa hàng của bạn đang được Admin xem xét. Vui lòng chờ duyệt để có thể đăng sản phẩm.
            </div>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">🛒</div>
                <div class="value"><?= $stats['orders_today'] ?></div>
                <div class="label">Đơn hôm nay</div>
            </div>
            <div class="stat-card">
                <div class="icon">💰</div>
                <div class="value"><?= number_format($stats['revenue_today']) ?>đ</div>
                <div class="label">Doanh thu hôm nay</div>
            </div>
            <div class="stat-card">
                <div class="icon">📦</div>
                <div class="value"><?= $stats['pending_orders'] ?></div>
                <div class="label">Chờ xử lý</div>
            </div>
            <div class="stat-card">
                <div class="icon">🍔</div>
                <div class="value"><?= $stats['products'] ?></div>
                <div class="label">Sản phẩm</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>📋 Đơn hàng mới nhất</h2>
                <a href="orders.php" class="btn btn-primary btn-sm">Xem tất cả</a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thời gian</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="6" style="text-align: center; color: rgba(255,255,255,0.5); padding: 40px;">📭 Chưa có đơn hàng nào</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><strong style="color: #2ecc71;">#<?= $order['id'] ?></strong></td>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($order['customer_name']) ?></div>
                                <small style="color: rgba(255,255,255,0.5);"><?= $order['customer_phone'] ?></small>
                            </td>
                            <td style="font-weight: 600; color: #2ecc71;"><?= number_format($order['total_amount']) ?>đ</td>
                            <td><span class="badge badge-<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                            <td style="color: rgba(255,255,255,0.7);"><?= date('H:i d/m', strtotime($order['created_at'])) ?></td>
                            <td><a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary">Chi tiết</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($isApproved): ?>
    <script>
    // Kiểm tra đơn hàng mới mỗi 3 giây
    let lastOrderId = <?= !empty($recentOrders) ? $recentOrders[0]['id'] : 0 ?>;
    let soundInterval = null; // Interval để lặp âm thanh
    let soundTimeout = null;  // Timeout 5 phút
    
    // Tạo âm thanh thông báo - phát 1 lần
    function playBeepOnce() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            function playBeep(time, freq, duration) {
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                osc.connect(gain);
                gain.connect(audioContext.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.5, time);
                gain.gain.exponentialRampToValueAtTime(0.01, time + duration);
                osc.start(time);
                osc.stop(time + duration);
            }
            
            const now = audioContext.currentTime;
            playBeep(now, 800, 0.15);
            playBeep(now + 0.2, 1000, 0.15);
            playBeep(now + 0.5, 800, 0.15);
            playBeep(now + 0.7, 1200, 0.2);
            
        } catch (e) {
            console.log('Audio not supported');
        }
    }
    
    // Bắt đầu reo liên tục (mỗi 3 giây, tối đa 5 phút)
    function startContinuousSound() {
        // Dừng âm thanh cũ nếu có
        stopSound();
        
        // Phát ngay lần đầu
        playBeepOnce();
        
        // Lặp lại mỗi 3 giây
        soundInterval = setInterval(() => {
            playBeepOnce();
        }, 3000);
        
        // Tự động dừng sau 5 phút (300000ms)
        soundTimeout = setTimeout(() => {
            stopSound();
        }, 300000);
    }
    
    // Dừng âm thanh
    function stopSound() {
        if (soundInterval) {
            clearInterval(soundInterval);
            soundInterval = null;
        }
        if (soundTimeout) {
            clearTimeout(soundTimeout);
            soundTimeout = null;
        }
    }
    
    function checkNewOrders() {
        console.log('Checking new orders... lastOrderId:', lastOrderId);
        fetch('../api/check_new_orders.php?shop_id=<?= $shop['id'] ?>&last_id=' + lastOrderId)
            .then(response => response.json())
            .then(data => {
                console.log('API response:', data);
                if (data.hasNew && data.order) {
                    console.log('NEW ORDER FOUND!', data.order);
                    lastOrderId = data.order.id;
                    showNewOrderAlert(data.order);
                    startContinuousSound(); // Bắt đầu reo liên tục
                    
                    // Cập nhật số liệu
                    if (data.stats) {
                        document.querySelector('.stat-card:nth-child(1) .value').textContent = data.stats.orders_today;
                        document.querySelector('.stat-card:nth-child(3) .value').textContent = data.stats.pending_orders;
                    }
                }
            })
            .catch(err => console.log('Check orders error:', err));
    }
    
    function showNewOrderAlert(order) {
        const alert = document.getElementById('newOrderAlert');
        const info = document.getElementById('newOrderInfo');
        info.innerHTML = `Đơn #${order.id} - ${order.customer_name}<br>${formatMoney(order.total_amount)}đ`;
        alert.style.display = 'block';
        alert.classList.add('show');
    }
    
    function closeNewOrderAlert() {
        const alert = document.getElementById('newOrderAlert');
        alert.style.display = 'none';
        alert.classList.remove('show');
        stopSound(); // Dừng âm thanh khi đóng thông báo
    }
    
    // Khi click vào "Xem đơn hàng" - dừng âm thanh
    function viewOrder() {
        stopSound();
        window.location.href = 'orders.php';
    }
    
    function formatMoney(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Kiểm tra ngay khi load trang
    console.log('🚀 Notification system started! Shop ID: <?= $shop['id'] ?>, Last Order ID:', lastOrderId);
    checkNewOrders();
    
    // Kiểm tra mỗi 3 giây để thông báo nhanh hơn
    setInterval(checkNewOrders, 3000);
    
    // Yêu cầu quyền thông báo
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    </script>
    <?php endif; ?>
</body>
</html>
