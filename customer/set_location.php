<?php
/**
 * Trang cập nhật vị trí khách hàng - Giống seller
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$base = getBaseUrl();
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
        .location-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .location-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }
        .location-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .location-header .icon {
            font-size: 50px;
            margin-bottom: 10px;
        }
        .location-header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 8px;
        }
        .location-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        .map-section {
            margin-bottom: 20px;
        }
        .map-section label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            font-size: 15px;
        }
        #map {
            height: 350px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
        }
        .btn-locate {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-locate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.4);
        }
        .btn-locate:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        .coords-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .coords-row input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            background: #f9fafb;
        }
        .address-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        .hint {
            font-size: 12px;
            color: #f39c12;
            margin-bottom: 10px;
        }
        #locationStatus {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-loading {
            background: #d1ecf1;
            color: #0c5460;
        }
        .btn-back {
            display: block;
            text-align: center;
            color: #7f8c8d;
            text-decoration: none;
            margin-top: 20px;
            font-size: 14px;
        }
        .btn-back:hover {
            color: #3498db;
        }
        .btn-save {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4);
        }
        .btn-save:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        .current-location {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .current-location h4 {
            color: #2e7d32;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .current-location p {
            color: #558b2f;
            font-size: 13px;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="location-container">
        <div class="location-card">
            <div class="location-header">
                <div class="icon">📍</div>
                <h1>Cập nhật vị trí của bạn</h1>
                <p>Để tìm cửa hàng gần nhất và tính phí ship chính xác</p>
            </div>
            
            <?php if ($user['lat'] && $user['lng']): ?>
            <div class="current-location">
                <h4>✓ Vị trí hiện tại của bạn</h4>
                <p><?= htmlspecialchars($user['address'] ?: 'Tọa độ: ' . $user['lat'] . ', ' . $user['lng']) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="map-section">
                <label>📍 Vị trí của bạn trên bản đồ</label>
                
                <!-- Tìm kiếm địa chỉ -->
                <div class="search-box" style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="searchAddress" class="address-input" style="margin-bottom: 0; flex: 1;" placeholder="🔍 Nhập địa chỉ để tìm kiếm (VD: 123 Nguyễn Huệ, Quận 1)">
                        <button type="button" onclick="searchAddress()" style="padding: 12px 20px; background: #9b59b6; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600;">
                            Tìm
                        </button>
                    </div>
                    <div id="searchResults" style="display: none; background: white; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px; max-height: 200px; overflow-y: auto;"></div>
                </div>
                
                <button type="button" id="btnLocate" class="btn-locate" onclick="getCurrentLocation()">
                    🎯 Lấy vị trí hiện tại (GPS)
                </button>
                
                <div id="map"></div>
                
                <p class="hint">💡 Click hoặc kéo marker trên bản đồ để chọn vị trí - sẽ tự động lưu!</p>
                <p class="hint" style="color: #3498db;">🔵 Vòng tròn xanh hiển thị phạm vi giao hàng 10km từ vị trí của bạn</p>
                
                <div id="locationStatus"></div>
                
                <div class="coords-row">
                    <input type="text" id="lat" value="<?= $user['lat'] ?? '' ?>" placeholder="Vĩ độ" readonly>
                    <input type="text" id="lng" value="<?= $user['lng'] ?? '' ?>" placeholder="Kinh độ" readonly>
                </div>
                
                <input type="text" id="address" class="address-input" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Địa chỉ của bạn..." readonly>
                
                <?php if (!$user['lat'] || !$user['lng']): ?>
                <p style="color: #e74c3c; font-size: 13px;">⚠️ Bạn chưa có vị trí! Vui lòng cập nhật để tìm cửa hàng gần nhất.</p>
                <?php else: ?>
                <p style="color: #27ae60; font-size: 13px;">✓ Đã có vị trí: <?= $user['lat'] ?>, <?= $user['lng'] ?></p>
                <?php endif; ?>
                
                <button type="button" id="btnSave" class="btn-save" onclick="manualSave()">
                    💾 Lưu vị trí
                </button>
            </div>
            
            <a href="index.php" class="btn-back">← Quay lại trang chủ</a>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let map, marker, deliveryCircle;
    const defaultLat = <?= $user['lat'] ?? DEFAULT_LAT ?>;
    const defaultLng = <?= $user['lng'] ?? DEFAULT_LNG ?>;
    const MAX_DELIVERY_RADIUS = <?= defined('MAX_DELIVERY_RADIUS') ? MAX_DELIVERY_RADIUS : 10 ?>; // 10km
    
    // Khởi tạo bản đồ
    document.addEventListener('DOMContentLoaded', function() {
        map = L.map('map').setView([defaultLat, defaultLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        // Tạo marker
        marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);
        
        // Vẽ vòng tròn 10km từ vị trí hiện tại
        updateDeliveryCircle(defaultLat, defaultLng);
        
        // Khi kéo marker
        marker.on('dragend', function(e) {
            const latlng = e.target.getLatLng();
            updateCoords(latlng.lat, latlng.lng);
            updateDeliveryCircle(latlng.lat, latlng.lng);
            saveLocation(latlng.lat, latlng.lng);
        });
        
        // Khi click trên bản đồ
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng);
            updateDeliveryCircle(e.latlng.lat, e.latlng.lng);
            saveLocation(e.latlng.lat, e.latlng.lng);
        });
        
        // Nếu đã có tọa độ
        <?php if ($user['lat'] && $user['lng']): ?>
        updateCoords(<?= $user['lat'] ?>, <?= $user['lng'] ?>);
        updateDeliveryCircle(<?= $user['lat'] ?>, <?= $user['lng'] ?>);
        <?php endif; ?>
    });
    
    // Vẽ/cập nhật vòng tròn phạm vi giao hàng 10km
    function updateDeliveryCircle(lat, lng) {
        if (deliveryCircle) {
            map.removeLayer(deliveryCircle);
        }
        deliveryCircle = L.circle([lat, lng], {
            color: '#3498db',
            fillColor: '#3498db',
            fillOpacity: 0.1,
            radius: MAX_DELIVERY_RADIUS * 1000, // 10km = 10000m
            weight: 2,
            dashArray: '5, 10'
        }).addTo(map);
        
        // Thêm tooltip cho vòng tròn
        deliveryCircle.bindTooltip('Phạm vi giao hàng ' + MAX_DELIVERY_RADIUS + 'km', {
            permanent: false,
            direction: 'center'
        });
    }
    
    // Cập nhật tọa độ hiển thị
    function updateCoords(lat, lng) {
        document.getElementById('lat').value = lat.toFixed(8);
        document.getElementById('lng').value = lng.toFixed(8);
        
        // Lấy địa chỉ từ tọa độ
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('address').value = data.display_name;
                }
            });
    }
    
    // Lưu vị trí vào database
    function saveLocation(lat, lng) {
        const statusEl = document.getElementById('locationStatus');
        statusEl.className = 'status-loading';
        statusEl.innerHTML = '💾 Đang lưu vị trí...';
        statusEl.style.display = 'block';
        
        const btnSave = document.getElementById('btnSave');
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '⏳ Đang lưu...';
        }
        
        fetch('<?= $base ?>/api/save_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusEl.className = 'status-success';
                statusEl.innerHTML = '✓ Đã lưu vị trí thành công!';
                if (data.address) {
                    document.getElementById('address').value = data.address;
                }
                if (btnSave) {
                    btnSave.innerHTML = '✓ Đã lưu!';
                    btnSave.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
                    setTimeout(() => {
                        btnSave.innerHTML = '💾 Lưu vị trí';
                        btnSave.disabled = false;
                    }, 2000);
                }
            } else {
                statusEl.className = 'status-error';
                statusEl.innerHTML = '⚠️ ' + data.message;
                if (btnSave) {
                    btnSave.innerHTML = '💾 Lưu vị trí';
                    btnSave.disabled = false;
                }
            }
        })
        .catch(err => {
            statusEl.className = 'status-error';
            statusEl.innerHTML = '⚠️ Lỗi kết nối';
            if (btnSave) {
                btnSave.innerHTML = '💾 Lưu vị trí';
                btnSave.disabled = false;
            }
        });
    }
    
    // Lưu thủ công khi click nút
    function manualSave() {
        const lat = parseFloat(document.getElementById('lat').value);
        const lng = parseFloat(document.getElementById('lng').value);
        
        if (!lat || !lng) {
            const statusEl = document.getElementById('locationStatus');
            statusEl.className = 'status-error';
            statusEl.innerHTML = '⚠️ Vui lòng chọn vị trí trên bản đồ trước!';
            statusEl.style.display = 'block';
            return;
        }
        
        saveLocation(lat, lng);
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
        
        // Tìm kiếm qua Nominatim API
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=vn&limit=5&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding: 10px; color: #e74c3c;">Không tìm thấy địa chỉ. Thử nhập chi tiết hơn.</div>';
                    return;
                }
                
                let html = '';
                data.forEach((item, index) => {
                    html += `<div onclick="selectAddress(${item.lat}, ${item.lon}, '${item.display_name.replace(/'/g, "\\'")}')" 
                        style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;"
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
    
    // Chọn địa chỉ từ kết quả tìm kiếm
    function selectAddress(lat, lng, address) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('searchAddress').value = '';
        
        map.setView([lat, lng], 17);
        marker.setLatLng([lat, lng]);
        
        document.getElementById('lat').value = parseFloat(lat).toFixed(8);
        document.getElementById('lng').value = parseFloat(lng).toFixed(8);
        document.getElementById('address').value = address;
        
        // Tự động lưu
        saveLocation(lat, lng);
    }
    
    // Cho phép nhấn Enter để tìm kiếm
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('searchAddress').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress();
            }
        });
    });
    
    // Lấy vị trí hiện tại
    function getCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Trình duyệt không hỗ trợ định vị!');
            return;
        }
        
        const btn = document.getElementById('btnLocate');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Đang lấy vị trí...';
        btn.disabled = true;
        
        const statusEl = document.getElementById('locationStatus');
        statusEl.className = 'status-loading';
        statusEl.innerHTML = '🔍 Đang xác định vị trí của bạn...';
        statusEl.style.display = 'block';
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                updateCoords(lat, lng);
                
                // Tự động lưu
                btn.innerHTML = '💾 Đang lưu...';
                saveLocation(lat, lng);
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            },
            function(err) {
                let msg = 'Không thể lấy vị trí. ';
                if (err.code === 1) msg += 'Bạn đã từ chối quyền truy cập vị trí.';
                else if (err.code === 2) msg += 'Không thể xác định vị trí.';
                else if (err.code === 3) msg += 'Hết thời gian chờ.';
                
                statusEl.className = 'status-error';
                statusEl.innerHTML = '⚠️ ' + msg + ' Hãy click trên bản đồ để chọn vị trí.';
                
                btn.innerHTML = originalText;
                btn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
        );
    }
    </script>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
