<?php
/**
 * Seller - Chi tiết đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

// Lấy shop của seller
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Lấy thông tin đơn hàng (chỉ đơn của shop này)
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
                       sh.name as shipper_name, sh.phone as shipper_phone
                       FROM orders o 
                       JOIN users u ON o.customer_id = u.id 
                       LEFT JOIN users sh ON o.shipper_id = sh.id 
                       WHERE o.id = ? AND o.shop_id = ?");
$stmt->execute([$orderId, $shop['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Lấy chi tiết sản phẩm
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Xử lý cập nhật trạng thái
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $statusMap = [
        'confirm' => 'confirmed',
        'prepare' => 'preparing',
        'ready' => 'ready',
        'reject' => 'cancelled'
    ];

    if (isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND shop_id = ?");
        $stmt->execute([$newStatus, $orderId, $shop['id']]);
        
        // Gửi thông báo cho khách hàng
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
        
        if ($action === 'confirm') {
            $notifStmt->execute([$order['customer_id'], 'Đơn hàng đã được xác nhận', "Đơn hàng #$orderId đã được cửa hàng xác nhận."]);
        } elseif ($action === 'prepare') {
            $notifStmt->execute([$order['customer_id'], 'Đơn hàng đang được chuẩn bị', "Đơn hàng #$orderId đang được chuẩn bị."]);
        } elseif ($action === 'ready') {
            $notifStmt->execute([$order['customer_id'], 'Đơn hàng sẵn sàng giao', "Đơn hàng #$orderId đã sẵn sàng giao."]);
            // Thông báo cho shipper
            $shipperStmt = $pdo->query("SELECT id FROM users WHERE role = 'shipper' AND status = 'active'");
            foreach ($shipperStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $notifStmt->execute([$sid, '🚨 Đơn hàng mới!', "Có đơn hàng #$orderId từ {$shop['name']} cần giao."]);
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE orders SET cancelled_by = 'seller' WHERE id = ?");
            $stmt->execute([$orderId]);
            $notifStmt->execute([$order['customer_id'], 'Đơn hàng bị từ chối', "Đơn hàng #$orderId đã bị từ chối."]);
        }
        
        $message = 'success:Cập nhật trạng thái thành công!';
        // Refresh order data
        $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
                               sh.name as shipper_name, sh.phone as shipper_phone
                               FROM orders o 
                               JOIN users u ON o.customer_id = u.id 
                               LEFT JOIN users sh ON o.shipper_id = sh.id 
                               WHERE o.id = ? AND o.shop_id = ?");
        $stmt->execute([$orderId, $shop['id']]);
        $order = $stmt->fetch();
    }
}

$statusLabels = [
    'pending' => ['label' => 'Chờ xác nhận', 'color' => '#f39c12'],
    'confirmed' => ['label' => 'Đã xác nhận', 'color' => '#3498db'],
    'preparing' => ['label' => 'Đang chuẩn bị', 'color' => '#9b59b6'],
    'ready' => ['label' => 'Sẵn sàng giao', 'color' => '#1abc9c'],
    'picked' => ['label' => 'Đã lấy hàng', 'color' => '#e67e22'],
    'delivering' => ['label' => 'Đang giao', 'color' => '#3498db'],
    'delivered' => ['label' => 'Đã giao', 'color' => '#27ae60'],
    'cancelled' => ['label' => 'Đã hủy', 'color' => '#e74c3c']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $orderId ?> - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .order-grid { display: grid; grid-template-columns: 1fr 350px; gap: 25px; }
        .info-card { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
        .status-badge { padding: 8px 16px; border-radius: 20px; color: white; font-weight: 500; display: inline-block; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; }
        @media (max-width: 768px) { .order-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h1>📦 Chi tiết đơn hàng #<?= $orderId ?></h1>
            <a href="orders.php" class="btn btn-secondary">← Quay lại</a>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="order-grid">
            <div>
                <!-- Thông tin sản phẩm -->
                <div class="card">
                    <h3 style="margin-bottom: 15px;">🛒 Sản phẩm đặt hàng</h3>
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
                                <td><strong><?= number_format($item['subtotal']) ?>đ</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Tổng tiền hàng:</strong></td>
                                <td><strong><?= number_format($order['total_amount']) ?>đ</strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;">Phí giao hàng:</td>
                                <td><?= number_format($order['shipping_fee']) ?>đ</td>
                            </tr>
                            <tr style="font-size: 18px; color: #27ae60;">
                                <td colspan="3" style="text-align: right;"><strong>Tổng cộng:</strong></td>
                                <td><strong><?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Thông tin giao hàng -->
                <div class="card">
                    <h3 style="margin-bottom: 15px;">📍 Thông tin giao hàng</h3>
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
                            <span style="color: #e74c3c;"><?= htmlspecialchars($order['note']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <!-- Trạng thái đơn hàng -->
                <div class="card">
                    <h3 style="margin-bottom: 15px;">📊 Trạng thái</h3>
                    <div style="text-align: center; padding: 20px;">
                        <span class="status-badge" style="background: <?= $statusLabels[$order['status']]['color'] ?>;">
                            <?= $statusLabels[$order['status']]['label'] ?>
                        </span>
                        <p style="margin-top: 15px; color: #7f8c8d;">
                            Đặt lúc: <?= date('H:i - d/m/Y', strtotime($order['created_at'])) ?>
                        </p>
                    </div>
                    
                    <!-- Nút hành động -->
                    <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
                    <div class="action-buttons">
                        <form method="POST" style="width: 100%;">
                            <?php if ($order['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="confirm" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">✓ Xác nhận đơn</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Từ chối đơn hàng này?')">✕ Từ chối</button>
                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                <?php if ($order['shipper_id']): ?>
                                <button type="submit" name="action" value="prepare" class="btn btn-primary" style="width: 100%;">👨‍🍳 Bắt đầu chuẩn bị</button>
                                <?php else: ?>
                                <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; color: #856404; text-align: center;">
                                    <strong>⏳ Đang chờ shipper nhận đơn</strong>
                                    <p style="margin: 5px 0 0; font-size: 13px;">Nút "Bắt đầu chuẩn bị" sẽ hiện khi có shipper nhận đơn</p>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                            <button type="submit" name="action" value="ready" class="btn btn-success" style="width: 100%;">📦 Sẵn sàng giao</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Thông tin khách hàng -->
                <div class="card">
                    <h3 style="margin-bottom: 15px;">👤 Khách hàng</h3>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Tên:</span>
                            <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span>SĐT:</span>
                            <strong><?= htmlspecialchars($order['customer_phone']) ?></strong>
                        </div>
                    </div>
                    <?php if (!in_array($order['status'], ['delivered', 'cancelled'])): ?>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <a href="tel:<?= $order['customer_phone'] ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">📞 Gọi</a>
                        <a href="chat_customer.php?order_id=<?= $orderId ?>" class="btn btn-primary" style="flex: 1; text-align: center;">💬 Nhắn tin</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Thông tin shipper -->
                <?php if ($order['shipper_name']): ?>
                <div class="card">
                    <h3 style="margin-bottom: 15px;">🛵 Shipper</h3>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Tên:</span>
                            <strong><?= htmlspecialchars($order['shipper_name']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span>SĐT:</span>
                            <strong><?= htmlspecialchars($order['shipper_phone']) ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Thanh toán -->
                <div class="card">
                    <h3 style="margin-bottom: 15px;">💰 Thanh toán</h3>
                    <div class="info-card">
                        <div class="info-row">
                            <span>Phương thức:</span>
                            <strong><?= $order['payment_method'] === 'cash' ? '💵 Tiền mặt' : ($order['payment_method'] === 'card' ? '💳 Thẻ' : '📱 Ví điện tử') ?></strong>
                        </div>
                        <div class="info-row">
                            <span>Trạng thái:</span>
                            <span class="badge badge-<?= $order['payment_status'] ?>"><?= $order['payment_status'] === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
