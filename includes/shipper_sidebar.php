<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
?>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $base ?>/home.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px;">
            <span style="font-size: 18px; font-weight: bold;">Shipper Panel</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= $base ?>/shipper/dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>
        <a href="<?= $base ?>/shipper/available.php" class="<?= $currentPage == 'available.php' ? 'active' : '' ?>">
            <span>📦</span> Đơn có sẵn
        </a>
        <a href="<?= $base ?>/shipper/my_orders.php" class="<?= $currentPage == 'my_orders.php' ? 'active' : '' ?>">
            <span>🚚</span> Đơn của tôi
        </a>
        <a href="<?= $base ?>/shipper/earnings.php" class="<?= $currentPage == 'earnings.php' ? 'active' : '' ?>">
            <span>💵</span> Thu nhập
        </a>
        <a href="<?= $base ?>/shipper/messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
            <span>💬</span> Tin nhắn
        </a>
            <a href="<?= $base ?>/shipper/notifications.php" class="<?= $currentPage == 'notifications.php' ? 'active' : '' ?>">
                <span>🔔</span> Thông báo
            </a>
        <a href="<?= $base ?>/shipper/profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <span>⚙️</span> Tài khoản
        </a>
        <a href="<?= $base ?>/auth/logout.php">
            <span>🚪</span> Đăng xuất
        </a>
    </nav>
</div>
