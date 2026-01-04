<?php
/**
 * Admin - Quản lý Users
 * Bao gồm cấp quyền Seller và Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($userId && $action) {
        switch ($action) {
            case 'block':
                $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ? AND id != ?");
                $stmt->execute([$userId, $_SESSION['user_id']]);
                $message = 'success:Đã khóa tài khoản';
                break;
                
            case 'unblock':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'success:Đã mở khóa tài khoản';
                break;
                
            case 'delete':
                try {
                    $pdo->beginTransaction();
                    
                    // Kiểm tra user có shop không
                    $stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $shopIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($shopIds)) {
                        // Xóa các bản ghi liên quan đến orders của shop
                        $placeholders = implode(',', array_fill(0, count($shopIds), '?'));
                        
                        // Lấy order_ids của shop
                        $stmt = $pdo->prepare("SELECT id FROM orders WHERE shop_id IN ($placeholders)");
                        $stmt->execute($shopIds);
                        $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($orderIds)) {
                            $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
                            
                            // Xóa order_items
                            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa order_messages
                            $stmt = $pdo->prepare("DELETE FROM order_messages WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa payments
                            $stmt = $pdo->prepare("DELETE FROM payments WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa reviews
                            $stmt = $pdo->prepare("DELETE FROM reviews WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa voucher_usage
                            $stmt = $pdo->prepare("DELETE FROM voucher_usage WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa promotion_usage
                            $stmt = $pdo->prepare("DELETE FROM promotion_usage WHERE order_id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                            
                            // Xóa orders
                            $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($orderPlaceholders)");
                            $stmt->execute($orderIds);
                        }
                        
                        // Xóa products của shop
                        $stmt = $pdo->prepare("DELETE FROM products WHERE shop_id IN ($placeholders)");
                        $stmt->execute($shopIds);
                        
                        // Xóa promotions của shop
                        $stmt = $pdo->prepare("DELETE FROM promotions WHERE shop_id IN ($placeholders)");
                        $stmt->execute($shopIds);
                        
                        // Xóa shops
                        $stmt = $pdo->prepare("DELETE FROM shops WHERE user_id = ?");
                        $stmt->execute([$userId]);
                    }
                    
                    // Xóa shipper_info nếu có
                    $stmt = $pdo->prepare("DELETE FROM shipper_info WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Xóa user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                    $stmt->execute([$userId, $_SESSION['user_id']]);
                    
                    $pdo->commit();
                    $message = 'success:Đã xóa tài khoản và tất cả dữ liệu liên quan';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'error:Không thể xóa tài khoản: ' . $e->getMessage();
                }
                break;
                
            case 'set_admin':
                $isAdmin = (int)($_POST['is_admin'] ?? 0);
                $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                $stmt->execute([$isAdmin, $userId]);
                $message = 'success:Đã cập nhật quyền admin';
                break;
                
            // CẤP QUYỀN SELLER
            case 'grant_seller':
                $stmt = $pdo->prepare("UPDATE users SET role = 'seller' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'success:Đã cấp quyền Người bán cho user này. User sẽ vào trang Seller khi đăng nhập.';
                break;
                
            // CẤP QUYỀN SHIPPER
            case 'grant_shipper':
                // Cập nhật role
                $stmt = $pdo->prepare("UPDATE users SET role = 'shipper' WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Tạo shipper_info nếu chưa có
                $stmt = $pdo->prepare("INSERT IGNORE INTO shipper_info (user_id) VALUES (?)");
                $stmt->execute([$userId]);
                
                $message = 'success:Đã cấp quyền Shipper cho user này. User sẽ vào trang Shipper khi đăng nhập.';
                break;
                
            // ĐẶT LẠI THÀNH CUSTOMER
            case 'set_customer':
                $stmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'success:Đã chuyển user về Người mua';
                break;
        }
    }
}

// Lọc
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role) {
    $sql .= " AND role = ?";
    $params[] = $role;
}
if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}
if ($search) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Đếm theo role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$roleCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $roleCounts[$row['role']] = $row['count'];
}

// Đếm admin
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
$adminCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .role-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .role-btn { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; white-space: nowrap; }
        .role-btn.seller { background: #27ae60; color: white; }
        .role-btn.shipper { background: #3498db; color: white; }
        .role-btn.customer { background: #95a5a6; color: white; }
        .current-role { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; margin-bottom: 5px; }
        .role-customer { background: #ecf0f1; color: #7f8c8d; }
        .role-seller { background: #d5f5e3; color: #27ae60; }
        .role-shipper { background: #d6eaf8; color: #3498db; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>👤 Quản lý người dùng</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?role=" class="tab <?= !$role ? 'active' : '' ?>">Tất cả</a>
            <a href="?role=customer" class="tab <?= $role === 'customer' ? 'active' : '' ?>">Người mua <span class="count"><?= $roleCounts['customer'] ?? 0 ?></span></a>
            <a href="?role=seller" class="tab <?= $role === 'seller' ? 'active' : '' ?>">Người bán <span class="count"><?= $roleCounts['seller'] ?? 0 ?></span></a>
            <a href="?role=shipper" class="tab <?= $role === 'shipper' ? 'active' : '' ?>">Shipper <span class="count"><?= $roleCounts['shipper'] ?? 0 ?></span></a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên, email, SĐT..." style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; width: 250px;">
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                </form>
                <div style="color: #7f8c8d; font-size: 14px;">
                    👑 Admin: <?= $adminCount ?>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thông tin</th>
                        <th>Vai trò hiện tại</th>
                        <th>Cấp quyền</th>
                        <th>Admin</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong><br>
                            <small style="color: #7f8c8d;"><?= htmlspecialchars($user['email']) ?></small>
                            <?php if ($user['phone']): ?>
                            <br><small>📞 <?= htmlspecialchars($user['phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="current-role role-<?= $user['role'] ?>">
                                <?php 
                                switch($user['role']) {
                                    case 'customer': echo '🛒 Người mua'; break;
                                    case 'seller': echo '🏪 Người bán'; break;
                                    case 'shipper': echo '🛵 Shipper'; break;
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="role-actions">
                                <?php if ($user['role'] !== 'seller'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="grant_seller" class="role-btn seller" onclick="return confirm('Cấp quyền Người bán cho user này?')">
                                        🏪 Cấp quyền Bán
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] !== 'shipper'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="grant_shipper" class="role-btn shipper" onclick="return confirm('Cấp quyền Shipper cho user này?')">
                                        🛵 Cấp quyền Ship
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] !== 'customer'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="set_customer" class="role-btn customer" onclick="return confirm('Chuyển user về Người mua?')">
                                        ↩ Về Người mua
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="action" value="set_admin">
                                <input type="hidden" name="is_admin" value="<?= $user['is_admin'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-sm <?= $user['is_admin'] ? 'btn-danger' : 'btn-secondary' ?>" onclick="return confirm('<?= $user['is_admin'] ? 'Hủy quyền admin?' : 'Cấp quyền admin?' ?>')">
                                    <?= $user['is_admin'] ? '👑 Admin' : 'Cấp Admin' ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="badge badge-admin">👑 Bạn</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $user['status'] ?>">
                                <?= $user['status'] === 'active' ? 'Hoạt động' : ($user['status'] === 'blocked' ? 'Đã khóa' : 'Chờ duyệt') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <?php if ($user['status'] === 'active'): ?>
                                <button type="submit" name="action" value="block" class="btn btn-warning btn-sm">Khóa</button>
                                <?php else: ?>
                                <button type="submit" name="action" value="unblock" class="btn btn-success btn-sm">Mở</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Xóa user này vĩnh viễn?')">Xóa</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 15px;">📋 Hướng dẫn cấp quyền</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div style="background: #d5f5e3; padding: 15px; border-radius: 10px;">
                    <h4 style="color: #27ae60;">🏪 Cấp quyền Người bán</h4>
                    <p style="font-size: 13px; color: #555; margin-top: 10px;">
                        Khi cấp quyền, user sẽ vào trang <strong>Seller Dashboard</strong> khi đăng nhập. 
                        User có thể tạo cửa hàng và bán sản phẩm.
                    </p>
                </div>
                <div style="background: #d6eaf8; padding: 15px; border-radius: 10px;">
                    <h4 style="color: #3498db;">🛵 Cấp quyền Shipper</h4>
                    <p style="font-size: 13px; color: #555; margin-top: 10px;">
                        Khi cấp quyền, user sẽ vào trang <strong>Shipper Dashboard</strong> khi đăng nhập. 
                        User có thể nhận và giao đơn hàng.
                    </p>
                </div>
                <div style="background: #ecf0f1; padding: 15px; border-radius: 10px;">
                    <h4 style="color: #7f8c8d;">🛒 Người mua (mặc định)</h4>
                    <p style="font-size: 13px; color: #555; margin-top: 10px;">
                        Vai trò mặc định khi đăng ký. User vào trang <strong>Customer</strong> để đặt món ăn.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
