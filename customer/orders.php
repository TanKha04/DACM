<?php
/**
 * Danh sách đơn hàng của khách
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Xử lý hủy đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = (int)$_POST['order_id'];
    $reason = trim($_POST['cancel_reason'] ?? '');
    
    // Chỉ hủy được khi đơn còn pending
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_by = 'customer', cancel_reason = ? WHERE id = ? AND customer_id = ? AND status = 'pending'");
    $stmt->execute([$reason, $orderId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        $message = 'success:Đã hủy đơn hàng thành công';
    } else {
        $message = 'error:Không thể hủy đơn hàng này';
    }
}

// Lấy danh sách đơn hàng
$status = $_GET['status'] ?? '';
$sql = "SELECT o.*, s.name as shop_name FROM orders o JOIN shops s ON o.shop_id = s.id WHERE o.customer_id = ?";
$params = [$userId];

if ($status && $status !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Lấy thông tin đánh giá cho các đơn đã giao
$reviewedOrders = [];
$stmt = $pdo->prepare("SELECT DISTINCT order_id FROM reviews WHERE user_id = ?");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $reviewedOrders[$row['order_id']] = true;
}

// Đếm theo trạng thái
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE customer_id = ? GROUP BY status");
$stmt->execute([$userId]);
$statusCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .status-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .status-tab { padding: 10px 20px; background: white; border-radius: 25px; text-decoration: none; color: #666; border: 1px solid #ddd; }
        .status-tab.active { background: #ff6b35; color: white; border-color: #ff6b35; }
        .status-tab .count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px; }
        .order-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .order-items { margin-bottom: 15px; }
        .order-item { display: flex; justify-content: space-between; padding: 8px 0; }
        .order-footer { display: flex; justify-content: space-between; align-items: center; }
        .order-total { font-size: 18px; font-weight: bold; color: #ff6b35; }
        .order-actions { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">📦 Đơn hàng của tôi</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="status-tabs">
            <a href="?status=all" class="status-tab <?= !$status || $status === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="?status=pending" class="status-tab <?= $status === 'pending' ? 'active' : '' ?>">Chờ xác nhận <span class="count"><?= $statusCounts['pending'] ?? 0 ?></span></a>
            <a href="?status=confirmed" class="status-tab <?= $status === 'confirmed' ? 'active' : '' ?>">Đã xác nhận</a>
            <a href="?status=preparing" class="status-tab <?= $status === 'preparing' ? 'active' : '' ?>">Đang chuẩn bị</a>
            <a href="?status=delivering" class="status-tab <?= $status === 'delivering' ? 'active' : '' ?>">Đang giao</a>
            <a href="?status=delivered" class="status-tab <?= $status === 'delivered' ? 'active' : '' ?>">Đã giao</a>
            <a href="?status=cancelled" class="status-tab <?= $status === 'cancelled' ? 'active' : '' ?>">Đã hủy</a>
        </div>
        
        <?php if (empty($orders)): ?>
        <div class="section" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">📦</p>
            <h2>Chưa có đơn hàng nào</h2>
            <a href="shops.php" class="btn-primary" style="display: inline-block; text-decoration: none; margin-top: 20px;">Đặt hàng ngay</a>
        </div>
        <?php else: ?>
        
        <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <strong style="font-size: 18px;">#<?= $order['id'] ?></strong>
                    <span style="color: #7f8c8d; margin-left: 15px;">🏪 <?= htmlspecialchars($order['shop_name']) ?></span>
                </div>
                <div>
                    <span class="order-status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                    <span style="color: #7f8c8d; margin-left: 15px;"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
            </div>
            
            <div class="order-footer">
                <div class="order-total">
                    Tổng: <?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ
                </div>
                <div class="order-actions">
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-primary" style="text-decoration: none; padding: 8px 20px;">Xem chi tiết</a>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                    <button onclick="showCancelModal(<?= $order['id'] ?>)" class="btn-danger" style="padding: 8px 20px;">Hủy đơn</button>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'delivered'): ?>
                    <?php if (isset($reviewedOrders[$order['id']])): ?>
                    <span style="padding: 8px 15px; background: #d4edda; color: #155724; border-radius: 5px; font-size: 13px;">✓ Đã đánh giá</span>
                    <?php else: ?>
                    <a href="review.php?order_id=<?= $order['id'] ?>" class="btn-secondary" style="text-decoration: none; padding: 8px 20px; background: #f39c12; color: white; border: none;">⭐ Đánh giá ngay</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal hủy đơn -->
    <div id="cancelModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 20px;">Hủy đơn hàng</h3>
            <form method="POST">
                <input type="hidden" name="order_id" id="cancel_order_id">
                <input type="hidden" name="cancel_order" value="1">
                <div class="form-group">
                    <label>Lý do hủy</label>
                    <textarea name="cancel_reason" rows="3" placeholder="Nhập lý do hủy đơn..."></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeCancelModal()" class="btn-secondary">Đóng</button>
                    <button type="submit" class="btn-danger">Xác nhận hủy</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showCancelModal(orderId) {
        document.getElementById('cancel_order_id').value = orderId;
        document.getElementById('cancelModal').style.display = 'flex';
    }
    function closeCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
    }
    </script>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
