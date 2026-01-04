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
<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $base ?>/home.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 12px;">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 45px; height: 45px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <span style="font-size: 20px; font-weight: 700;">Seller Panel</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= $base ?>/seller/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <span class="icon">📊</span> Dashboard
        </a>
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
