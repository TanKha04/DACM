<?php
/**
 * Đăng ký mở cửa hàng - Seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Kiểm tra đã có shop chưa
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if ($shop) {
    header('Location: dashboard.php');
    exit;
}

// Hàm upload file
function uploadFile($file, $folder) {
    $uploadDir = __DIR__ . '/../uploads/' . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/' . $folder . '/' . $filename;
    }
    return false;
}

// Xử lý đăng ký shop
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim($_POST['shop_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    
    // Upload ảnh cửa hàng
    $shopImage = null;
    if (!empty($_FILES['shop_image']['name'])) {
        $shopImage = uploadFile($_FILES['shop_image'], 'shops');
    }
    
    // Upload giấy an toàn thực phẩm
    $foodSafetyCert = null;
    if (!empty($_FILES['food_safety_cert']['name'])) {
        $foodSafetyCert = uploadFile($_FILES['food_safety_cert'], 'certificates');
    }
    
    if ($shopName && $address && $phone) {
        $stmt = $pdo->prepare("INSERT INTO shops (user_id, name, address, phone, description, latitude, longitude, image, food_safety_cert, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $shopName, $address, $phone, $description, $latitude ?: null, $longitude ?: null, $shopImage, $foodSafetyCert]);
        header('Location: dashboard.php');
        exit;
    } else {
        $message = 'Vui lòng điền đầy đủ thông tin bắt buộc';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký mở cửa hàng - FastFood</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🏪 Đăng ký mở cửa hàng</h1>
        </div>
        
        <div class="card" style="max-width: 600px;">
            <?php if ($message): ?>
            <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Tên cửa hàng *</label>
                    <input type="text" name="shop_name" required placeholder="VD: Quán Bún Bò Huế Ngon">
                </div>
                <div class="form-group">
                    <label>Địa chỉ *</label>
                    <textarea name="address" rows="2" required placeholder="Địa chỉ chi tiết của cửa hàng" id="address"></textarea>
                </div>
                
                <div class="form-group">
                    <label>📍 Vị trí cửa hàng</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="button" id="getLocationBtn" class="btn btn-secondary" style="flex: 1;">
                            🎯 Lấy vị trí hiện tại
                        </button>
                    </div>
                    <div id="locationStatus" style="font-size: 13px; color: #666; margin-bottom: 10px;"></div>
                    <div id="mapContainer" style="height: 250px; border-radius: 8px; background: #f0f0f0; display: none; margin-bottom: 10px;"></div>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="latitude" id="latitude" placeholder="Vĩ độ (Latitude)" readonly style="flex: 1; background: #f5f5f5;">
                        <input type="text" name="longitude" id="longitude" placeholder="Kinh độ (Longitude)" readonly style="flex: 1; background: #f5f5f5;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Số điện thoại *</label>
                    <input type="tel" name="phone" required placeholder="0901234567">
                </div>
                <div class="form-group">
                    <label>Mô tả cửa hàng</label>
                    <textarea name="description" rows="3" placeholder="Giới thiệu về cửa hàng của bạn..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>📷 Ảnh cửa hàng</label>
                    <input type="file" name="shop_image" accept="image/*" id="shopImageInput">
                    <div id="shopImagePreview" style="margin-top: 10px;"></div>
                    <small style="color: #666;">Hỗ trợ: JPG, PNG, GIF (Tối đa 5MB)</small>
                </div>
                
                <div class="form-group">
                    <label>📄 Giấy chứng nhận An toàn thực phẩm</label>
                    <input type="file" name="food_safety_cert" accept="image/*,.pdf" id="certInput">
                    <div id="certPreview" style="margin-top: 10px;"></div>
                    <small style="color: #666;">Hỗ trợ: JPG, PNG, PDF (Tối đa 5MB)</small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Gửi yêu cầu</button>
            </form>
        </div>
    </div>
    
    <script>
    // Lấy vị trí hiện tại
    document.getElementById('getLocationBtn').addEventListener('click', function() {
        const statusEl = document.getElementById('locationStatus');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const mapContainer = document.getElementById('mapContainer');
        
        if (!navigator.geolocation) {
            statusEl.innerHTML = '<span style="color: red;">❌ Trình duyệt không hỗ trợ định vị</span>';
            return;
        }
        
        statusEl.innerHTML = '<span style="color: #2196F3;">⏳ Đang lấy vị trí...</span>';
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                latInput.value = lat.toFixed(8);
                lngInput.value = lng.toFixed(8);
                
                statusEl.innerHTML = '<span style="color: green;">✅ Đã lấy vị trí thành công!</span>';
                
                // Hiển thị bản đồ
                mapContainer.style.display = 'block';
                mapContainer.innerHTML = '<iframe width="100%" height="100%" frameborder="0" style="border-radius: 8px;" src="https://www.openstreetmap.org/export/embed.html?bbox=' + (lng - 0.005) + '%2C' + (lat - 0.005) + '%2C' + (lng + 0.005) + '%2C' + (lat + 0.005) + '&layer=mapnik&marker=' + lat + '%2C' + lng + '"></iframe>';
            },
            function(error) {
                let msg = 'Không thể lấy vị trí';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        msg = 'Bạn đã từ chối quyền truy cập vị trí';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        msg = 'Không thể xác định vị trí';
                        break;
                    case error.TIMEOUT:
                        msg = 'Hết thời gian chờ';
                        break;
                }
                statusEl.innerHTML = '<span style="color: red;">❌ ' + msg + '</span>';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    });
    
    // Preview ảnh cửa hàng
    document.getElementById('shopImageInput').addEventListener('change', function(e) {
        const preview = document.getElementById('shopImagePreview');
        const file = e.target.files[0];
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
    
    // Preview giấy chứng nhận
    document.getElementById('certInput').addEventListener('change', function(e) {
        const preview = document.getElementById('certPreview');
        const file = e.target.files[0];
        
        if (file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">';
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                preview.innerHTML = '<div style="padding: 10px; background: #f5f5f5; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;"><span style="font-size: 24px;">📄</span> ' + file.name + '</div>';
            }
        } else {
            preview.innerHTML = '';
        }
    });
    </script>
</body>
</html>
