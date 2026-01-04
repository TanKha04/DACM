<?php
/**
 * Trang yêu cầu định vị vị trí khách hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Kiểm tra user đã có vị trí chưa
$stmt = $pdo->prepare("SELECT lat, lng FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Nếu đã có vị trí và không phải cập nhật thì redirect
if ($user['lat'] && $user['lng'] && !isset($_GET['update'])) {
    header('Location: index.php');
    exit;
}

// Xử lý lưu vị trí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = (float)($_POST['lat'] ?? 0);
    $lng = (float)($_POST['lng'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    
    if ($lat && $lng) {
        // Cập nhật vị trí user
        $stmt = $pdo->prepare("UPDATE users SET lat = ?, lng = ?, address = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $address, $userId]);
        
        // Cập nhật session
        $_SESSION['user_lat'] = $lat;
        $_SESSION['user_lng'] = $lng;
        
        $message = 'success:Đã lưu vị trí thành công!';
        
        // Redirect sau 1 giây
        header("Refresh: 1; url=index.php");
    } else {
        $message = 'error:Vui lòng cho phép truy cập vị trí hoặc chọn trên bản đồ!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật vị trí - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .location-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            padding: 20px;
        }
        .location-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .location-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .location-header .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .location-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .location-header p {
            color: #7f8c8d;
        }
        #map {
            height: 300px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .btn-locate {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-locate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.4);
        }
        .btn-save {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-save:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        .address-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
        }
        .coords {
            font-size: 12px;
            color: #7f8c8d;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="location-page">
        <div class="location-card">
            <div class="location-header">
                <div class="icon">📍</div>
                <h1>Xác định vị trí của bạn</h1>
                <p>Để tìm các cửa hàng gần bạn nhất và tính phí ship chính xác</p>
            </div>
            
            <?php if ($message): 
                $parts = explode(':', $message, 2);
            ?>
            <div class="alert alert-<?= $parts[0] ?>" style="margin-bottom: 20px;"><?= htmlspecialchars($parts[1]) ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>💡 Lưu ý:</strong> Vị trí của bạn giúp chúng tôi:
                <ul style="margin: 10px 0 0 20px; color: #666;">
                    <li>Hiển thị cửa hàng gần bạn nhất</li>
                    <li>Tính phí ship chính xác</li>
                    <li>Giao hàng nhanh hơn</li>
                </ul>
            </div>
            
            <button type="button" class="btn-locate" onclick="getCurrentLocation()">
                <span>🎯</span> Sử dụng vị trí hiện tại
            </button>
            
            <div id="map"></div>
            
            <form method="POST" id="locationForm">
                <input type="hidden" name="lat" id="lat" value="">
                <input type="hidden" name="lng" id="lng" value="">
                <input type="text" name="address" id="address" class="address-input" placeholder="Địa chỉ của bạn..." readonly>
                <div class="coords" id="coords"></div>
                <button type="submit" class="btn-save" id="btnSave" disabled>✓ Xác nhận vị trí</button>
            </form>
            
            <?php if ($user['lat'] && $user['lng']): ?>
            <p style="text-align: center; margin-top: 15px;">
                <a href="index.php" style="color: #7f8c8d;">Bỏ qua, giữ vị trí cũ →</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let map, marker;
    const defaultLat = 10.762622;
    const defaultLng = 106.660172;
    
    // Khởi tạo bản đồ
    function initMap(lat, lng) {
        if (map) {
            map.setView([lat, lng], 16);
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        } else {
            map = L.map('map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);
            
            marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            
            // Khi kéo marker
            marker.on('dragend', function(e) {
                const latlng = e.target.getLatLng();
                updateLocation(latlng.lat, latlng.lng);
            });
            
            // Khi click trên bản đồ
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateLocation(e.latlng.lat, e.latlng.lng);
            });
        }
        
        updateLocation(lat, lng);
    }
    
    // Lấy vị trí hiện tại
    function getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    initMap(pos.coords.latitude, pos.coords.longitude);
                },
                function(err) {
                    alert('Không thể lấy vị trí. Vui lòng chọn trên bản đồ!');
                    initMap(defaultLat, defaultLng);
                },
                { enableHighAccuracy: true }
            );
        } else {
            alert('Trình duyệt không hỗ trợ định vị!');
            initMap(defaultLat, defaultLng);
        }
    }
    
    // Cập nhật vị trí
    function updateLocation(lat, lng) {
        document.getElementById('lat').value = lat;
        document.getElementById('lng').value = lng;
        document.getElementById('coords').textContent = `Tọa độ: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        document.getElementById('btnSave').disabled = false;
        
        // Lấy địa chỉ từ tọa độ
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('address').value = data.display_name;
                }
            });
    }
    
    // Khởi tạo bản đồ khi load trang
    window.onload = function() {
        <?php if ($user['lat'] && $user['lng']): ?>
        initMap(<?= $user['lat'] ?>, <?= $user['lng'] ?>);
        <?php else: ?>
        initMap(defaultLat, defaultLng);
        <?php endif; ?>
    };
    </script>
</body>
</html>
