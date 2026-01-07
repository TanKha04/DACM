<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
?>
<style>
/* Override header màu đỏ Tết */
.main-header {
    background: linear-gradient(90deg, #b91c1c, #991b1b) !important;
    border-bottom: 3px solid #fbbf24 !important;
    box-shadow: 0 4px 20px rgba(185,28,28,0.4) !important;
}
.main-header .logo { color: #fef3c7 !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
.main-header .main-nav a { color: #fef3c7 !important; }
.main-header .main-nav a:hover, .main-header .main-nav a.active { 
    background: rgba(251,191,36,0.3) !important; 
    border: 1px solid #fbbf24 !important; 
}
.main-header .logout-btn { 
    background: linear-gradient(135deg, #fbbf24, #f59e0b) !important; 
    color: #7c2d12 !important;
    font-weight: 600 !important;
}
</style>
<header class="main-header">
    <div class="header-container">
        <a href="<?= $base ?>/customer/index.php" class="logo">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 32px; height: 32px; border-radius: 6px; vertical-align: middle; margin-right: 8px;">
            🧧 FastFood
        </a>
        <nav class="main-nav">
            <a href="<?= $base ?>/customer/shops.php" class="<?= $currentPage == 'shops.php' ? 'active' : '' ?>">Cửa hàng</a>
            <a href="<?= $base ?>/customer/shops_map.php" class="<?= $currentPage == 'shops_map.php' ? 'active' : '' ?>">🗺️ Bản đồ</a>
            <a href="<?= $base ?>/customer/cart.php" class="<?= $currentPage == 'cart.php' ? 'active' : '' ?>">🛒 Giỏ hàng</a>
            <a href="<?= $base ?>/customer/orders.php" class="<?= $currentPage == 'orders.php' ? 'active' : '' ?>">Đơn hàng</a>
            <a href="<?= $base ?>/customer/order_history.php" class="<?= $currentPage == 'order_history.php' ? 'active' : '' ?>">📜 Lịch sử</a>
            <a href="<?= $base ?>/customer/messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">💬 Tin nhắn</a>
            <a href="<?= $base ?>/customer/profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">Tài khoản</a>
            <a href="<?= $base ?>/auth/logout.php" class="logout-btn">Đăng xuất</a>
        </nav>
    </div>
</header>
