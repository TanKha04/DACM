<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();

// Xử lý đổi mật khẩu
$pwdMessage = '';
$pwdError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
        $pwdError = 'Vui lòng điền đầy đủ thông tin';
    } elseif ($newPwd !== $confirmPwd) {
        $pwdError = 'Mật khẩu mới không khớp';
    } elseif (strlen($newPwd) < 6) {
        $pwdError = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } else {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPwd, $user['password'])) {
            $pwdError = 'Mật khẩu hiện tại không đúng';
        } else {
            $hashedPwd = password_hash($newPwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPwd, $_SESSION['user_id']]);
            $pwdMessage = 'Đổi mật khẩu thành công!';
        }
    }
}
?>
<div class="sidebar" style="background: linear-gradient(135deg, #dc2626, #b91c1c) !important;">
    <!-- Trang trí Tết -->
    <div style="position: absolute; top: 10px; right: 10px; font-size: 24px; animation: swing 2s ease-in-out infinite;">🏮</div>
    <style>
        @keyframes swing { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    </style>
    <div class="sidebar-header" style="background: rgba(255,255,255,0.1) !important;">
        <a href="<?= $base ?>/seller/dashboard.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 12px;">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 45px; height: 45px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <span style="font-size: 20px; font-weight: 700;">🧧 Seller Panel</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= $base ?>/seller/products.php" class="<?= $currentPage == 'products.php' ? 'active' : '' ?>">
            <span class="icon">🍔</span> Sản phẩm
        </a>
        <a href="<?= $base ?>/seller/combos.php" class="<?= $currentPage == 'combos.php' ? 'active' : '' ?>">
            <span class="icon">🎯</span> Combo
        </a>
        <a href="<?= $base ?>/seller/orders.php" class="<?= $currentPage == 'orders.php' ? 'active' : '' ?>">
            <span class="icon">📦</span> Đơn hàng
        </a>
        <a href="<?= $base ?>/seller/promotions.php" class="<?= $currentPage == 'promotions.php' ? 'active' : '' ?>">
            <span class="icon">🎁</span> Khuyến mãi
        </a>
        <a href="<?= $base ?>/seller/revenue.php" class="<?= $currentPage == 'revenue.php' ? 'active' : '' ?>">
            <span class="icon">💰</span> Doanh thu
        </a>
        <a href="<?= $base ?>/seller/reviews.php" class="<?= $currentPage == 'reviews.php' ? 'active' : '' ?>">
            <span class="icon">⭐</span> Đánh giá
        </a>
        <a href="<?= $base ?>/seller/messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
            <span class="icon">💬</span> Tin nhắn
        </a>
        <a href="<?= $base ?>/seller/shop.php" class="<?= $currentPage == 'shop.php' ? 'active' : '' ?>">
            <span class="icon">🏪</span> Cửa hàng
        </a>
        <a href="javascript:void(0)" onclick="openPasswordModal()">
            <span class="icon">🔑</span> Đổi mật khẩu
        </a>
        <a href="<?= $base ?>/seller/support.php" class="support-link <?= $currentPage == 'support.php' ? 'active' : '' ?>">
            <span class="icon">🎧</span> Hỗ trợ
        </a>
        <a href="<?= $base ?>/auth/logout.php" class="logout-link">
            <span class="icon">🚪</span> Đăng xuất
        </a>
    </nav>
</div>

<!-- Modal Đổi mật khẩu -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="margin: 0;">🔑 Đổi mật khẩu</h3>
            <span class="modal-close" onclick="closePasswordModal()">&times;</span>
        </div>
        <div class="modal-body">
            <?php if ($pwdMessage): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                ✅ <?= $pwdMessage ?>
            </div>
            <?php endif; ?>
            <?php if ($pwdError): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;">
                ❌ <?= $pwdError ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label>Mật khẩu hiện tại</label>
                    <input type="password" name="current_password" required placeholder="Nhập mật khẩu hiện tại">
                </div>
                <div class="form-group">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" required placeholder="Nhập mật khẩu mới (ít nhất 6 ký tự)">
                </div>
                <div class="form-group">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Hủy</button>
                    <button type="submit" name="change_password" class="btn btn-primary">Đổi mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPasswordModal() {
    document.getElementById('passwordModal').classList.add('active');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('active');
}

// Đóng modal khi click bên ngoài
document.getElementById('passwordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePasswordModal();
    }
});

// Mở modal nếu có lỗi hoặc thông báo
<?php if ($pwdError || $pwdMessage): ?>
document.addEventListener('DOMContentLoaded', function() {
    openPasswordModal();
});
<?php endif; ?>
</script>

<?php
// Lấy shop_id cho thông báo đơn hàng mới
$shopForNotif = null;
if (isset($_SESSION['user_id'])) {
    $pdo = getConnection();
    $stmtShop = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND status = 'active'");
    $stmtShop->execute([$_SESSION['user_id']]);
    $shopForNotif = $stmtShop->fetch();
}
?>

<?php if ($shopForNotif): ?>
<!-- Thông báo đơn hàng mới - Toàn cục cho tất cả trang seller -->
<div id="globalNewOrderAlert" style="display: none; position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 20px 25px; border-radius: 15px; box-shadow: 0 10px 40px rgba(39,174,96,0.4); z-index: 99999; max-width: 350px; animation: slideIn 0.5s ease;">
    <style>
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }
        #globalNewOrderAlert.pulsing { animation: slideIn 0.5s ease, pulse 1s infinite; }
    </style>
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="font-size: 40px;">🔔</div>
        <div>
            <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">Đơn hàng mới!</div>
            <div id="globalNewOrderInfo" style="font-size: 14px; opacity: 0.9;"></div>
        </div>
    </div>
    <button onclick="closeGlobalOrderAlert()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
    <button onclick="goToOrders()" style="display: block; width: 100%; margin-top: 15px; background: white; color: #27ae60; padding: 10px 20px; border-radius: 8px; border: none; text-align: center; font-weight: bold; cursor: pointer;">📦 Xem đơn hàng</button>
</div>

<script>
(function() {
    // Tránh chạy trùng nếu trang đã có script riêng
    if (window.globalOrderNotificationLoaded) return;
    window.globalOrderNotificationLoaded = true;
    
    const SHOP_ID = <?= $shopForNotif['id'] ?>;
    let lastCheckedOrderId = localStorage.getItem('seller_last_order_id_' + SHOP_ID) || 0;
    let soundInterval = null;
    let soundTimeout = null;
    
    // Tạo âm thanh thông báo
    function playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            function beep(time, freq, duration) {
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                osc.connect(gain);
                gain.connect(audioContext.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.6, time);
                gain.gain.exponentialRampToValueAtTime(0.01, time + duration);
                osc.start(time);
                osc.stop(time + duration);
            }
            
            const now = audioContext.currentTime;
            // Âm thanh rõ ràng hơn
            beep(now, 880, 0.15);
            beep(now + 0.2, 1100, 0.15);
            beep(now + 0.4, 880, 0.15);
            beep(now + 0.6, 1320, 0.25);
        } catch (e) {
            console.log('Audio error:', e);
        }
    }
    
    function startContinuousSound() {
        stopSound();
        playNotificationSound();
        soundInterval = setInterval(playNotificationSound, 3000);
        soundTimeout = setTimeout(stopSound, 300000); // Dừng sau 5 phút
    }
    
    function stopSound() {
        if (soundInterval) { clearInterval(soundInterval); soundInterval = null; }
        if (soundTimeout) { clearTimeout(soundTimeout); soundTimeout = null; }
    }
    
    function checkNewOrders() {
        fetch('<?= $base ?>/api/check_new_orders.php?shop_id=' + SHOP_ID + '&last_id=' + lastCheckedOrderId)
            .then(res => res.json())
            .then(data => {
                if (data.hasNew && data.order) {
                    lastCheckedOrderId = data.order.id;
                    localStorage.setItem('seller_last_order_id_' + SHOP_ID, lastCheckedOrderId);
                    showGlobalOrderAlert(data.order);
                    startContinuousSound();
                }
            })
            .catch(err => console.log('Check orders error:', err));
    }
    
    function showGlobalOrderAlert(order) {
        const alert = document.getElementById('globalNewOrderAlert');
        const info = document.getElementById('globalNewOrderInfo');
        if (alert && info) {
            info.innerHTML = `Đơn #${order.id} - ${order.customer_name}<br><strong>${formatMoney(order.total_amount)}đ</strong>`;
            alert.style.display = 'block';
            alert.classList.add('pulsing');
        }
    }
    
    window.closeGlobalOrderAlert = function() {
        const alert = document.getElementById('globalNewOrderAlert');
        if (alert) {
            alert.style.display = 'none';
            alert.classList.remove('pulsing');
        }
        stopSound();
    };
    
    window.goToOrders = function() {
        stopSound();
        window.location.href = '<?= $base ?>/seller/orders.php';
    };
    
    function formatMoney(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Bắt đầu kiểm tra
    console.log('🔔 Seller notification system started! Shop ID:', SHOP_ID);
    checkNewOrders();
    setInterval(checkNewOrders, 3000);
})();
</script>
<?php endif; ?>
