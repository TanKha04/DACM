<?php
/**
 * Shipper Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy hoặc tạo shipper info
$stmt = $pdo->prepare("SELECT * FROM shipper_info WHERE user_id = ?");
$stmt->execute([$userId]);
$shipperInfo = $stmt->fetch();

if (!$shipperInfo) {
    $stmt = $pdo->prepare("INSERT INTO shipper_info (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    $stmt = $pdo->prepare("SELECT * FROM shipper_info WHERE user_id = ?");
    $stmt->execute([$userId]);
    $shipperInfo = $stmt->fetch();
}

$today = date('Y-m-d');

// Thống kê hôm nay
$stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(shipping_fee), 0) as earnings FROM orders WHERE shipper_id = ? AND status = 'delivered' AND DATE(updated_at) = ?");
$stmt->execute([$userId, $today]);
$todayStats = $stmt->fetch();

// Đơn đang giao
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE shipper_id = ? AND status IN ('picked', 'delivering')");
$stmt->execute([$userId]);
$activeOrders = $stmt->fetch()['total'];

// Đơn có sẵn (đơn đã được xác nhận hoặc sẵn sàng)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status IN ('confirmed', 'preparing', 'ready') AND shipper_id IS NULL");
$availableOrders = $stmt->fetch()['total'];

// Đơn đang giao của tôi
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone 
    FROM orders o JOIN shops s ON o.shop_id = s.id 
    WHERE o.shipper_id = ? AND o.status IN ('picked', 'delivering') 
    ORDER BY o.created_at DESC");
$stmt->execute([$userId]);
$myActiveOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipper Dashboard - FastFood</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🏠 Trang chủ</h1>
            <span style="color: #7f8c8d; font-size: 15px;"><?= date('d/m/Y H:i') ?></span>
        </div>
        
        <!-- Welcome Banner -->
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
                font-size: 26px;
                font-weight: 700;
                font-style: italic;
                margin-bottom: 15px;
                white-space: nowrap;
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
        </style>
        
        <?php
        $hour = date('H');
        if ($hour < 12) $greeting = 'Chào buổi sáng';
        elseif ($hour < 18) $greeting = 'Chào buổi chiều';
        else $greeting = 'Chào buổi tối';
        ?>
        
        <div class="welcome-banner">
            <div style="flex: 1; min-width: 0;">
                <h2><?= $greeting ?>, Shipper <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <div class="welcome-badges">
                    <span class="welcome-badge">🚚 Shipper</span>
                    <span class="welcome-badge"><?= $shipperInfo['is_available'] ? '✅ Đang hoạt động' : '⏸️ Tạm nghỉ' ?></span>
                </div>
                <p class="welcome-text">Chọn một chức năng từ menu bên trái để bắt đầu.</p>
            </div>
            <div class="welcome-logo">
                <img src="../logo.png" alt="Logo">
            </div>
            <div class="welcome-actions">
                <a href="available.php" class="welcome-btn">📦 Đơn có sẵn</a>
                <a href="my_orders.php" class="welcome-btn">🚚 Đơn của tôi</a>
                <a href="earnings.php" class="welcome-btn">💰 Thu nhập</a>
            </div>
        </div>
        
        <!-- Thông báo đơn hàng mới -->
        <div id="newOrderAlert" style="display: none; position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px 25px; border-radius: 15px; box-shadow: 0 10px 40px rgba(52,152,219,0.4); z-index: 9999; animation: slideIn 0.5s ease; max-width: 350px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 40px;">🚚</div>
                <div>
                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">Có đơn hàng mới!</div>
                    <div id="newOrderInfo" style="font-size: 14px; opacity: 0.9;"></div>
                </div>
            </div>
            <button onclick="closeNewOrderAlert()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
            <button onclick="viewAvailableOrders()" style="display: block; width: 100%; margin-top: 15px; background: white; color: #3498db; padding: 10px 20px; border-radius: 8px; border: none; text-align: center; font-weight: bold; cursor: pointer;">Nhận đơn ngay</button>
        </div>
        
        <style>
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            #newOrderAlert.show { animation: slideIn 0.5s ease, pulse 2s infinite; }
        </style>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📦</div>
                <div class="value"><?= $todayStats['total'] ?></div>
                <div class="label">Đơn giao hôm nay</div>
            </div>
            <div class="stat-card">
                <div class="icon">💵</div>
                <div class="value"><?= number_format($todayStats['earnings']) ?>đ</div>
                <div class="label">Thu nhập hôm nay</div>
            </div>
            <div class="stat-card">
                <div class="icon">🚚</div>
                <div class="value"><?= $activeOrders ?></div>
                <div class="label">Đang giao</div>
            </div>
        </div>
        
        <?php if ($activeOrders > 0): ?>
        <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 10px; color: #856404;">
            <strong>⚠️ Bạn đang có <?= $activeOrders ?> đơn hàng chưa hoàn thành.</strong> Vui lòng giao xong và bấm "Đã giao xong" trước khi nhận đơn mới.
        </div>
        <?php elseif ($availableOrders > 0): ?>
        <div class="alert alert-success" id="shipper-order-alert" style="display: flex; align-items: center; font-size: 18px; padding: 18px 28px; border: 2px solid #2ecc40; background: #e8fbe8; color: #145a32; font-weight: 500;">
            <span style="font-size: 28px; margin-right: 14px;">🚨</span>
            <span>
                <span style="font-size: 20px; color: #117a65; font-weight: bold;">THÔNG BÁO:</span> <br>
                📦 Có <strong style="font-size: 22px; color: #d35400;"><?= $availableOrders ?></strong> đơn hàng <b>đang chờ shipper nhận giao!</b> <a href="available.php" style="color: #117a65; text-decoration: underline; font-weight: bold;">Xem ngay →</a>
            </span>
        </div>
        <audio id="shipper-alert-sound" src="https://cdn.pixabay.com/audio/2022/07/26/audio_124bfa1c82.mp3" preload="auto"></audio>
        <script>
        window.addEventListener('DOMContentLoaded', function() {
            var alertBox = document.getElementById('shipper-order-alert');
            var audio = document.getElementById('shipper-alert-sound');
            if (alertBox && audio) {
                setTimeout(function() { audio.play(); }, 500);
            }
        });
        </script>
        <?php else: ?>
        <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 10px; color: #856404;">
            <strong>💡 Lưu ý:</strong> Đơn hàng sẽ hiển thị khi người bán đã chuẩn bị xong và đánh dấu "Sẵn sàng giao". Trang sẽ tự động cập nhật mỗi 30 giây.
        </div>
        <?php endif; ?>
        
        <!-- Auto refresh mỗi 30 giây để kiểm tra đơn mới -->
        <script>
        setTimeout(function() {
            location.reload();
        }, 30000);
        </script>
        
        <div class="card">
            <h2>🚚 Đơn đang giao</h2>
            
            <?php if (empty($myActiveOrders)): ?>
            <p style="color: #999; text-align: center; padding: 30px;">Bạn chưa có đơn nào đang giao</p>
            <?php else: ?>
            <?php foreach ($myActiveOrders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <span class="order-id">#<?= $order['id'] ?></span>
                    <span class="badge badge-<?= $order['status'] ?>"><?= $order['status'] === 'picked' ? 'Đã lấy hàng' : 'Đang giao' ?></span>
                </div>
                <div class="order-details">
                    <div class="order-detail-item">
                        <div class="label">🏪 Cửa hàng</div>
                        <div class="value"><?= htmlspecialchars($order['shop_name']) ?></div>
                        <div style="font-size: 13px; color: #7f8c8d;"><?= htmlspecialchars($order['shop_address']) ?></div>
                    </div>
                    <div class="order-detail-item">
                        <div class="label">📍 Giao đến</div>
                        <div class="value"><?= htmlspecialchars($order['delivery_name']) ?></div>
                        <div style="font-size: 13px; color: #7f8c8d;"><?= htmlspecialchars($order['delivery_address']) ?></div>
                    </div>
                </div>
                <div class="order-details">
                    <div class="order-detail-item">
                        <div class="label">📞 SĐT khách</div>
                        <div class="value"><?= $order['delivery_phone'] ?></div>
                    </div>
                    <div class="order-detail-item">
                        <div class="label">💵 Tiền ship</div>
                        <div class="value" style="color: #3498db;"><?= number_format($order['shipping_fee']) ?>đ</div>
                    </div>
                </div>
                <div class="order-actions">
                    <?php if ($order['status'] === 'picked'): ?>
                    <form method="POST" action="update_status.php">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="status" value="delivering">
                        <button type="submit" class="btn btn-primary">🚀 Bắt đầu giao</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="update_status.php">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="status" value="delivered">
                        <button type="submit" class="btn btn-success">✅ Đã giao xong</button>
                    </form>
                    <?php endif; ?>
                    <a href="tel:<?= $order['delivery_phone'] ?>" class="btn btn-secondary">📞 Gọi khách</a>
                    <a href="chat_customer.php?order_id=<?= $order['id'] ?>" class="btn btn-info" style="background: #3498db; color: white;">💬 Nhắn tin</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Kiểm tra đơn hàng mới cho shipper
    let lastAvailableCount = 0; // Bắt đầu từ 0 để phát âm thanh khi có đơn
    let soundInterval = null;
    let soundTimeout = null;
    let hasUserInteracted = false;
    
    // Lắng nghe tương tác người dùng để có thể phát âm thanh
    document.addEventListener('click', () => { hasUserInteracted = true; }, { once: true });
    document.addEventListener('keydown', () => { hasUserInteracted = true; }, { once: true });
    document.addEventListener('touchstart', () => { hasUserInteracted = true; }, { once: true });
    
    // Tạo âm thanh thông báo
    function playBeepOnce() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Resume audio context nếu bị suspended
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            
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
            playBeep(now, 600, 0.15);
            playBeep(now + 0.2, 800, 0.15);
            playBeep(now + 0.5, 600, 0.15);
            playBeep(now + 0.7, 1000, 0.2);
            
            console.log('🔔 Đã phát âm thanh thông báo');
        } catch (e) {
            console.log('Audio error:', e);
        }
    }
    
    // Bắt đầu reo liên tục
    function startContinuousSound() {
        stopSound();
        playBeepOnce();
        soundInterval = setInterval(() => {
            playBeepOnce();
        }, 3000);
        soundTimeout = setTimeout(() => {
            stopSound();
        }, 300000); // 5 phút
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
        fetch('../api/check_shipper_orders.php')
            .then(response => response.json())
            .then(data => {
                console.log('Shipper API response:', data, 'lastCount:', lastAvailableCount);
                
                // Phát âm thanh nếu có đơn mới (số đơn tăng lên)
                if (data.available > lastAvailableCount && data.available > 0) {
                    console.log('🚨 Có đơn hàng mới! Phát âm thanh...');
                    showNewOrderAlert(data.available);
                    startContinuousSound();
                }
                
                lastAvailableCount = data.available;
                
                // Cập nhật số liệu trên dashboard
                const statCards = document.querySelectorAll('.stat-card');
                if (statCards[2]) {
                    statCards[2].querySelector('.value').textContent = data.active || 0;
                }
            })
            .catch(err => console.log('Check orders error:', err));
    }
    
    function showNewOrderAlert(count) {
        const alert = document.getElementById('newOrderAlert');
        const info = document.getElementById('newOrderInfo');
        info.innerHTML = `Có <strong>${count}</strong> đơn hàng đang chờ shipper nhận!`;
        alert.style.display = 'block';
        alert.classList.add('show');
    }
    
    function closeNewOrderAlert() {
        const alert = document.getElementById('newOrderAlert');
        alert.style.display = 'none';
        alert.classList.remove('show');
        stopSound();
    }
    
    function viewAvailableOrders() {
        stopSound();
        window.location.href = 'available.php';
    }
    
    // Kiểm tra ngay khi trang load (sau 1 giây để đảm bảo trang đã sẵn sàng)
    setTimeout(checkNewOrders, 1000);
    
    // Kiểm tra mỗi 5 giây
    setInterval(checkNewOrders, 5000);
    
    // Hiển thị hướng dẫn nếu chưa tương tác
    setTimeout(() => {
        if (!hasUserInteracted && <?= $availableOrders ?> > 0) {
            console.log('⚠️ Vui lòng click vào trang để bật âm thanh thông báo');
        }
    }, 2000);
    </script>
</body>
</html>
