<?php
/**
 * Seller - Quản lý đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $statusMap = [
        'confirm' => 'confirmed',
        'prepare' => 'preparing',
        'ready' => 'ready',
        'reject' => 'cancelled'
    ];
    if ($orderId && isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND shop_id = ?");
        $stmt->execute([$newStatus, $orderId, $shop['id']]);
        
        // Lấy thông tin đơn hàng để gửi thông báo
        $orderStmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $customerId = $orderStmt->fetchColumn();
        
        // Gửi thông báo cho khách hàng
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
        
        if ($action === 'confirm') {
            // Gửi thông báo cho khách hàng
            $notifStmt->execute([$customerId, 'Đơn hàng đã được xác nhận', "Đơn hàng #$orderId đã được cửa hàng xác nhận."]);
            
            // Gửi thông báo cho tất cả shipper ngay khi xác nhận
            $shipperStmt = $pdo->query("SELECT id FROM users WHERE role = 'shipper' AND status = 'active'");
            $shippers = $shipperStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($shippers as $sid) {
                $notifStmt->execute([$sid, '🚨 Đơn hàng mới cần giao!', "Có đơn hàng #$orderId từ {$shop['name']} cần giao. Vào mục 'Đơn có sẵn' để nhận đơn!"]);
            }
        } elseif ($action === 'prepare') {
            $notifStmt->execute([$customerId, 'Đơn hàng đang được chuẩn bị', "Đơn hàng #$orderId đang được cửa hàng chuẩn bị."]);
        } elseif ($action === 'ready') {
            // Gửi thông báo cho khách
            $notifStmt->execute([$customerId, 'Đơn hàng sẵn sàng giao', "Đơn hàng #$orderId đã sẵn sàng và đang chờ shipper nhận giao."]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE orders SET cancelled_by = 'seller' WHERE id = ?");
            $stmt->execute([$orderId]);
            $notifStmt->execute([$customerId, 'Đơn hàng bị từ chối', "Đơn hàng #$orderId đã bị cửa hàng từ chối. Vui lòng liên hệ hỗ trợ."]);
        }
        
        $message = 'success:Cập nhật trạng thái thành công!';
    }
}

// Lọc theo trạng thái
$status = $_GET['status'] ?? '';
$sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.shop_id = ?";
$params = [$shop['id']];

if ($status && $status !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Đếm theo trạng thái
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE shop_id = ? GROUP BY status");
$stmt->execute([$shop['id']]);
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
    <title>Quản lý đơn hàng - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .status-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .status-tab { padding: 10px 20px; background: white; border-radius: 25px; text-decoration: none; color: #666; }
        .status-tab.active { background: #27ae60; color: white; }
        .status-tab .count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📦 Quản lý đơn hàng</h1>
        </div>
        
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
            <a href="?status=ready" class="status-tab <?= $status === 'ready' ? 'active' : '' ?>">Sẵn sàng</a>
            <a href="?status=delivered" class="status-tab <?= $status === 'delivered' ? 'active' : '' ?>">Đã giao</a>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Địa chỉ giao</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="6" style="text-align: center; color: #999;">Không có đơn hàng</td></tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($order['customer_name']) ?><br>
                            <small>📞 <?= $order['customer_phone'] ?></small>
                        </td>
                        <td style="max-width: 200px;"><?= htmlspecialchars(mb_substr($order['delivery_address'], 0, 50)) ?>...</td>
                        <td><strong><?= number_format($order['total_amount']) ?>đ</strong></td>
                        <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><?= date('H:i d/m', strtotime($order['created_at'])) ?></td>
                        <td>
                            <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary">Chi tiết</a>
                            
                            <?php if ($order['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" name="action" value="confirm" class="btn btn-sm btn-primary">Xác nhận</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" onclick="return confirm('Từ chối đơn này?')">Từ chối</button>
                            </form>
                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                <?php if ($order['shipper_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="action" value="prepare" class="btn btn-sm btn-primary">Bắt đầu chuẩn bị</button>
                                </form>
                                <?php else: ?>
                                <span class="btn btn-sm" style="background: #f0f0f0; color: #999; cursor: not-allowed;" title="Chờ shipper nhận đơn">⏳ Chờ shipper</span>
                                <?php endif; ?>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" name="action" value="ready" class="btn btn-sm btn-primary">Sẵn sàng giao</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
