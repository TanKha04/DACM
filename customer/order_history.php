<?php
/**
 * Lịch sử mua hàng - Customer
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Xử lý đặt lại đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder'])) {
    $orderId = (int)$_POST['order_id'];
    
    // Lấy thông tin đơn hàng cũ
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $userId]);
    $oldOrder = $stmt->fetch();
    
    if ($oldOrder) {
        // Lấy các sản phẩm trong đơn hàng cũ
        $stmt = $pdo->prepare("SELECT oi.*, p.status as product_status, p.shop_id 
                               FROM order_items oi 
                               JOIN products p ON oi.product_id = p.id 
                               WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
        
        $addedCount = 0;
        $unavailableItems = [];
        
        foreach ($items as $item) {
            // Kiểm tra sản phẩm còn active không
            if ($item['product_status'] === 'active') {
                // Thêm vào giỏ hàng
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) 
                                       VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                $stmt->execute([$userId, $item['product_id'], $item['quantity'], $item['quantity']]);
                $addedCount++;
            } else {
                $unavailableItems[] = $item['product_name'];
            }
        }
        
        if ($addedCount > 0) {
            if (empty($unavailableItems)) {
                $message = 'success:Đã thêm ' . $addedCount . ' sản phẩm vào giỏ hàng!';
            } else {
                $message = 'warning:Đã thêm ' . $addedCount . ' sản phẩm. Một số sản phẩm không còn bán: ' . implode(', ', $unavailableItems);
            }
        } else {
            $message = 'error:Không thể đặt lại đơn hàng này. Các sản phẩm đã ngừng bán.';
        }
    }
}

// Lọc theo thời gian
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, s.name as shop_name, s.image as shop_image,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id 
        WHERE o.customer_id = ? AND o.status = 'delivered'";
$params = [$userId];

// Lọc theo thời gian
switch ($filter) {
    case 'week':
        $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '3months':
        $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
}

// Tìm kiếm
if ($search) {
    $sql .= " AND (s.name LIKE ? OR o.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent 
                       FROM orders WHERE customer_id = ? AND status = 'delivered'");
$stmt->execute([$userId]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử mua hàng - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .history-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .history-stat { background: linear-gradient(135deg, #ff6b35, #ff4d4d); color: white; padding: 25px; border-radius: 15px; text-align: center; }
        .history-stat .value { font-size: 32px; font-weight: bold; }
        .history-stat .label { opacity: 0.9; margin-top: 5px; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab { padding: 10px 20px; border: 1px solid #ddd; border-radius: 25px; text-decoration: none; color: #666; background: white; }
        .filter-tab.active { background: #ff6b35; color: white; border-color: #ff6b35; }
        .order-history-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .order-history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .shop-info { display: flex; align-items: center; gap: 12px; }
        .shop-info img { width: 50px; height: 50px; border-radius: 10px; object-fit: cover; }
        .shop-info h3 { margin: 0; font-size: 16px; }
        .shop-info .date { color: #999; font-size: 13px; }
        .order-items-preview { display: flex; gap: 10px; margin-bottom: 15px; overflow-x: auto; padding: 5px 0; }
        .item-thumb { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 1px solid #eee; }
        .item-more { width: 60px; height: 60px; border-radius: 8px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; color: #666; font-size: 14px; }
        .order-history-footer { display: flex; justify-content: space-between; align-items: center; }
        .order-total { font-size: 18px; font-weight: bold; color: #ff6b35; }
        .order-actions { display: flex; gap: 10px; }
        .btn-reorder { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-reorder:hover { background: #219a52; }
        .btn-detail { background: #f5f5f5; color: #333; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; text-decoration: none; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">📜 Lịch sử mua hàng</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>" style="padding: 15px; border-radius: 10px; margin-bottom: 20px; background: <?= $parts[0] === 'success' ? '#d4edda' : ($parts[0] === 'warning' ? '#fff3cd' : '#f8d7da') ?>; color: <?= $parts[0] === 'success' ? '#155724' : ($parts[0] === 'warning' ? '#856404' : '#721c24') ?>;">
            <?= htmlspecialchars($parts[1]) ?>
            <?php if ($parts[0] === 'success' || $parts[0] === 'warning'): ?>
            <a href="cart.php" style="margin-left: 10px; font-weight: bold;">Xem giỏ hàng →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Thống kê -->
        <div class="history-stats">
            <div class="history-stat">
                <div class="value"><?= $stats['total_orders'] ?></div>
                <div class="label">Đơn hàng đã hoàn thành</div>
            </div>
            <div class="history-stat">
                <div class="value"><?= number_format($stats['total_spent']) ?>đ</div>
                <div class="label">Tổng chi tiêu</div>
            </div>
        </div>
        
        <!-- Bộ lọc -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="?filter=week" class="filter-tab <?= $filter === 'week' ? 'active' : '' ?>">7 ngày qua</a>
            <a href="?filter=month" class="filter-tab <?= $filter === 'month' ? 'active' : '' ?>">30 ngày qua</a>
            <a href="?filter=3months" class="filter-tab <?= $filter === '3months' ? 'active' : '' ?>">3 tháng qua</a>
        </div>
        
        <!-- Tìm kiếm -->
        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên cửa hàng hoặc mã đơn..." style="flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 10px;">
                <button type="submit" class="btn-primary" style="padding: 12px 25px; border: none; border-radius: 10px; background: #ff6b35; color: white; cursor: pointer;">Tìm kiếm</button>
            </div>
        </form>
        
        <!-- Danh sách đơn hàng -->
        <?php if (empty($orders)): ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 15px;">
            <p style="font-size: 50px; margin-bottom: 15px;">📦</p>
            <h3>Chưa có lịch sử mua hàng</h3>
            <p style="color: #999;">Hãy đặt đơn hàng đầu tiên của bạn!</p>
            <a href="shops.php" class="btn-primary" style="display: inline-block; margin-top: 15px; padding: 12px 25px; background: #ff6b35; color: white; text-decoration: none; border-radius: 10px;">Xem cửa hàng</a>
        </div>
        <?php else: ?>
        
        <?php foreach ($orders as $order): 
            // Lấy sản phẩm trong đơn
            $stmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? LIMIT 4");
            $stmt->execute([$order['id']]);
            $items = $stmt->fetchAll();
            $shopImage = $order['shop_image'] ? '../' . $order['shop_image'] : 'https://via.placeholder.com/50';
        ?>
        <div class="order-history-card">
            <div class="order-history-header">
                <div class="shop-info">
                    <img src="<?= $shopImage ?>" alt="<?= htmlspecialchars($order['shop_name']) ?>">
                    <div>
                        <h3><?= htmlspecialchars($order['shop_name']) ?></h3>
                        <div class="date">Đơn #<?= $order['id'] ?> • <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                    </div>
                </div>
                <span style="background: #d4edda; color: #155724; padding: 5px 12px; border-radius: 20px; font-size: 13px;">✓ Đã giao</span>
            </div>
            
            <div class="order-items-preview">
                <?php foreach ($items as $item): 
                    $itemImage = $item['image'] ? '../' . $item['image'] : 'https://via.placeholder.com/60';
                ?>
                <img src="<?= $itemImage ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="item-thumb" title="<?= htmlspecialchars($item['product_name']) ?> x<?= $item['quantity'] ?>">
                <?php endforeach; ?>
                <?php if ($order['item_count'] > 4): ?>
                <div class="item-more">+<?= $order['item_count'] - 4 ?></div>
                <?php endif; ?>
            </div>
            
            <div class="order-history-footer">
                <div>
                    <span style="color: #666;"><?= $order['item_count'] ?> sản phẩm</span>
                    <span class="order-total" style="margin-left: 15px;"><?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ</span>
                </div>
                <div class="order-actions">
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-detail">Chi tiết</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" name="reorder" class="btn-reorder">🔄 Đặt lại</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
