<?php
/**
 * Admin - Quản lý Đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($orderId && $action) {
        if ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_by = 'admin' WHERE id = ?");
            $stmt->execute([$orderId]);
            $message = 'success:Đã hủy đơn hàng';
        } elseif ($action === 'update_status') {
            $newStatus = $_POST['new_status'] ?? '';
            if ($newStatus) {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $orderId]);
                $message = 'success:Đã cập nhật trạng thái';
            }
        }
    }
}

// Lọc
$status = $_GET['status'] ?? '';

$sql = "SELECT o.*, u.name as customer_name, s.name as shop_name, sh.name as shipper_name
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        JOIN shops s ON o.shop_id = s.id 
        LEFT JOIN users sh ON o.shipper_id = sh.id
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY o.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Đếm theo status
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
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
    <title>Quản lý Đơn hàng - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📦 Quản lý đơn hàng</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?status=" class="tab <?= !$status ? 'active' : '' ?>">Tất cả</a>
            <a href="?status=pending" class="tab <?= $status === 'pending' ? 'active' : '' ?>">Chờ xác nhận <span class="count"><?= $statusCounts['pending'] ?? 0 ?></span></a>
            <a href="?status=confirmed" class="tab <?= $status === 'confirmed' ? 'active' : '' ?>">Đã xác nhận</a>
            <a href="?status=preparing" class="tab <?= $status === 'preparing' ? 'active' : '' ?>">Đang chuẩn bị</a>
            <a href="?status=delivering" class="tab <?= $status === 'delivering' ? 'active' : '' ?>">Đang giao</a>
            <a href="?status=delivered" class="tab <?= $status === 'delivered' ? 'active' : '' ?>">Đã giao</a>
            <a href="?status=cancelled" class="tab <?= $status === 'cancelled' ? 'active' : '' ?>">Đã hủy</a>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Cửa hàng</th>
                        <th>Shipper</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= htmlspecialchars($order['shop_name']) ?></td>
                        <td><?= $order['shipper_name'] ? htmlspecialchars($order['shipper_name']) : '-' ?></td>
                        <td><?= number_format($order['total_amount']) ?>đ</td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="update_status">
                                <select name="new_status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; font-size: 12px;">
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                    <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                    <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Đang chuẩn bị</option>
                                    <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Sẵn sàng giao</option>
                                    <option value="delivering" <?= $order['status'] === 'delivering' ? 'selected' : '' ?>>Đang giao</option>
                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Đã giao</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                </select>
                            </form>
                        </td>
                        <td><?= date('d/m H:i', strtotime($order['created_at'])) ?></td>
                        <td>
                            <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm" onclick="return confirm('Hủy đơn này?')">Hủy</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
