<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
$linkStyle = "color: #fef3c7; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 15px 25px; border-left: 4px solid transparent; transition: all 0.3s;";
$activeStyle = "color: #fbbf24; background: rgba(251,191,36,0.3); border-left: 4px solid #fbbf24;";
?>
<div class="sidebar" style="position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: linear-gradient(180deg, #dc2626, #b91c1c); color: white; padding: 20px 0; z-index: 100; box-shadow: 4px 0 25px rgba(220,38,38,0.3);">
    <!-- Đèn lồng trang trí -->
    <div style="position: absolute; top: 10px; right: 10px; font-size: 24px; animation: swing 2s ease-in-out infinite;">🏮</div>
    <style>@keyframes swing { 0%, 100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }</style>
    
    <div style="padding: 20px; text-align: center; border-bottom: 2px solid rgba(251,191,36,0.3); margin-bottom: 20px;">
        <a href="<?= $base ?>/home.php" style="text-decoration: none; color: #fef3c7; display: flex; align-items: center; gap: 10px; justify-content: center;">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px; border: 2px solid #fbbf24;">
            <span style="font-size: 18px; font-weight: bold;">🧧 Shipper Panel</span>
        </a>
    </div>
    <nav>
        <a href="<?= $base ?>/shipper/dashboard.php" style="<?= $linkStyle ?> <?= $currentPage == 'dashboard.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">🏮</span> Dashboard
        </a>
        <a href="<?= $base ?>/shipper/available.php" style="<?= $linkStyle ?> <?= $currentPage == 'available.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">🧧</span> Đơn có sẵn
        </a>
        <a href="<?= $base ?>/shipper/my_orders.php" style="<?= $linkStyle ?> <?= $currentPage == 'my_orders.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">🚚</span> Đơn của tôi
        </a>
        <a href="<?= $base ?>/shipper/set_location.php" style="<?= $linkStyle ?> <?= $currentPage == 'set_location.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">📍</span> Vị trí của tôi
        </a>
        <a href="<?= $base ?>/shipper/earnings.php" style="<?= $linkStyle ?> <?= $currentPage == 'earnings.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">💰</span> Thu nhập
        </a>
        <a href="<?= $base ?>/shipper/messages.php" style="<?= $linkStyle ?> <?= $currentPage == 'messages.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">💬</span> Tin nhắn
        </a>
        <a href="<?= $base ?>/shipper/notifications.php" style="<?= $linkStyle ?> <?= $currentPage == 'notifications.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">🔔</span> Thông báo
        </a>
        <a href="<?= $base ?>/shipper/profile.php" style="<?= $linkStyle ?> <?= $currentPage == 'profile.php' ? $activeStyle : '' ?>">
            <span style="font-size: 20px;">👤</span> Tài khoản
        </a>
        <a href="<?= $base ?>/auth/logout.php" style="<?= $linkStyle ?> margin-top: 20px; border-top: 1px solid rgba(251,191,36,0.3); padding-top: 20px;">
            <span style="font-size: 20px;">🚪</span> Đăng xuất
        </a>
    </nav>
</div>
