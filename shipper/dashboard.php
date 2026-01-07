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

// Lấy vị trí shipper
$shipperLat = $shipperInfo['current_lat'] ?? null;
$shipperLng = $shipperInfo['current_lng'] ?? null;

// Đơn có sẵn - đếm tất cả đơn (không giới hạn khoảng cách)
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
    <style>
        /* Hiệu ứng Tết */
        @keyframes fall { 0% { transform: translateY(-10vh) rotate(0deg); } 100% { transform: translateY(100vh) rotate(360deg); } }
        @keyframes sway { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(20px); } }
        @keyframes swing { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }
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
    </style>
</head>
<body>
    <!-- Hoa mai rơi -->
    <div class="tet-flowers" id="tetFlowers"></div>
    
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
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
        <style>
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
            @keyframes swing { 0%, 100% { transform: rotate(-10deg); } 50% { transform: rotate(10deg); } }
            .welcome-banner h2 {
                font-size: 24px;
                font-weight: 700;
                font-style: italic;
                margin-bottom: 15px;
                white-space: normal;
                line-height: 1.3;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
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
        </style>
        
        <?php
        $hour = date('H');
        if ($hour < 12) $greeting = 'Chào buổi sáng';
        elseif ($hour < 18) $greeting = 'Chào buổi chiều';
        else $greeting = 'Chào buổi tối';
        
        // Lời chúc Tết
        $tetGreetings = ['🧧 Năm mới Phát Tài!', '🌸 Vạn Sự Như Ý!', '🏮 An Khang Thịnh Vượng!'];
        $tetGreeting = $tetGreetings[array_rand($tetGreetings)];
        ?>
        
        <div class="welcome-banner">
            <div style="flex: 1; min-width: 200px;">
                <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <p style="font-size: 18px; color: #fbbf24; margin-bottom: 12px; font-weight: 600;"><?= $tetGreeting ?></p>
                <div class="welcome-badges">
                    <span class="welcome-badge">🚚 Shipper</span>
                    <span class="welcome-badge"><?= $shipperInfo['is_available'] ? '✅ Đang hoạt động' : '⏸️ Tạm nghỉ' ?></span>
                </div>
                <p class="welcome-text">Chọn một chức năng từ menu bên trái để bắt đầu.</p>
            </div>
            <div class="welcome-logo">
                <img src="../logo.png" alt="Logo" style="width: 120px; height: 120px;">
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
        <?php elseif ($availableOrders == 0): ?>
        <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 10px; color: #856404;">
            <strong>💡 Lưu ý:</strong> Hiện không có đơn hàng nào đang chờ shipper. Trang sẽ tự động cập nhật.
        </div>
        <?php endif; ?>
        
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
    
    <!-- Nút bật/tắt âm thanh -->
    <div id="soundToggle" style="position: fixed; bottom: 20px; right: 20px; z-index: 9998;">
        <button onclick="toggleSound()" id="soundBtn" style="background: #27ae60; color: white; border: none; padding: 15px 20px; border-radius: 50px; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px;">
            <span id="soundIcon">🔔</span>
            <span id="soundText">Âm thanh: BẬT</span>
        </button>
    </div>
    
    <script>
    // Kiểm tra đơn hàng mới cho shipper - GIỐNG TRANG NGƯỜI BÁN
    let lastAvailableCount = <?= $availableOrders ?>;
    let lastReadyCount = 0;
    let soundInterval = null;
    let soundTimeout = null;
    let soundEnabled = localStorage.getItem('shipperSoundEnabled') !== 'false';
    
    // Tạo âm thanh thông báo - phát 1 lần (giống seller)
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
    
    // Bắt đầu reo liên tục (mỗi 3 giây, tối đa 5 phút) - giống seller
    function startContinuousSound() {
        if (!soundEnabled) return;
        
        stopSound();
        playBeepOnce();
        
        soundInterval = setInterval(() => {
            playBeepOnce();
        }, 3000);
        
        // Tự động dừng âm thanh sau 5 phút (nhưng thông báo vẫn hiển thị)
        soundTimeout = setTimeout(() => {
            stopSound();
            console.log('⏰ Âm thanh tự động dừng sau 5 phút');
        }, 300000);
    }
    
    // Dừng âm thanh (không ẩn thông báo)
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
    
    // Ẩn thông báo và dừng âm thanh
    function hideAlertAndStopSound() {
        const alert = document.getElementById('newOrderAlert');
        alert.style.display = 'none';
        alert.classList.remove('show');
        stopSound();
    }
    
    // Cập nhật UI nút âm thanh
    function updateSoundButton() {
        const btn = document.getElementById('soundBtn');
        const icon = document.getElementById('soundIcon');
        const text = document.getElementById('soundText');
        if (soundEnabled) {
            btn.style.background = '#27ae60';
            icon.textContent = '🔔';
            text.textContent = 'Âm thanh: BẬT';
        } else {
            btn.style.background = '#e74c3c';
            icon.textContent = '🔕';
            text.textContent = 'Âm thanh: TẮT';
        }
    }
    
    // Bật/tắt âm thanh
    function toggleSound() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('shipperSoundEnabled', soundEnabled);
        updateSoundButton();
        
        if (soundEnabled) {
            playBeepOnce();
        } else {
            stopSound();
        }
    }
    
    updateSoundButton();
    
    function checkNewOrders() {
        console.log('Checking shipper orders... lastAvailable:', lastAvailableCount, 'lastReady:', lastReadyCount);
        fetch('../api/check_shipper_orders.php')
            .then(response => response.json())
            .then(data => {
                console.log('Shipper API response:', data);
                
                let shouldPlaySound = false;
                let alertMessage = '';
                
                // Có đơn mới
                if (data.available > lastAvailableCount && data.available > 0) {
                    console.log('🚨 NEW ORDER! Playing sound...');
                    shouldPlaySound = true;
                    alertMessage = `Có <strong>${data.available}</strong> đơn hàng đang chờ!`;
                }
                
                // Có đơn chuyển sang ready (người bán bấm "Sẵn sàng")
                if (data.ready > lastReadyCount && data.ready > 0) {
                    console.log('🚨 ORDER READY! Playing sound...');
                    shouldPlaySound = true;
                    alertMessage = `Có <strong>${data.ready}</strong> đơn hàng <span style="color:#e74c3c;font-weight:bold;">SẴN SÀNG</span> để lấy ngay!`;
                }
                
                if (shouldPlaySound) {
                    showNewOrderAlert(data.available, alertMessage);
                    startContinuousSound();
                }
                
                lastAvailableCount = data.available;
                lastReadyCount = data.ready || 0;
                
                // Cập nhật số liệu
                const statCards = document.querySelectorAll('.stat-card');
                if (statCards[2]) {
                    statCards[2].querySelector('.value').textContent = data.active || 0;
                }
            })
            .catch(err => console.log('Check orders error:', err));
    }
    
    function showNewOrderAlert(count, customMessage) {
        const alert = document.getElementById('newOrderAlert');
        const info = document.getElementById('newOrderInfo');
        if (customMessage) {
            info.innerHTML = customMessage + `<br><small style="opacity:0.8">🏃‍♂️ Ai nhận trước - được giao!</small>`;
        } else {
            info.innerHTML = `Có <strong>${count}</strong> đơn hàng đang chờ!<br><small style="opacity:0.8">🏃‍♂️ Ai nhận trước - được giao!</small>`;
        }
        alert.style.display = 'block';
        alert.classList.add('show');
    }
    
    function closeNewOrderAlert() {
        hideAlertAndStopSound();
    }
    
    function viewAvailableOrders() {
        hideAlertAndStopSound();
        window.location.href = 'available.php';
    }
    
    // Kiểm tra ngay khi load
    console.log('🚀 Shipper notification system started! Available:', lastAvailableCount);
    checkNewOrders();
    
    // Kiểm tra mỗi 3 giây (giống seller)
    setInterval(checkNewOrders, 3000);
    
    // ===== CẬP NHẬT VỊ TRÍ SHIPPER REALTIME =====
    <?php if ($activeOrders > 0): ?>
    function updateShipperLocation(lat, lng) {
        fetch('../api/shipper_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('📍 Đã cập nhật vị trí:', lat, lng);
            }
        })
        .catch(err => console.log('Lỗi cập nhật vị trí:', err));
    }
    
    // Theo dõi vị trí liên tục khi đang giao hàng
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
            function(err) {
                console.log('Lỗi theo dõi vị trí:', err.message);
            },
            { enableHighAccuracy: true, maximumAge: 5000 }
        );
        
        // Backup: cập nhật mỗi 5 giây để khách hàng theo dõi realtime
        setInterval(() => {
            navigator.geolocation.getCurrentPosition(
                pos => updateShipperLocation(pos.coords.latitude, pos.coords.longitude),
                err => console.log('Backup update failed:', err.message)
            );
        }, 5000);
    } else {
        console.log('Trình duyệt không hỗ trợ GPS');
    }
    <?php endif; ?>
    </script>
    
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
