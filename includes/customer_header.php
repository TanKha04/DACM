<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
?>
<header class="main-header">
    <div class="header-container">
        <a href="<?= $base ?>/home.php" class="logo">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 32px; height: 32px; border-radius: 6px; vertical-align: middle; margin-right: 8px;">
            FastFood
        </a>
        <nav class="main-nav">
            <a href="<?= $base ?>/customer/shops.php" class="<?= $currentPage == 'shops.php' ? 'active' : '' ?>">Cửa hàng</a>
            <a href="<?= $base ?>/customer/cart.php" class="<?= $currentPage == 'cart.php' ? 'active' : '' ?>">🛒 Giỏ hàng</a>
            <a href="<?= $base ?>/customer/orders.php" class="<?= $currentPage == 'orders.php' ? 'active' : '' ?>">Đơn hàng</a>
            <a href="<?= $base ?>/customer/order_history.php" class="<?= $currentPage == 'order_history.php' ? 'active' : '' ?>">📜 Lịch sử</a>
            <a href="<?= $base ?>/customer/messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">💬 Tin nhắn</a>
            <a href="<?= $base ?>/customer/profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">Tài khoản</a>
            <a href="<?= $base ?>/auth/logout.php" class="logout-btn">Đăng xuất</a>
        </nav>
    </div>
</header>
