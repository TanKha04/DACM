<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
$searchQuery = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
?>
<style>
/* Override header màu đỏ Tết */
.main-header {
    background: linear-gradient(90deg, #b91c1c, #991b1b) !important;
    border-bottom: 3px solid #fbbf24 !important;
    box-shadow: 0 4px 20px rgba(185,28,28,0.4) !important;
}
.main-header .logo { color: #fef3c7 !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
.main-header .main-nav { 
    display: flex !important; 
    flex-wrap: nowrap !important; 
    gap: 8px !important; 
    align-items: center !important;
}
.main-header .main-nav a { 
    color: #fef3c7 !important; 
    white-space: nowrap !important;
    padding: 8px 10px !important;
    font-size: 13px !important;
}
.main-header .main-nav a:hover, .main-header .main-nav a.active { 
    background: rgba(251,191,36,0.3) !important; 
    border: 1px solid #fbbf24 !important; 
}
.main-header .logout-btn { 
    background: linear-gradient(135deg, #fbbf24, #f59e0b) !important; 
    color: #7c2d12 !important;
    font-weight: 600 !important;
}
/* Search box styles */
.header-search {
    position: relative;
    margin: 0 15px;
}
.header-search input {
    width: 220px;
    padding: 8px 35px 8px 12px;
    border: 2px solid rgba(251,191,36,0.5);
    border-radius: 20px;
    background: rgba(255,255,255,0.15);
    color: #fef3c7;
    font-size: 13px;
    outline: none;
    transition: all 0.3s;
}
.header-search input::placeholder {
    color: rgba(254,243,199,0.7);
}
.header-search input:focus {
    width: 280px;
    background: rgba(255,255,255,0.25);
    border-color: #fbbf24;
}
.header-search button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #fbbf24;
    cursor: pointer;
    font-size: 14px;
    padding: 5px;
}
.header-search button:hover {
    color: #fef3c7;
}
</style>
<header class="main-header">
    <div class="header-container">
        <a href="<?= $base ?>/customer/index.php" class="logo">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 32px; height: 32px; border-radius: 6px; vertical-align: middle; margin-right: 8px;">
            🧧 FastFood
        </a>
        
        <!-- Search Box -->
        <form action="<?= $base ?>/customer/search.php" method="GET" class="header-search">
            <input type="text" name="q" placeholder="Tìm món ăn, cửa hàng..." value="<?= $searchQuery ?>" autocomplete="off">
            <button type="submit">🔍</button>
        </form>
        
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
