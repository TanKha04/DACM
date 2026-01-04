<?php
/**
 * Lịch sử thanh toán của khách hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy lịch sử thanh toán
$stmt = $pdo->prepare("SELECT p.*, o.id as order_id, o.total_amount, o.shipping_fee, s.name as shop_name 
    FROM payments p 
    JOIN orders o ON p.order_id = o.id 
    JOIN shops s ON o.shop_id = s.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC");
$stmt->execute([$userId]);
$payments = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_payments,
    COALESCE(SUM(amount), 0) as total_amount,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount
    FROM payments WHERE user_id = ?");
$stmt->execute([$userId]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử thanh toán - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .payment-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .payment-stat { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .payment-stat .value { font-size: 24px; font-weight: bold; color: #ff6b35; }
        .payment-stat .label { color: #7f8c8d; font-size: 14px; margin-top: 5px; }
        .payment-method { display: inline-flex; align-items: center; gap: 5px; }
        .status-completed { color: #27ae60; }
        .status-pending { color: #f39c12; }
        .status-failed { color: #e74c3c; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">💳 Lịch sử thanh toán</h1>
        
        <div class="payment-stats">
            <div class="payment-stat">
                <div class="value"><?= $stats['total_payments'] ?></div>
                <div class="label">Tổng giao dịch</div>
            </div>
            <div class="payment-stat">
                <div class="value"><?= number_format($stats['total_amount']) ?>đ</div>
                <div class="label">Tổng thanh toán</div>
            </div>
            <div class="payment-stat">
                <div class="value"><?= number_format($stats['completed_amount']) ?>đ</div>
                <div class="label">Đã hoàn thành</div>
            </div>
        </div>
        
        <div class="section">
            <?php if (empty($payments)): ?>
            <div style="text-align: center; padding: 50px;">
                <p style="font-size: 60px;">💳</p>
                <h2>Chưa có giao dịch nào</h2>
                <p style="color: #7f8c8d; margin-top: 10px;">Lịch sử thanh toán sẽ hiển thị ở đây</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Mã GD</th>
                        <th>Đơn hàng</th>
                        <th>Cửa hàng</th>
                        <th>Số tiền</th>
                        <th>Phương thức</th>
                        <th>Trạng thái</th>
                        <th>Ngày</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><strong>#<?= $payment['id'] ?></strong></td>
                        <td><a href="order_detail.php?id=<?= $payment['order_id'] ?>" style="color: #ff6b35;">#<?= $payment['order_id'] ?></a></td>
                        <td><?= htmlspecialchars($payment['shop_name']) ?></td>
                        <td><strong><?= number_format($payment['amount']) ?>đ</strong></td>
                        <td>
                            <span class="payment-method">
                                <?php 
                                switch($payment['method']) {
                                    case 'cash': echo '💵 Tiền mặt'; break;
                                    case 'card': echo '💳 Thẻ'; break;
                                    case 'ewallet': echo '📱 Ví điện tử'; break;
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-<?= $payment['status'] ?>">
                                <?php 
                                switch($payment['status']) {
                                    case 'completed': echo '✓ Hoàn thành'; break;
                                    case 'pending': echo '⏳ Chờ xử lý'; break;
                                    case 'failed': echo '✗ Thất bại'; break;
                                    case 'refunded': echo '↩ Hoàn tiền'; break;
                                }
                                ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
