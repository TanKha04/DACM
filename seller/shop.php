<?php
/**
 * Quản lý thông tin cửa hàng - Seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
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
    
    // Xử lý tọa độ - chỉ set null nếu thực sự rỗng
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    
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
            $stmt = $pdo->prepare("UPDATE shops SET name = ?, description = ?, address = ?, phone = ?, image = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmt->execute([$name, $description, $address, $phone, $image, $latitude, $longitude, $shop['id']]);
            
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
                        <input type="text" name="address" id="addressInput" value="<?= htmlspecialchars($shop['address']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>📍 Vị trí cửa hàng trên bản đồ</label>
                        
                        <!-- Tìm kiếm địa chỉ -->
                        <div style="margin-bottom: 10px;">
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="searchAddress" placeholder="🔍 Nhập địa chỉ để tìm kiếm..." style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px;">
                                <button type="button" onclick="searchAddress()" style="padding: 12px 20px; background: #9b59b6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                    Tìm
                                </button>
                            </div>
                            <div id="searchResults" style="display: none; background: white; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px; max-height: 200px; overflow-y: auto; position: relative; z-index: 1000;"></div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <button type="button" onclick="getCurrentLocation()" class="btn" style="flex: 1; padding: 12px; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer;">
                                🎯 Lấy vị trí hiện tại (GPS)
                            </button>
                        </div>
                        <div id="map" style="height: 300px; border-radius: 12px; margin-bottom: 10px;"></div>
                        <p style="font-size: 12px; color: #888; margin-bottom: 10px;">💡 Click hoặc kéo marker trên bản đồ để chọn vị trí - sẽ tự động lưu!</p>
                        <div id="locationStatus" style="font-size: 13px; margin-bottom: 10px; font-weight: 500;"></div>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="latitude" id="latitude" value="<?= $shop['latitude'] ?? '' ?>" placeholder="Vĩ độ" readonly style="flex: 1; background: #f5f5f5;">
                            <input type="text" name="longitude" id="longitude" value="<?= $shop['longitude'] ?? '' ?>" placeholder="Kinh độ" readonly style="flex: 1; background: #f5f5f5;">
                        </div>
                        <?php if (!$shop['latitude'] || !$shop['longitude']): ?>
                        <p style="color: #e74c3c; font-size: 13px; margin-top: 10px;">⚠️ Cửa hàng chưa có vị trí! Khách hàng sẽ không thể tìm thấy bạn trên bản đồ.</p>
                        <?php else: ?>
                        <p style="color: #27ae60; font-size: 13px; margin-top: 10px;">✓ Đã có vị trí: <?= $shop['latitude'] ?>, <?= $shop['longitude'] ?></p>
                        <?php endif; ?>
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
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let map, marker;
    const defaultLat = <?= $shop['latitude'] ?? DEFAULT_LAT ?>;
    const defaultLng = <?= $shop['longitude'] ?? DEFAULT_LNG ?>;
    
    // Khởi tạo bản đồ
    document.addEventListener('DOMContentLoaded', function() {
        map = L.map('map').setView([defaultLat, defaultLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        // Tạo marker
        marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);
        
        // Khi kéo marker
        marker.on('dragend', function(e) {
            const latlng = e.target.getLatLng();
            updateCoords(latlng.lat, latlng.lng);
            saveShopLocation(latlng.lat, latlng.lng); // Tự động lưu
        });
        
        // Khi click trên bản đồ
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng);
            saveShopLocation(e.latlng.lat, e.latlng.lng); // Tự động lưu
        });
        
        // Nếu đã có tọa độ
        <?php if ($shop['latitude'] && $shop['longitude']): ?>
        updateCoords(<?= $shop['latitude'] ?>, <?= $shop['longitude'] ?>);
        <?php endif; ?>
    });
    
    // Cập nhật tọa độ hiển thị
    function updateCoords(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(8);
        document.getElementById('longitude').value = lng.toFixed(8);
        
        // Lấy địa chỉ từ tọa độ
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('addressInput').value = data.display_name;
                }
            });
    }
    
    // Lưu vị trí vào database
    function saveShopLocation(lat, lng) {
        const statusEl = document.getElementById('locationStatus');
        if (statusEl) {
            statusEl.innerHTML = '💾 Đang lưu vị trí...';
            statusEl.style.color = '#3498db';
        }
        
        fetch('<?= getBaseUrl() ?>/api/save_shop_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (statusEl) {
                if (data.success) {
                    statusEl.innerHTML = '✓ Đã lưu vị trí thành công!';
                    statusEl.style.color = '#27ae60';
                } else {
                    statusEl.innerHTML = '⚠️ ' + data.message;
                    statusEl.style.color = '#e74c3c';
                }
            }
        })
        .catch(err => {
            if (statusEl) {
                statusEl.innerHTML = '⚠️ Lỗi kết nối';
                statusEl.style.color = '#e74c3c';
            }
        });
    }
    
    // Tìm kiếm địa chỉ
    function searchAddress() {
        const query = document.getElementById('searchAddress').value.trim();
        if (!query) {
            alert('Vui lòng nhập địa chỉ cần tìm!');
            return;
        }
        
        const resultsDiv = document.getElementById('searchResults');
        resultsDiv.innerHTML = '<div style="padding: 10px; color: #666;">🔍 Đang tìm kiếm...</div>';
        resultsDiv.style.display = 'block';
        
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=vn&limit=5&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding: 10px; color: #e74c3c;">Không tìm thấy địa chỉ. Thử nhập chi tiết hơn.</div>';
                    return;
                }
                
                let html = '';
                data.forEach((item) => {
                    html += `<div onclick="selectAddress(${item.lat}, ${item.lon}, '${item.display_name.replace(/'/g, "\\'")}')" 
                        style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;"
                        onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='white'">
                        <strong style="color: #2c3e50;">📍 ${item.display_name.split(',')[0]}</strong>
                        <p style="margin: 5px 0 0; font-size: 12px; color: #7f8c8d;">${item.display_name}</p>
                    </div>`;
                });
                resultsDiv.innerHTML = html;
            })
            .catch(err => {
                resultsDiv.innerHTML = '<div style="padding: 10px; color: #e74c3c;">Lỗi kết nối. Vui lòng thử lại.</div>';
            });
    }
    
    function selectAddress(lat, lng, address) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('searchAddress').value = '';
        
        map.setView([lat, lng], 17);
        marker.setLatLng([lat, lng]);
        
        document.getElementById('latitude').value = parseFloat(lat).toFixed(8);
        document.getElementById('longitude').value = parseFloat(lng).toFixed(8);
        document.getElementById('addressInput').value = address;
        
        saveShopLocation(lat, lng);
    }
    
    document.getElementById('searchAddress').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchAddress();
        }
    });
    
    // Lấy vị trí hiện tại
    function getCurrentLocation() {
        const statusEl = document.getElementById('locationStatus');
        const btn = document.querySelector('button[onclick="getCurrentLocation()"]');
        const originalText = btn.innerHTML;
        
        if (!navigator.geolocation) {
            if (statusEl) {
                statusEl.innerHTML = '⚠️ Trình duyệt không hỗ trợ định vị. Hãy click trên bản đồ để chọn vị trí.';
                statusEl.style.color = '#e74c3c';
            }
            return;
        }
        
        btn.innerHTML = '⏳ Đang lấy vị trí...';
        btn.disabled = true;
        
        if (statusEl) {
            statusEl.innerHTML = '🔍 Đang xác định vị trí của bạn...';
            statusEl.style.color = '#3498db';
        }
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                updateCoords(lat, lng);
                
                // Tự động lưu vị trí vào database
                btn.innerHTML = '💾 Đang lưu...';
                saveShopLocation(lat, lng);
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '#3498db';
                    btn.disabled = false;
                }, 2000);
            },
            function(err) {
                let msg = 'Không thể lấy vị trí tự động. ';
                if (err.code === 1) msg += 'Bạn đã từ chối quyền truy cập.';
                else if (err.code === 2) msg += 'Không xác định được.';
                else if (err.code === 3) msg += 'Hết thời gian chờ.';
                msg += ' Hãy click trên bản đồ để chọn vị trí.';
                
                if (statusEl) {
                    statusEl.innerHTML = '⚠️ ' + msg;
                    statusEl.style.color = '#e74c3c';
                }
                
                btn.innerHTML = originalText;
                btn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }
    </script>
</body>
</html>
