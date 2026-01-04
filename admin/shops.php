<?php
/**
 * Admin - Quản lý Shops
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($shopId && $action) {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE shops SET status = 'active' WHERE id = ?");
                $stmt->execute([$shopId]);
                $message = 'success:Đã duyệt cửa hàng';
                break;
            case 'reject':
            case 'block':
                $stmt = $pdo->prepare("UPDATE shops SET status = 'blocked' WHERE id = ?");
                $stmt->execute([$shopId]);
                $message = 'success:Đã khóa cửa hàng';
                break;
            case 'unblock':
                $stmt = $pdo->prepare("UPDATE shops SET status = 'active' WHERE id = ?");
                $stmt->execute([$shopId]);
                $message = 'success:Đã mở khóa cửa hàng';
                break;
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM shops WHERE id = ?");
                $stmt->execute([$shopId]);
                $message = 'success:Đã xóa cửa hàng';
                break;
        }
    }
}

// Lọc
$status = $_GET['status'] ?? '';
$currentUserId = $_SESSION['user_id'];

$sql = "SELECT s.*, u.name as owner_name, u.email as owner_email,
        (SELECT COUNT(*) FROM products WHERE shop_id = s.id) as product_count,
        (SELECT COUNT(*) FROM orders WHERE shop_id = s.id) as order_count
        FROM shops s JOIN users u ON s.user_id = u.id WHERE s.user_id != ?";
$params = [$currentUserId];

if ($status) {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY s.status = 'pending' DESC, s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();

// Đếm theo status (không tính shop của admin đang đăng nhập)
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM shops WHERE user_id != ? GROUP BY status");
$stmt->execute([$currentUserId]);
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
    <title>Quản lý Shops - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🏪 Quản lý cửa hàng</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?status=" class="tab <?= !$status ? 'active' : '' ?>">Tất cả</a>
            <a href="?status=pending" class="tab <?= $status === 'pending' ? 'active' : '' ?>">Chờ duyệt <span class="count"><?= $statusCounts['pending'] ?? 0 ?></span></a>
            <a href="?status=active" class="tab <?= $status === 'active' ? 'active' : '' ?>">Hoạt động <span class="count"><?= $statusCounts['active'] ?? 0 ?></span></a>
            <a href="?status=blocked" class="tab <?= $status === 'blocked' ? 'active' : '' ?>">Đã khóa <span class="count"><?= $statusCounts['blocked'] ?? 0 ?></span></a>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên cửa hàng</th>
                        <th>Chủ sở hữu</th>
                        <th>Địa chỉ</th>
                        <th>Sản phẩm</th>
                        <th>Đơn hàng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $shop): ?>
                    <tr>
                        <td><?= $shop['id'] ?></td>
                        <td>
                            <?php if ($shop['image']): ?>
                            <img src="../<?= htmlspecialchars($shop['image']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 8px; vertical-align: middle;">
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($shop['name']) ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($shop['owner_name']) ?><br>
                            <small style="color: #7f8c8d;"><?= $shop['owner_email'] ?></small>
                        </td>
                        <td style="max-width: 200px;"><?= htmlspecialchars(mb_substr($shop['address'], 0, 50)) ?>...</td>
                        <td><?= $shop['product_count'] ?></td>
                        <td><?= $shop['order_count'] ?></td>
                        <td><span class="badge badge-<?= $shop['status'] ?>"><?= ucfirst($shop['status']) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-info btn-sm" onclick="showShopDetail(<?= htmlspecialchars(json_encode($shop)) ?>)">Chi tiết</button>
                            <?php if ($shop['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">Duyệt</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm">Từ chối</button>
                            </form>
                            <?php elseif ($shop['status'] === 'active'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <input type="hidden" name="action" value="block">
                                <button type="submit" class="btn btn-warning btn-sm">Khóa</button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <input type="hidden" name="action" value="unblock">
                                <button type="submit" class="btn btn-success btn-sm">Mở</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa shop này?')">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Chi tiết cửa hàng -->
    <div id="shopDetailModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 25px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">📋 Chi tiết cửa hàng</h2>
                <button onclick="closeShopDetail()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div id="shopDetailContent"></div>
        </div>
    </div>
    
    <script>
    function showShopDetail(shop) {
        let html = '';
        
        // Ảnh cửa hàng
        html += '<div style="margin-bottom: 20px;">';
        html += '<h4 style="margin-bottom: 10px;">📷 Ảnh cửa hàng</h4>';
        if (shop.image) {
            html += '<img src="../' + shop.image + '" style="max-width: 100%; max-height: 250px; border-radius: 10px; object-fit: cover;">';
        } else {
            html += '<p style="color: #999;">Chưa có ảnh</p>';
        }
        html += '</div>';
        
        // Thông tin cơ bản
        html += '<div style="margin-bottom: 20px;">';
        html += '<h4 style="margin-bottom: 10px;">🏪 Thông tin cơ bản</h4>';
        html += '<table style="width: 100%;">';
        html += '<tr><td style="padding: 8px 0; color: #666;">Tên cửa hàng:</td><td style="padding: 8px 0;"><strong>' + shop.name + '</strong></td></tr>';
        html += '<tr><td style="padding: 8px 0; color: #666;">Số điện thoại:</td><td style="padding: 8px 0;">' + (shop.phone || 'Chưa có') + '</td></tr>';
        html += '<tr><td style="padding: 8px 0; color: #666;">Địa chỉ:</td><td style="padding: 8px 0;">' + shop.address + '</td></tr>';
        html += '<tr><td style="padding: 8px 0; color: #666;">Mô tả:</td><td style="padding: 8px 0;">' + (shop.description || 'Chưa có') + '</td></tr>';
        html += '</table>';
        html += '</div>';
        
        // Vị trí
        html += '<div style="margin-bottom: 20px;">';
        html += '<h4 style="margin-bottom: 10px;">📍 Vị trí cửa hàng</h4>';
        if (shop.latitude && shop.longitude) {
            html += '<p style="margin-bottom: 10px;">Tọa độ: <strong>' + shop.latitude + ', ' + shop.longitude + '</strong></p>';
            html += '<div style="border-radius: 10px; overflow: hidden;">';
            html += '<iframe width="100%" height="200" frameborder="0" style="border:0" src="https://www.openstreetmap.org/export/embed.html?bbox=' + (parseFloat(shop.longitude) - 0.005) + '%2C' + (parseFloat(shop.latitude) - 0.005) + '%2C' + (parseFloat(shop.longitude) + 0.005) + '%2C' + (parseFloat(shop.latitude) + 0.005) + '&layer=mapnik&marker=' + shop.latitude + '%2C' + shop.longitude + '"></iframe>';
            html += '</div>';
        } else {
            html += '<p style="color: #e74c3c;">⚠️ Chưa có thông tin vị trí</p>';
        }
        html += '</div>';
        
        // Giấy an toàn thực phẩm
        html += '<div style="margin-bottom: 20px;">';
        html += '<h4 style="margin-bottom: 10px;">📄 Giấy chứng nhận An toàn thực phẩm</h4>';
        if (shop.food_safety_cert) {
            if (shop.food_safety_cert.endsWith('.pdf')) {
                html += '<a href="../' + shop.food_safety_cert + '" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #333;">';
                html += '<span style="font-size: 24px;">📄</span> Xem file PDF';
                html += '</a>';
            } else {
                html += '<img src="../' + shop.food_safety_cert + '" style="max-width: 100%; max-height: 300px; border-radius: 10px; border: 1px solid #ddd;">';
            }
        } else {
            html += '<p style="color: #e74c3c;">⚠️ Chưa cung cấp giấy chứng nhận</p>';
        }
        html += '</div>';
        
        document.getElementById('shopDetailContent').innerHTML = html;
        document.getElementById('shopDetailModal').style.display = 'flex';
    }
    
    function closeShopDetail() {
        document.getElementById('shopDetailModal').style.display = 'none';
    }
    
    // Đóng modal khi click bên ngoài
    document.getElementById('shopDetailModal').addEventListener('click', function(e) {
        if (e.target === this) closeShopDetail();
    });
    </script>
</body>
</html>
