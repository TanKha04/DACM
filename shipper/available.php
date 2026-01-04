<?php
/**
 * Shipper - Đơn hàng có sẵn
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Kiểm tra shipper có đang giao đơn nào không (đơn đã lấy hàng hoặc đang giao)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipper_id = ? AND status IN ('picked', 'delivering')");
$stmt->execute([$userId]);
$hasActiveDelivery = $stmt->fetchColumn() > 0;

// Kiểm tra shipper có đơn đang chờ chuẩn bị không
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipper_id = ? AND status IN ('confirmed', 'preparing', 'ready')");
$stmt->execute([$userId]);
$hasWaitingOrder = $stmt->fetchColumn() > 0;

// Nhận đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_order'])) {
    // Kiểm tra shipper có đang giao đơn khác không
    if ($hasActiveDelivery) {
        $message = 'error:Bạn đang có đơn hàng chưa giao xong. Vui lòng hoàn thành đơn hiện tại trước!';
    } elseif ($hasWaitingOrder) {
        $message = 'error:Bạn đã nhận 1 đơn đang chờ chuẩn bị. Vui lòng chờ người bán chuẩn bị xong!';
    } else {
        $orderId = (int)$_POST['order_id'];
        
        // Kiểm tra đơn còn available không (đơn đã xác nhận, đang chuẩn bị hoặc sẵn sàng)
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('confirmed', 'preparing', 'ready') AND shipper_id IS NULL");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Chỉ gán shipper, không đổi status - để người bán bấm "Bắt đầu chuẩn bị"
            $stmt = $pdo->prepare("UPDATE orders SET shipper_id = ? WHERE id = ?");
            $stmt->execute([$userId, $orderId]);
            // Gửi thông báo cho người bán
            $sellerStmt = $pdo->prepare("SELECT s.user_id FROM orders o JOIN shops s ON o.shop_id = s.id WHERE o.id = ?");
            $sellerStmt->execute([$orderId]);
            $sellerId = $sellerStmt->fetchColumn();
            if ($sellerId) {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([$sellerId, '🚚 Shipper đã nhận đơn!', 'Đơn hàng #' . $orderId . ' đã có shipper nhận. Bạn có thể bắt đầu chuẩn bị hàng ngay!', 'order']);
            }
            $message = 'success:Đã nhận đơn thành công! Chờ người bán chuẩn bị hàng.';
            header('Location: my_orders.php');
            exit;
        } else {
            $message = 'error:Đơn hàng đã được nhận bởi shipper khác';
        }
    }
}

// Lấy đơn có sẵn (đơn đã xác nhận, đang chuẩn bị hoặc sẵn sàng)
$stmt = $pdo->query("SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone 
    FROM orders o JOIN shops s ON o.shop_id = s.id 
    WHERE o.status IN ('confirmed', 'preparing', 'ready') AND o.shipper_id IS NULL 
    ORDER BY o.created_at ASC");
$availableOrders = $stmt->fetchAll();

$statusLabels = [
    'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#3498db'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'color' => '#f39c12'],
    'ready' => ['label' => 'Sẵn sàng giao', 'color' => '#27ae60']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn có sẵn - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📦 Đơn hàng có sẵn</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if ($hasActiveDelivery): ?>
        <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; border-radius: 10px; color: #856404;">
            <strong>⚠️ Bạn đang có đơn hàng chưa hoàn thành!</strong><br>
            <p style="margin-top: 10px;">Vui lòng giao xong đơn hiện tại và bấm "Đã giao xong" trước khi nhận đơn mới.</p>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">📦 Xem đơn đang giao</a>
        </div>
        <?php elseif ($hasWaitingOrder): ?>
        <div class="alert" style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 10px; color: #0c5460;">
            <strong>⏳ Bạn đã nhận 1 đơn đang chờ chuẩn bị!</strong><br>
            <p style="margin-top: 10px;">Vui lòng chờ người bán chuẩn bị xong rồi mới nhận đơn mới.</p>
            <a href="my_orders.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">📦 Xem đơn của tôi</a>
        </div>
        <?php elseif (empty($availableOrders)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">📦</p>
            <h2>Không có đơn hàng</h2>
            <p style="color: #7f8c8d; margin-top: 10px;">Hiện tại chưa có đơn hàng nào cần giao</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($availableOrders as $order): ?>
        <div class="card order-available-card" style="box-shadow: 0 8px 32px rgba(52,152,219,0.10); border: 2px solid #eaf6fb;">
            <div class="order-card" style="background: linear-gradient(90deg, #fafdff 60%, #eaf6fb 100%); margin: 0; padding: 32px 28px; border-radius: 18px; box-shadow: 0 2px 8px rgba(52,152,219,0.07);">
                <div class="order-header" style="margin-bottom: 25px;">
                    <span class="order-id" style="font-size: 22px; color: #2980b9; font-weight: bold; letter-spacing: 1px;">#<?= $order['id'] ?></span>
                    <span class="badge" style="font-size: 15px; padding: 7px 18px; background: <?= $statusLabels[$order['status']]['color'] ?>20; color: <?= $statusLabels[$order['status']]['color'] ?>; font-weight: 600;"><?= $statusLabels[$order['status']]['label'] ?></span>
                </div>
                <div class="order-details" style="display: flex; gap: 40px;">
                    <div class="order-detail-item" style="flex:1;">
                        <div class="label" style="font-size: 15px; color: #2980b9; font-weight: 600; margin-bottom: 4px;">🏪 Lấy hàng tại</div>
                        <div class="value" style="font-size: 18px; font-weight: bold; color: #273c75; margin-bottom: 2px;"> <?= htmlspecialchars($order['shop_name']) ?></div>
                        <div style="font-size: 14px; color: #7f8c8d; margin-bottom: 2px;"> <?= htmlspecialchars($order['shop_address']) ?></div>
                        <div style="font-size: 14px; color: #636e72;">📞 <?= $order['shop_phone'] ?></div>
                    </div>
                    <div class="order-detail-item" style="flex:1;">
                        <div class="label" style="font-size: 15px; color: #e17055; font-weight: 600; margin-bottom: 4px;">📍 Giao đến</div>
                        <div class="value" style="font-size: 18px; font-weight: bold; color: #d35400; margin-bottom: 2px;"> <?= htmlspecialchars($order['delivery_name']) ?></div>
                        <div style="font-size: 14px; color: #7f8c8d; margin-bottom: 2px;"> <?= htmlspecialchars($order['delivery_address']) ?></div>
                        <div style="font-size: 14px; color: #636e72;">📞 <?= $order['delivery_phone'] ?></div>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 18px; border-top: 2px dashed #d6eaf8;">
                    <div style="font-size: 18px; color: #636e72;">
                        <span style="color: #7f8c8d; font-size: 16px;">💸 Tiền ship:</span>
                        <strong style="color: #2980b9; font-size: 24px; margin-left: 10px; letter-spacing: 1px;"> <?= number_format($order['shipping_fee']) ?>đ</strong>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" name="accept_order" value="1" class="btn btn-primary" style="font-size: 18px; padding: 14px 32px; border-radius: 10px; font-weight: bold; box-shadow: 0 2px 8px #d6eaf8;">✓ Nhận đơn này</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
