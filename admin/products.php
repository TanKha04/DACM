<?php
/**
 * Admin - Quản lý Sản phẩm theo Cửa hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($productId && $action) {
        switch ($action) {
            case 'hide':
                $stmt = $pdo->prepare("UPDATE products SET status = 'hidden' WHERE id = ?");
                $stmt->execute([$productId]);
                $message = 'success:Đã ẩn sản phẩm';
                break;
            case 'show':
                $stmt = $pdo->prepare("UPDATE products SET status = 'active' WHERE id = ?");
                $stmt->execute([$productId]);
                $message = 'success:Đã hiện sản phẩm';
                break;
            case 'delete':
                $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
                $stmt->execute([$productId]);
                $message = 'success:Đã xóa sản phẩm';
                break;
        }
    }
}

// Lấy danh sách shops với số lượng sản phẩm
$stmt = $pdo->query("SELECT s.*, u.name as owner_name,
    (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status != 'deleted') as product_count,
    (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'active') as active_count,
    (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id AND p.status = 'hidden') as hidden_count
    FROM shops s 
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.name");
$shops = $stmt->fetchAll();

// Tổng số sản phẩm
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status != 'deleted'")->fetchColumn();
$activeProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$hiddenProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'hidden'")->fetchColumn();

// Nếu có shop_id thì lấy sản phẩm của shop đó
$selectedShopId = $_GET['shop_id'] ?? null;
$selectedShop = null;
$products = [];

if ($selectedShopId) {
    $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
    $stmt->execute([$selectedShopId]);
    $selectedShop = $stmt->fetch();
    
    if ($selectedShop) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND status != 'deleted' ORDER BY status ASC, created_at DESC");
        $stmt->execute([$selectedShopId]);
        $products = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sản phẩm - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .shop-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.3s; border: 2px solid transparent; }
        .shop-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.12); border-color: #ff6b35; }
        .shop-card.selected { border-color: #ff6b35; background: #fff9f7; }
        .shop-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .shop-avatar { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; background: #f0f0f0; }
        .shop-info h3 { margin: 0 0 5px; font-size: 16px; }
        .shop-info p { margin: 0; color: #7f8c8d; font-size: 13px; }
        .shop-stats { display: flex; gap: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .shop-stat { text-align: center; flex: 1; }
        .shop-stat .value { font-size: 20px; font-weight: bold; color: #2c3e50; }
        .shop-stat .label { font-size: 11px; color: #7f8c8d; }
        
        .products-panel { background: white; border-radius: 12px; padding: 25px; margin-top: 25px; }
        .products-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .product-card { background: #f8f9fa; border-radius: 10px; overflow: hidden; transition: all 0.2s; }
        .product-card:hover { box-shadow: 0 3px 15px rgba(0,0,0,0.1); }
        .product-card.hidden { opacity: 0.6; }
        .product-image { width: 100%; height: 140px; object-fit: cover; }
        .product-body { padding: 12px; }
        .product-name { font-weight: 600; margin-bottom: 5px; font-size: 14px; }
        .product-price { color: #27ae60; font-weight: bold; }
        .product-category { font-size: 11px; color: #7f8c8d; margin-top: 5px; }
        .product-actions { display: flex; gap: 5px; margin-top: 10px; }
        .product-actions button { flex: 1; padding: 6px; font-size: 11px; }
        .product-badge { position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .product-badge.active { background: #27ae60; color: white; }
        .product-badge.hidden { background: #e74c3c; color: white; }
        
        .back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f0f0f0; border-radius: 8px; text-decoration: none; color: #333; margin-bottom: 20px; }
        .back-btn:hover { background: #e0e0e0; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🍕 Quản lý sản phẩm</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Thống kê tổng -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">🏪</div>
                <div class="value"><?= count($shops) ?></div>
                <div class="label">Cửa hàng</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">📦</div>
                <div class="value"><?= $totalProducts ?></div>
                <div class="label">Tổng sản phẩm</div>
            </div>
            <div class="stat-card green">
                <div class="icon">✅</div>
                <div class="value"><?= $activeProducts ?></div>
                <div class="label">Đang bán</div>
            </div>
            <div class="stat-card">
                <div class="icon">🙈</div>
                <div class="value"><?= $hiddenProducts ?></div>
                <div class="label">Đã ẩn</div>
            </div>
        </div>
        
        <?php if ($selectedShop): ?>
        <!-- Hiển thị sản phẩm của shop đã chọn -->
        <a href="products.php" class="back-btn">← Quay lại danh sách cửa hàng</a>
        
        <div class="products-panel">
            <div class="products-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php $shopImg = $selectedShop['image'] ? (strpos($selectedShop['image'], 'http') === 0 ? $selectedShop['image'] : '../' . $selectedShop['image']) : 'https://via.placeholder.com/50'; ?>
                    <img src="<?= $shopImg ?>" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover;">
                    <div>
                        <h2 style="margin: 0;">🏪 <?= htmlspecialchars($selectedShop['name']) ?></h2>
                        <p style="margin: 5px 0 0; color: #7f8c8d;"><?= count($products) ?> sản phẩm</p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 50px; color: #7f8c8d;">
                <p style="font-size: 50px;">📦</p>
                <p>Cửa hàng chưa có sản phẩm nào</p>
            </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): 
                    $productImage = $product['image'] ? (strpos($product['image'], 'http') === 0 ? $product['image'] : '../' . $product['image']) : 'https://via.placeholder.com/200x140';
                ?>
                <div class="product-card <?= $product['status'] ?>" style="position: relative;">
                    <span class="product-badge <?= $product['status'] ?>"><?= $product['status'] === 'active' ? 'Đang bán' : 'Đã ẩn' ?></span>
                    <img src="<?= $productImage ?>" class="product-image">
                    <div class="product-body">
                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="product-price"><?= number_format($product['price']) ?>đ</div>
                        <div class="product-category"><?= htmlspecialchars($product['category'] ?: 'Chưa phân loại') ?></div>
                        <div class="product-actions">
                            <form method="POST" style="display: contents;">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <?php if ($product['status'] === 'active'): ?>
                                <button type="submit" name="action" value="hide" class="btn btn-warning btn-sm">🙈 Ẩn</button>
                                <?php else: ?>
                                <button type="submit" name="action" value="show" class="btn btn-success btn-sm">👁 Hiện</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Xóa sản phẩm này?')">🗑</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Hiển thị danh sách cửa hàng -->
        <h2 style="margin: 25px 0 15px;">📋 Chọn cửa hàng để xem sản phẩm</h2>
        
        <?php if (empty($shops)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🏪</p>
            <h2>Chưa có cửa hàng nào</h2>
        </div>
        <?php else: ?>
        <div class="shop-grid">
            <?php foreach ($shops as $shop): 
                $shopImg = $shop['image'] ? (strpos($shop['image'], 'http') === 0 ? $shop['image'] : '../' . $shop['image']) : 'https://via.placeholder.com/60';
            ?>
            <div class="shop-card" onclick="window.location.href='?shop_id=<?= $shop['id'] ?>'">
                <div class="shop-header">
                    <img src="<?= $shopImg ?>" class="shop-avatar">
                    <div class="shop-info">
                        <h3><?= htmlspecialchars($shop['name']) ?></h3>
                        <p>👤 <?= htmlspecialchars($shop['owner_name']) ?></p>
                    </div>
                </div>
                <div class="shop-stats">
                    <div class="shop-stat">
                        <div class="value"><?= $shop['product_count'] ?></div>
                        <div class="label">Tổng SP</div>
                    </div>
                    <div class="shop-stat">
                        <div class="value" style="color: #27ae60;"><?= $shop['active_count'] ?></div>
                        <div class="label">Đang bán</div>
                    </div>
                    <div class="shop-stat">
                        <div class="value" style="color: #e74c3c;"><?= $shop['hidden_count'] ?></div>
                        <div class="label">Đã ẩn</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
