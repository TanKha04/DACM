<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$base = getBaseUrl();
?>
<style>
.sidebar { 
    background: linear-gradient(180deg, #ff6b35 0%, #f7931e 100%) !important; 
}
.sidebar-header {
    border-bottom: 1px solid rgba(255,255,255,0.2) !important;
}
.sidebar-nav a { 
    font-size: 17px !important; 
    padding: 16px 25px !important; 
    color: rgba(255,255,255,0.9) !important;
    transition: all 0.3s !important;
}
.sidebar-nav a:hover, .sidebar-nav a.active { 
    background: rgba(255,255,255,0.2) !important; 
    color: white !important;
}
.sidebar-nav a span { font-size: 20px !important; }
.sidebar-nav .divider { 
    border-top: 1px solid rgba(255,255,255,0.2) !important; 
}
</style>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $base ?>/admin/dashboard.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <img src="<?= $base ?>/logo.png" alt="Logo" style="width: 40px; height: 40px; border-radius: 8px;">
            <span style="font-size: 20px; font-weight: bold;">FastFood Admin</span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= $base ?>/admin/users.php" class="<?= $currentPage == 'users.php' ? 'active' : '' ?>">
            <span>👤</span> Quản lý Users
        </a>
        <a href="<?= $base ?>/admin/shops.php" class="<?= $currentPage == 'shops.php' ? 'active' : '' ?>">
            <span>🏪</span> Quản lý Shops
        </a>
        <a href="<?= $base ?>/admin/orders.php" class="<?= $currentPage == 'orders.php' ? 'active' : '' ?>">
            <span>📦</span> Quản lý Đơn hàng
        </a>
        <a href="<?= $base ?>/admin/products.php" class="<?= $currentPage == 'products.php' ? 'active' : '' ?>">
            <span>🍕</span> Quản lý Sản phẩm
        </a>
        <a href="<?= $base ?>/admin/categories.php" class="<?= $currentPage == 'categories.php' ? 'active' : '' ?>">
            <span>🏷️</span> Danh mục món
        </a>
        <div class="divider"></div>
        <a href="<?= $base ?>/admin/reviews.php" class="<?= $currentPage == 'reviews.php' ? 'active' : '' ?>">
            <span>💬</span> Quản lý Đánh giá
        </a>
        <a href="<?= $base ?>/admin/vouchers.php" class="<?= $currentPage == 'vouchers.php' ? 'active' : '' ?>">
            <span>🎫</span> Voucher & KM
        </a>
        <a href="<?= $base ?>/admin/finance.php" class="<?= $currentPage == 'finance.php' ? 'active' : '' ?>">
            <span>💰</span> Tài chính
        </a>
        <a href="<?= $base ?>/admin/support.php" class="<?= $currentPage == 'support.php' ? 'active' : '' ?>">
            <span>🎧</span> Hỗ trợ
        </a>
        <a href="<?= $base ?>/admin/messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
            <span>💬</span> Tin nhắn
        </a>
        <a href="<?= $base ?>/admin/notifications.php" class="<?= $currentPage == 'notifications.php' ? 'active' : '' ?>">
            <span>🔔</span> Thông báo
        </a>
        <a href="<?= $base ?>/admin/settings.php" class="<?= $currentPage == 'settings.php' ? 'active' : '' ?>">
            <span>⚙️</span> Cấu hình
        </a>
        <div class="divider"></div>
        <a href="<?= $base ?>/auth/logout.php">
            <span>🚪</span> Đăng xuất
        </a>
    </nav>
</div>
