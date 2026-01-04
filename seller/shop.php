<?php
/**
 * Quản lý thông tin cửa hàng - Seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Lấy thông tin shop của seller
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    $message = 'error:Bạn chưa có cửa hàng. Vui lòng liên hệ admin để được hỗ trợ.';
}

// Xử lý cập nhật thông tin shop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop']) && $shop) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($name) || empty($address)) {
        $message = 'error:Tên và địa chỉ không được để trống!';
    } else {
        $image = $shop['image'];
        
        // Upload hình ảnh mới nếu có
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/shops/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['image']['type'];
            
            if (in_array($fileType, $allowedTypes)) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'shop_' . $shop['id'] . '_' . time() . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    // Xóa ảnh cũ nếu có
                    if ($image && strpos($image, 'uploads/shops/') !== false) {
                        $oldPath = __DIR__ . '/../' . $image;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $image = 'uploads/shops/' . $filename;
                }
            } else {
                $message = 'error:Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)!';
            }
        }
        
        if (strpos($message, 'error') === false) {
            $stmt = $pdo->prepare("UPDATE shops SET name = ?, description = ?, address = ?, phone = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $description, $address, $phone, $image, $shop['id']]);
            
            // Cập nhật lại thông tin shop
            $stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
            $stmt->execute([$shop['id']]);
            $shop = $stmt->fetch();
            
            $message = 'success:Cập nhật thông tin cửa hàng thành công!';
        }
    }
}

// Lấy thống kê cửa hàng
$stats = [];
if ($shop) {
    // Tổng sản phẩm
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE shop_id = ? AND status = 'active'");
    $stmt->execute([$shop['id']]);
    $stats['products'] = $stmt->fetchColumn();
    
    // Tổng đơn hàng
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shop_id = ?");
    $stmt->execute([$shop['id']]);
    $stats['orders'] = $stmt->fetchColumn();
    
    // Đánh giá trung bình
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE shop_id = ?");
    $stmt->execute([$shop['id']]);
    $reviewData = $stmt->fetch();
    $stats['avg_rating'] = round($reviewData['avg_rating'] ?? 0, 1);
    $stats['review_count'] = $reviewData['review_count'] ?? 0;
    
    // Doanh thu tháng này
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE shop_id = ? AND status = 'delivered' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute([$shop['id']]);
    $stats['monthly_revenue'] = $stmt->fetchColumn();
}

$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý cửa hàng - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        .shop-container { display: grid; grid-template-columns: 1fr 350px; gap: 25px; }
        .shop-form { background: white; border-radius: 15px; padding: 25px; }
        .shop-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .shop-preview { background: white; border-radius: 15px; overflow: hidden; }
        .shop-preview-banner { height: 150px; background: linear-gradient(135deg, #ff6b35, #f7931e); display: flex; align-items: center; justify-content: center; }
        .shop-preview-banner img { width: 100%; height: 100%; object-fit: cover; }
        .shop-preview-info { padding: 20px; }
        .shop-preview-info h3 { margin-bottom: 10px; }
        .shop-preview-info p { color: #7f8c8d; font-size: 14px; margin-bottom: 5px; }
        
        .stats-card { background: white; border-radius: 15px; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .stat-item { text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #ff6b35; }
        .stat-label { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #ff6b35; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .image-upload { border: 2px dashed #e0e0e0; border-radius: 10px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .image-upload:hover { border-color: #ff6b35; background: #fff8f5; }
        .image-upload input { display: none; }
        .image-upload .icon { font-size: 40px; margin-bottom: 10px; }
        .image-upload p { color: #7f8c8d; font-size: 14px; }
        .image-preview { max-width: 100%; max-height: 200px; margin-top: 15px; border-radius: 8px; display: none; }
        
        .btn-save { background: #ff6b35; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-save:hover { background: #e55a2b; }
        
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-blocked { background: #f8d7da; color: #721c24; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        @media (max-width: 900px) {
            .shop-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>🏪 Quản lý cửa hàng</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if ($shop): ?>
        <div class="shop-container">
            <div class="shop-form">
                <h2 style="margin-bottom: 20px;">Thông tin cửa hàng</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Tên cửa hàng *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($shop['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea name="description" placeholder="Mô tả về cửa hàng của bạn..."><?= htmlspecialchars($shop['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Địa chỉ *</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($shop['address']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($shop['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Hình ảnh cửa hàng</label>
                        <div class="image-upload" onclick="document.getElementById('shopImage').click()">
                            <input type="file" id="shopImage" name="image" accept="image/*" onchange="previewImage(this)">
                            <div class="icon">📷</div>
                            <p>Click để chọn ảnh mới</p>
                            <img id="imagePreview" class="image-preview" src="" alt="Preview">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_shop" class="btn-save">💾 Lưu thay đổi</button>
                </form>
            </div>
            
            <div class="shop-sidebar">
                <div class="shop-preview">
                    <div class="shop-preview-banner">
                        <?php if ($shop['image']): ?>
                        <img src="<?= $base ?>/<?= htmlspecialchars($shop['image']) ?>" alt="<?= htmlspecialchars($shop['name']) ?>">
                        <?php else: ?>
                        <span style="font-size: 60px;">🏪</span>
                        <?php endif; ?>
                    </div>
                    <div class="shop-preview-info">
                        <h3><?= htmlspecialchars($shop['name']) ?></h3>
                        <p>📍 <?= htmlspecialchars($shop['address']) ?></p>
                        <?php if ($shop['phone']): ?>
                        <p>📞 <?= htmlspecialchars($shop['phone']) ?></p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            Trạng thái: 
                            <span class="status-badge status-<?= $shop['status'] ?>">
                                <?= $shop['status'] === 'active' ? 'Hoạt động' : ($shop['status'] === 'pending' ? 'Chờ duyệt' : 'Bị khóa') ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="stats-card">
                    <h3 style="margin-bottom: 15px;">📊 Thống kê</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['products']) ?></div>
                            <div class="stat-label">Sản phẩm</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['orders']) ?></div>
                            <div class="stat-label">Đơn hàng</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">⭐ <?= $stats['avg_rating'] ?></div>
                            <div class="stat-label"><?= $stats['review_count'] ?> đánh giá</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['monthly_revenue']) ?>đ</div>
                            <div class="stat-label">Doanh thu tháng</div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <h3 style="margin-bottom: 15px;">📅 Thông tin khác</h3>
                    <p style="color: #7f8c8d; font-size: 14px;">
                        <strong>Ngày tạo:</strong> <?= date('d/m/Y', strtotime($shop['created_at'])) ?><br>
                        <strong>Tỷ lệ hoa hồng:</strong> <?= $shop['commission_rate'] ?>%
                    </p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="shop-form" style="text-align: center; padding: 50px;">
            <div style="font-size: 80px; margin-bottom: 20px;">🏪</div>
            <h2>Bạn chưa có cửa hàng</h2>
            <p style="color: #7f8c8d; margin-top: 10px;">Vui lòng liên hệ quản trị viên để được hỗ trợ tạo cửa hàng.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
