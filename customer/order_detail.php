<?php
/**
 * Chi tiết đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);
$success = isset($_GET['success']);

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, u.name as shipper_name, u.phone as shipper_phone 
                       FROM orders o 
                       JOIN shops s ON o.shop_id = s.id 
                       LEFT JOIN users u ON o.shipper_id = u.id 
                       WHERE o.id = ? AND o.customer_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Lấy chi tiết sản phẩm
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Lấy thông tin thanh toán
$stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
$stmt->execute([$orderId]);
$payment = $stmt->fetch();

$statusSteps = [
    'pending' => ['label' => 'Chờ xác nhận', 'icon' => '⏳'],
    'confirmed' => ['label' => 'Đã xác nhận', 'icon' => '✓'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'icon' => '👨‍🍳'],
    'ready' => ['label' => 'Sẵn sàng', 'icon' => '📦'],
    'picked' => ['label' => 'Đã lấy hàng', 'icon' => '🛵'],
    'delivering' => ['label' => 'Đang giao', 'icon' => '🚀'],
    'delivered' => ['label' => 'Đã giao', 'icon' => '✅'],
    'cancelled' => ['label' => 'Đã hủy', 'icon' => '❌']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng #<?= $orderId ?> - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .order-detail-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        .status-timeline { display: flex; justify-content: space-between; margin: 30px 0; position: relative; }
        .status-timeline::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #ddd; z-index: 0; }
        .status-step { text-align: center; position: relative; z-index: 1; flex: 1; }
        .status-step .icon { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 18px; }
        .status-step.active .icon { background: #ff6b35; }
        .status-step.completed .icon { background: #28a745; }
        .status-step .label { font-size: 12px; color: #7f8c8d; }
        .info-card { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <?php if ($success): ?>
        <div class="alert alert-success">🎉 Đặt hàng thành công! Cửa hàng sẽ xác nhận đơn hàng của bạn.</div>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>📦 Đơn hàng #<?= $orderId ?></h1>
            <a href="orders.php" class="btn-secondary" style="text-decoration: none;">← Quay lại</a>
        </div>
        
        <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="section">
            <h2>Trạng thái đơn hàng</h2>
            <div class="status-timeline">
                <?php 
                $steps = ['pending', 'confirmed', 'preparing', 'ready', 'delivering', 'delivered'];
                $currentIndex = array_search($order['status'], $steps);
                foreach ($steps as $index => $step): 
                    $isCompleted = $index < $currentIndex;
                    $isActive = $index === $currentIndex;
                ?>
                <div class="status-step <?= $isCompleted ? 'completed' : '' ?> <?= $isActive ? 'active' : '' ?>">
                    <div class="icon"><?= $statusSteps[$step]['icon'] ?></div>
                    <div class="label"><?= $statusSteps[$step]['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-error">
            <strong>Đơn hàng đã bị hủy</strong>
            <?php if ($order['cancel_reason']): ?>
            <br>Lý do: <?= htmlspecialchars($order['cancel_reason']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="order-detail-grid">
            <div>
                <div class="section">
                    <h2>🛒 Sản phẩm đã đặt</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Đơn giá</th>
                                <th>SL</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= number_format($item['price']) ?>đ</td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['subtotal']) ?>đ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <h2>📍 Thông tin giao hàng</h2>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Người nhận:</span>
                            <strong><?= htmlspecialchars($order['delivery_name']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Số điện thoại:</span>
                            <strong><?= htmlspecialchars($order['delivery_phone']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Địa chỉ:</span>
                            <strong><?= htmlspecialchars($order['delivery_address']) ?></strong>
                        </div>
                        <?php if ($order['note']): ?>
                        <div class="info-row">
                            <span>Ghi chú:</span>
                            <span><?= htmlspecialchars($order['note']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($order['shipper_name']): ?>
                <div class="section">
                    <h2>🛵 Thông tin shipper</h2>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Tên:</span>
                            <strong><?= htmlspecialchars($order['shipper_name']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Số điện thoại:</span>
                            <strong><?= htmlspecialchars($order['shipper_phone']) ?></strong>
                        </div>
                    </div>
                    <?php if (in_array($order['status'], ['picked', 'delivering'])): ?>
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <a href="tel:<?= $order['shipper_phone'] ?>" class="btn-secondary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px;">📞 Gọi điện</a>
                        <a href="chat_shipper.php?order_id=<?= $orderId ?>" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px;">💬 Nhắn tin</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <div class="section">
                    <h2>🏪 Cửa hàng</h2>
                    <div class="info-card">
                        <strong><?= htmlspecialchars($order['shop_name']) ?></strong>
                        <p style="color: #7f8c8d; margin-top: 5px;"><?= htmlspecialchars($order['shop_address']) ?></p>
                        <p style="margin-top: 5px;">📞 <?= htmlspecialchars($order['shop_phone']) ?></p>
                    </div>
                    <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <a href="tel:<?= $order['shop_phone'] ?>" class="btn-secondary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px;">📞 Gọi shop</a>
                        <a href="chat_shop.php?order_id=<?= $orderId ?>" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px;">💬 Nhắn shop</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="section">
                    <h2>💰 Thanh toán</h2>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Tạm tính:</span>
                            <span><?= number_format($order['total_amount']) ?>đ</span>
                        </div>
                        <div class="info-row">
                            <span>Phí giao hàng:</span>
                            <span><?= number_format($order['shipping_fee']) ?>đ</span>
                        </div>
                        <div class="info-row" style="font-size: 18px; font-weight: bold; color: #ff6b35;">
                            <span>Tổng cộng:</span>
                            <span><?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ</span>
                        </div>
                        <div class="info-row">
                            <span>Phương thức:</span>
                            <span><?= $order['payment_method'] === 'cash' ? '💵 Tiền mặt' : ($order['payment_method'] === 'card' ? '💳 Thẻ' : '📱 Ví điện tử') ?></span>
                        </div>
                        <div class="info-row">
                            <span>Trạng thái:</span>
                            <span class="order-status status-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($order['status'] === 'delivered'): 
                    // Kiểm tra đã đánh giá chưa
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$orderId, $userId]);
                    $hasReviewed = $stmt->fetchColumn() > 0;
                ?>
                <?php if ($hasReviewed): ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 10px; text-align: center;">
                    <span style="color: #155724; font-weight: bold;">✓ Bạn đã đánh giá đơn hàng này</span>
                    <a href="review.php?order_id=<?= $orderId ?>" style="display: block; margin-top: 10px; color: #155724;">Xem đánh giá →</a>
                </div>
                <?php else: ?>
                <a href="review.php?order_id=<?= $orderId ?>" class="btn-primary" style="display: block; text-align: center; text-decoration: none; padding: 15px; background: linear-gradient(135deg, #f39c12, #e67e22);">
                    ⭐ Đánh giá đơn hàng
                </a>
                <p style="text-align: center; color: #7f8c8d; font-size: 13px; margin-top: 10px;">Đánh giá giúp cải thiện chất lượng dịch vụ!</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
    <script>
    // Auto refresh trạng thái đơn hàng mỗi 5 giây
    let currentStatus = '<?= $order['status'] ?>';
    let currentShipper = <?= $order['shipper_id'] ? 'true' : 'false' ?>;
    
    async function checkOrderStatus() {
        try {
            const response = await fetch('<?= "order_status.php?id=" . $orderId ?>');
            const data = await response.json();
            
            if (data.status !== currentStatus || data.has_shipper !== currentShipper) {
                // Reload trang khi có thay đổi
                location.reload();
            }
        } catch (e) {
            console.log('Lỗi kiểm tra trạng thái');
        }
    }
    
    // Kiểm tra mỗi 5 giây
    setInterval(checkOrderStatus, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
