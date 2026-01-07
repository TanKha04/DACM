<?php
/**
 * Shipper - Đơn hàng có sẵn
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Kiểm tra shipper có đang giao đơn nào không (đơn đã lấy hàng hoặc đang giao)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipper_id = ? AND status IN ('picked', 'delivering')");
$stmt->execute([$userId]);
$hasActiveDelivery = $stmt->fetchColumn() > 0;

// Kiểm tra shipper có đơn đang chờ chuẩn bị không
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipper_id = ? AND status IN ('confirmed', 'preparing', 'ready')");
$stmt->execute([$userId]);
$hasWaitingOrder = $stmt->fetchColumn() > 0;

// Nhận đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_order'])) {
    // Kiểm tra shipper có đang giao đơn khác không
    if ($hasActiveDelivery) {
        $message = 'error:Bạn đang có đơn hàng chưa giao xong. Vui lòng hoàn thành đơn hiện tại trước!';
    } elseif ($hasWaitingOrder) {
        $message = 'error:Bạn đã nhận 1 đơn đang chờ chuẩn bị. Vui lòng chờ người bán chuẩn bị xong!';
    } else {
        $orderId = (int)$_POST['order_id'];
        $shipperLat = floatval($_POST['shipper_lat'] ?? 0);
        $shipperLng = floatval($_POST['shipper_lng'] ?? 0);
        
        // Kiểm tra đơn còn available không (đơn đã xác nhận, đang chuẩn bị hoặc sẵn sàng)
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('confirmed', 'preparing', 'ready') AND shipper_id IS NULL");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Chỉ gán shipper, không đổi status - để người bán bấm "Bắt đầu chuẩn bị"
            $stmt = $pdo->prepare("UPDATE orders SET shipper_id = ? WHERE id = ?");
            $stmt->execute([$userId, $orderId]);
            
            // Cập nhật vị trí shipper vào shipper_info
            if ($shipperLat && $shipperLng) {
                $stmt = $pdo->prepare("INSERT INTO shipper_info (user_id, current_lat, current_lng) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE current_lat = ?, current_lng = ?");
                $stmt->execute([$userId, $shipperLat, $shipperLng, $shipperLat, $shipperLng]);
            }
            
            // Gửi thông báo cho người bán
            $sellerStmt = $pdo->prepare("SELECT s.user_id FROM orders o JOIN shops s ON o.shop_id = s.id WHERE o.id = ?");
            $sellerStmt->execute([$orderId]);
            $sellerId = $sellerStmt->fetchColumn();
            if ($sellerId) {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([$sellerId, '🚚 Shipper đã nhận đơn!', 'Đơn hàng #' . $orderId . ' đã có shipper nhận. Bạn có thể bắt đầu chuẩn bị hàng ngay!', 'order']);
            }
            $message = 'success:Đã nhận đơn thành công! Chờ người bán chuẩn bị hàng.';
            header('Location: my_orders.php');
            exit;
        } else {
            $message = 'error:Đơn hàng đã được nhận bởi shipper khác';
        }
    }
}

// Lấy vị trí hiện tại của shipper
$stmt = $pdo->prepare("SELECT current_lat, current_lng FROM shipper_info WHERE user_id = ?");
$stmt->execute([$userId]);
$shipperLocation = $stmt->fetch();
$shipperLat = $shipperLocation['current_lat'] ?? null;
$shipperLng = $shipperLocation['current_lng'] ?? null;

// Lấy tất cả đơn có sẵn (không giới hạn khoảng cách)
// Nếu shipper có vị trí thì tính khoảng cách, nếu không thì vẫn hiển thị đơn
if ($shipperLat && $shipperLng) {
    $stmt = $pdo->prepare("
        SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, s.latitude as shop_lat, s.longitude as shop_lng,
               CASE 
                   WHEN s.latitude IS NOT NULL AND s.longitude IS NOT NULL 
                   THEN (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude))))
                   ELSE NULL 
               END AS distance_to_shop
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id 
        WHERE o.status IN ('confirmed', 'preparing', 'ready') AND o.shipper_id IS NULL 
        ORDER BY distance_to_shop ASC, o.created_at ASC
    ");
    $stmt->execute([$shipperLat, $shipperLng, $shipperLat]);
} else {
    // Nếu shipper chưa có vị trí, vẫn hiển thị tất cả đơn (không có khoảng cách)
    $stmt = $pdo->query("
        SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, s.latitude as shop_lat, s.longitude as shop_lng,
               NULL AS distance_to_shop
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id 
        WHERE o.status IN ('confirmed', 'preparing', 'ready') AND o.shipper_id IS NULL 
        ORDER BY o.created_at ASC
    ");
}
$availableOrders = $stmt->fetchAll();

$statusLabels = [
    'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#3498db'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'color' => '#f39c12'],
    'ready' => ['label' => 'Sẵn sàng giao', 'color' => '#27ae60']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn có sẵn - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📦 Đơn hàng có sẵn</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if ($hasActiveDelivery): ?>
        <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 10px; color: #856404;">
            <strong>⚠️ Bạn đang có đơn hàng chưa hoàn thành!</strong><br>
            <p style="margin-top: 10px;">Vui lòng giao xong đơn hiện tại và bấm "Đã giao xong" trước khi nhận đơn mới.</p>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">📦 Xem đơn đang giao</a>
        </div>
        <?php elseif ($hasWaitingOrder): ?>
        <div class="alert" style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 10px; color: #0c5460;">
            <strong>⏳ Bạn đã nhận 1 đơn đang chờ chuẩn bị!</strong><br>
            <p style="margin-top: 10px;">Vui lòng chờ người bán chuẩn bị xong rồi mới nhận đơn mới.</p>
            <a href="my_orders.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">📦 Xem đơn của tôi</a>
        </div>
        <?php elseif (empty($availableOrders)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">📦</p>
            <h2>Không có đơn hàng nào</h2>
            <p style="color: #7f8c8d; margin-top: 10px;">Hiện tại chưa có đơn hàng nào đang chờ shipper nhận giao</p>
            <?php if (!$shipperLat || !$shipperLng): ?>
            <p style="color: #f39c12; margin-top: 15px;">💡 Bật GPS để xem khoảng cách đến shop</p>
            <button onclick="requestLocation()" class="btn btn-secondary" style="margin-top: 10px; padding: 12px 25px;">📍 Bật định vị</button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        
        <?php foreach ($availableOrders as $order): ?>
        <div class="card order-available-card" style="box-shadow: 0 8px 32px rgba(52,152,219,0.10); border: 2px solid #eaf6fb;">
            <div class="order-card" style="background: linear-gradient(90deg, #fafdff 60%, #eaf6fb 100%); margin: 0; padding: 32px 28px; border-radius: 18px; box-shadow: 0 2px 8px rgba(52,152,219,0.07);">
                <div class="order-header" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span class="order-id" style="font-size: 22px; color: #2980b9; font-weight: bold; letter-spacing: 1px;">#<?= $order['id'] ?></span>
                        <span class="badge" style="font-size: 15px; padding: 7px 18px; background: <?= $statusLabels[$order['status']]['color'] ?>20; color: <?= $statusLabels[$order['status']]['color'] ?>; font-weight: 600; margin-left: 10px;"><?= $statusLabels[$order['status']]['label'] ?></span>
                    </div>
                    <?php if (isset($order['distance_to_shop']) && $order['distance_to_shop'] !== null): ?>
                    <span style="background: #e8f8f5; color: #1abc9c; padding: 8px 15px; border-radius: 20px; font-weight: 600; font-size: 14px;">
                        📍 Cách <?= number_format($order['distance_to_shop'], 1) ?> km
                    </span>
                    <?php endif; ?>
                </div>
                <div class="order-details" style="display: flex; gap: 40px;">
                    <div class="order-detail-item" style="flex:1;">
                        <div class="label" style="font-size: 15px; color: #2980b9; font-weight: 600; margin-bottom: 4px;">🏪 Lấy hàng tại</div>
                        <div class="value" style="font-size: 18px; font-weight: bold; color: #273c75; margin-bottom: 2px;"> <?= htmlspecialchars($order['shop_name']) ?></div>
                        <div style="font-size: 14px; color: #7f8c8d; margin-bottom: 2px;"> <?= htmlspecialchars($order['shop_address']) ?></div>
                        <div style="font-size: 14px; color: #636e72;">📞 <?= $order['shop_phone'] ?></div>
                    </div>
                    <div class="order-detail-item" style="flex:1;">
                        <div class="label" style="font-size: 15px; color: #e17055; font-weight: 600; margin-bottom: 4px;">📍 Giao đến</div>
                        <div class="value" style="font-size: 18px; font-weight: bold; color: #d35400; margin-bottom: 2px;"> <?= htmlspecialchars($order['delivery_name']) ?></div>
                        <div style="font-size: 14px; color: #7f8c8d; margin-bottom: 2px;"> <?= htmlspecialchars($order['delivery_address']) ?></div>
                        <div style="font-size: 14px; color: #636e72;">📞 <?= $order['delivery_phone'] ?></div>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 18px; border-top: 2px dashed #d6eaf8;">
                    <div style="font-size: 18px; color: #636e72;">
                        <span style="color: #7f8c8d; font-size: 16px;">💸 Tiền ship:</span>
                        <strong style="color: #2980b9; font-size: 24px; margin-left: 10px; letter-spacing: 1px;"> <?= number_format($order['shipping_fee']) ?>đ</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="order_map.php?id=<?= $order['id'] ?>" class="btn btn-secondary" style="font-size: 16px; padding: 14px 20px; border-radius: 10px; text-decoration: none;">🗺️ Xem bản đồ</a>
                        <form method="POST" style="display: inline;" onsubmit="return submitWithLocation(this)">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <input type="hidden" name="shipper_lat" class="shipper-lat">
                            <input type="hidden" name="shipper_lng" class="shipper-lng">
                            <button type="submit" name="accept_order" value="1" class="btn btn-primary" style="font-size: 18px; padding: 14px 32px; border-radius: 10px; font-weight: bold; box-shadow: 0 2px 8px #d6eaf8;">✓ Nhận đơn này</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Nút bật/tắt âm thanh -->
    <div id="soundToggle" style="position: fixed; bottom: 20px; right: 20px; z-index: 9998;">
        <button onclick="toggleSound()" id="soundBtn" style="background: #27ae60; color: white; border: none; padding: 15px 20px; border-radius: 50px; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px;">
            <span id="soundIcon">🔔</span>
            <span id="soundText">Âm thanh: BẬT</span>
        </button>
    </div>
    
    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
    
    <script>
    // Lấy vị trí GPS khi trang load
    let currentLat = null, currentLng = null;
    let lastOrderCount = <?= count($availableOrders) ?>;
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
        stopSound();
        playBeepOnce();
        
        soundInterval = setInterval(() => {
            playBeepOnce();
        }, 3000);
        
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
    
    // Kiểm tra đơn mới qua API
    function checkNewOrders() {
        console.log('Checking orders... lastCount:', lastOrderCount, 'lastReady:', lastReadyCount);
        fetch('../api/check_shipper_orders.php')
            .then(res => res.json())
            .then(data => {
                console.log('API response:', data);
                
                let shouldPlaySound = false;
                
                // Có đơn mới
                if (data.available > lastOrderCount && data.available > 0) {
                    console.log('🚨 NEW ORDER! Playing sound...');
                    shouldPlaySound = true;
                    showNewOrderNotification(data.available - lastOrderCount, data.new_order);
                }
                
                // Có đơn chuyển sang ready (người bán bấm "Sẵn sàng")
                if (data.ready > lastReadyCount && data.ready > 0) {
                    console.log('🚨 ORDER READY! Playing sound...');
                    shouldPlaySound = true;
                    showReadyNotification(data.ready);
                }
                
                if (shouldPlaySound && soundEnabled) {
                    startContinuousSound();
                }
                
                lastOrderCount = data.available;
                lastReadyCount = data.ready || 0;
                
                // Reload nếu có đơn mới
                if (shouldPlaySound) {
                    setTimeout(() => location.reload(), 2000);
                }
            })
            .catch(err => console.log('Lỗi kiểm tra đơn:', err));
    }
    
    // Hiển thị thông báo đơn mới
    function showNewOrderNotification(newCount, newOrder) {
        const popup = document.createElement('div');
        popup.style.cssText = 'position:fixed;top:20px;right:20px;background:linear-gradient(135deg,#27ae60,#2ecc71);color:white;padding:20px 30px;border-radius:15px;box-shadow:0 10px 40px rgba(39,174,96,0.4);z-index:9999;animation:slideIn 0.5s ease;font-size:16px;';
        
        let orderInfo = '';
        if (newOrder) {
            orderInfo = `<p style="margin:8px 0 0;font-size:14px;">🏪 ${newOrder.shop_name} - 💰 ${new Intl.NumberFormat('vi-VN').format(newOrder.shipping_fee)}đ</p>`;
        }
        
        popup.innerHTML = `
            <div style="display:flex;align-items:center;gap:15px;">
                <span style="font-size:35px;">🔔</span>
                <div>
                    <strong style="font-size:18px;">Có ${newCount} đơn hàng mới!</strong>
                    <p style="margin:5px 0 0;opacity:0.9;">Nhanh tay nhận đơn ngay!</p>
                    ${orderInfo}
                </div>
            </div>
        `;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 5000);
    }
    
    // Hiển thị thông báo đơn sẵn sàng
    function showReadyNotification(readyCount) {
        const popup = document.createElement('div');
        popup.style.cssText = 'position:fixed;top:20px;right:20px;background:linear-gradient(135deg,#e74c3c,#c0392b);color:white;padding:20px 30px;border-radius:15px;box-shadow:0 10px 40px rgba(231,76,60,0.4);z-index:9999;animation:slideIn 0.5s ease;font-size:16px;';
        
        popup.innerHTML = `
            <div style="display:flex;align-items:center;gap:15px;">
                <span style="font-size:35px;">🚨</span>
                <div>
                    <strong style="font-size:18px;">Có ${readyCount} đơn SẴN SÀNG!</strong>
                    <p style="margin:5px 0 0;opacity:0.9;">Người bán đã chuẩn bị xong!</p>
                </div>
            </div>
        `;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 5000);
    }
    
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // Kiểm tra ngay khi load
    console.log('🚀 Shipper notification started! Orders:', lastOrderCount);
    checkNewOrders();
    
    // Kiểm tra mỗi 3 giây (giống seller)
    setInterval(checkNewOrders, 3000);
    
    // Cập nhật vị trí shipper lên server
    function updateShipperLocationToServer(lat, lng) {
        fetch('../api/shipper_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('📍 Đã cập nhật vị trí shipper:', lat, lng);
                if (!currentLat) {
                    location.reload();
                }
            }
        })
        .catch(err => console.log('Lỗi cập nhật vị trí:', err));
    }
    
    // Hàm yêu cầu vị trí (gọi từ nút)
    function requestLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    currentLat = pos.coords.latitude;
                    currentLng = pos.coords.longitude;
                    updateShipperLocationToServer(currentLat, currentLng);
                },
                function(err) {
                    let msg = 'Không thể xác định vị trí!';
                    if (err.code === 1) msg = 'Bạn đã từ chối quyền truy cập vị trí. Vui lòng cho phép trong cài đặt trình duyệt!';
                    else if (err.code === 2) msg = 'Không thể xác định vị trí!';
                    else if (err.code === 3) msg = 'Hết thời gian chờ!';
                    alert('⚠️ ' + msg);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            alert('Trình duyệt không hỗ trợ GPS!');
        }
    }
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                console.log('📍 Vị trí shipper:', currentLat, currentLng);
                updateShipperLocationToServer(currentLat, currentLng);
            },
            function(err) {
                console.log('Không lấy được vị trí:', err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
        
        navigator.geolocation.watchPosition(
            function(pos) {
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                updateShipperLocationToServer(currentLat, currentLng);
            },
            function() {},
            { enableHighAccuracy: true }
        );
    }
    
    function submitWithLocation(form) {
        if (currentLat && currentLng) {
            form.querySelector('.shipper-lat').value = currentLat;
            form.querySelector('.shipper-lng').value = currentLng;
        }
        return true;
    }
    </script>
</body>
</html>
