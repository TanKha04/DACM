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
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .shop-container { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }
        .shop-form { background: white; border-radius: 20px; padding: 35px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .shop-form h2 { font-size: 22px; color: #1f2937; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f3f4f6; }
        .shop-sidebar { display: flex; flex-direction: column; gap: 25px; }
        .shop-preview { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .shop-preview-banner { height: 180px; background: linear-gradient(135deg, #059669, #10b981); display: flex; align-items: center; justify-content: center; }
        .shop-preview-banner img { width: 100%; height: 100%; object-fit: cover; }
        .shop-preview-info { padding: 25px; }
        .shop-preview-info h3 { margin-bottom: 15px; font-size: 22px; color: #1f2937; }
        .shop-preview-info p { color: #6b7280; font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        
        .stats-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .stats-card h3 { font-size: 18px; color: #1f2937; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .stat-item { text-align: center; padding: 20px 15px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 15px; transition: all 0.3s; }
        .stat-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(5,150,105,0.15); }
        .stat-value { font-size: 28px; font-weight: 700; color: #059669; }
        .stat-label { font-size: 13px; color: #6b7280; margin-top: 8px; font-weight: 500; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #374151; font-size: 15px; }
        .form-group input, .form-group textarea { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid #e5e7eb; 
            border-radius: 12px; 
            font-size: 15px; 
            box-sizing: border-box; 
            transition: all 0.3s;
            background: #f9fafb;
        }
        .form-group input:focus, .form-group textarea:focus { 
            outline: none; 
            border-color: #059669; 
            background: white;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        
        .image-upload { 
            border: 2px dashed #d1d5db; 
            border-radius: 15px; 
            padding: 40px; 
            text-align: center; 
            cursor: pointer; 
            transition: all 0.3s; 
            background: #f9fafb;
        }
        .image-upload:hover { border-color: #059669; background: #f0fdf4; }
        .image-upload input { display: none; }
        .image-upload .icon { font-size: 50px; margin-bottom: 15px; }
        .image-upload p { color: #6b7280; font-size: 15px; }
        .image-preview { max-width: 100%; max-height: 200px; margin-top: 20px; border-radius: 12px; display: none; }
        
        .btn-save { 
            background: linear-gradient(135deg, #059669, #047857); 
            color: white; 
            border: none; 
            padding: 16px 35px; 
            border-radius: 12px; 
            font-size: 16px; 
            font-weight: 600;
            cursor: pointer; 
            width: 100%; 
            margin-top: 15px; 
            box-shadow: 0 4px 15px rgba(5,150,105,0.3);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,0.4); }
        
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 25px; font-size: 13px; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-blocked { background: #fee2e2; color: #991b1b; }
        
        .alert { padding: 18px 22px; border-radius: 12px; margin-bottom: 25px; font-size: 15px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .info-item { padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .info-item:last-child { border-bottom: none; }
        .info-item strong { color: #374151; }
        
        @media (max-width: 1000px) {
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
