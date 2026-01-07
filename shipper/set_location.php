<?php
/**
 * Shipper - Cập nhật vị trí để nhận đơn hàng gần nhất
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

// Lấy thông tin shipper
$stmt = $pdo->prepare("SELECT u.*, si.current_lat, si.current_lng FROM users u LEFT JOIN shipper_info si ON u.id = si.user_id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$currentLat = $user['current_lat'] ?? null;
$currentLng = $user['current_lng'] ?? null;

$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật vị trí - Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .location-container { max-width: 800px; margin: 0 auto; }
        .location-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
        .location-header { text-align: center; margin-bottom: 25px; }
        .location-header .icon { font-size: 50px; margin-bottom: 10px; }
        .location-header h2 { color: #2c3e50; font-size: 22px; margin-bottom: 8px; }
        .location-header p { color: #7f8c8d; font-size: 14px; }
        #map { height: 400px; border-radius: 12px; border: 2px solid #e5e7eb; margin-bottom: 15px; }
        .btn-locate { width: 100%; padding: 14px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-locate:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(220, 38, 38, 0.4); }
        .btn-locate:disabled { background: #95a5a6; cursor: not-allowed; transform: none; }
        .coords-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .coords-row input { flex: 1; padding: 12px 15px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; background: #f9fafb; }
        .address-input { width: 100%; padding: 12px 15px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; margin-bottom: 15px; box-sizing: border-box; }
        .hint { font-size: 13px; color: #f39c12; margin-bottom: 15px; background: #fff8e1; padding: 12px 15px; border-radius: 10px; border: 1px solid #ffecb3; }
        #locationStatus { font-size: 14px; font-weight: 500; margin-bottom: 15px; padding: 12px 15px; border-radius: 10px; display: none; }
        .status-success { background: #d4edda; color: #155724; display: block !important; }
        .status-error { background: #f8d7da; color: #721c24; display: block !important; }
        .status-loading { background: #d1ecf1; color: #0c5460; display: block !important; }
        .btn-save { width: 100%; padding: 16px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4); }
        .btn-save:disabled { background: #95a5a6; cursor: not-allowed; transform: none; }
        .current-location { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .current-location h4 { color: #2e7d32; margin-bottom: 8px; font-size: 14px; }
        .current-location p { color: #558b2f; font-size: 13px; margin: 0; }
        .benefit-box { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 3px solid #fbbf24; }
        .benefit-box h3 { margin: 0 0 15px; font-size: 18px; }
        .benefit-list { list-style: none; padding: 0; margin: 0; }
        .benefit-list li { padding: 8px 0; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .benefit-list li::before { content: '✓'; background: rgba(255,255,255,0.2); width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📍 Cập nhật vị trí</h1>
        </div>
        
        <div class="location-container">
            <div class="benefit-box">
                <h3>🎯 Tại sao cần cập nhật vị trí?</h3>
                <ul class="benefit-list">
                    <li>Nhận thông báo đơn hàng gần bạn nhất</li>
                    <li>Shipper gần shop hơn sẽ được ưu tiên hiển thị đơn trước</li>
                    <li>Tránh tình trạng đơn bị shipper khác nhận mất</li>
                    <li>Tối ưu quãng đường di chuyển, tiết kiệm xăng</li>
                </ul>
            </div>
            
            <div class="location-card">
                <div class="location-header">
                    <div class="icon">🏍️</div>
                    <h2>Vị trí hiện tại của bạn</h2>
                    <p>Cập nhật vị trí để nhận đơn hàng gần nhất</p>
                </div>
                
                <?php if ($currentLat && $currentLng): ?>
                <div class="current-location">
                    <h4>✓ Đã có vị trí</h4>
                    <p>Tọa độ: <?= $currentLat ?>, <?= $currentLng ?></p>
                </div>
                <?php else: ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                    <h4 style="color: #856404; margin: 0 0 8px;">⚠️ Chưa có vị trí</h4>
                    <p style="color: #856404; font-size: 13px; margin: 0;">Bạn chưa cập nhật vị trí. Hãy bật GPS hoặc chọn trên bản đồ để nhận đơn hàng gần nhất!</p>
                </div>
                <?php endif; ?>
                
                <!-- Tìm kiếm địa chỉ -->
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="searchAddress" class="address-input" style="margin-bottom: 0; flex: 1;" placeholder="🔍 Nhập địa chỉ để tìm kiếm...">
                        <button type="button" onclick="searchAddress()" style="padding: 12px 20px; background: #9b59b6; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600;">Tìm</button>
                    </div>
                    <div id="searchResults" style="display: none; background: white; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px; max-height: 200px; overflow-y: auto; position: absolute; z-index: 1000; width: calc(100% - 60px);"></div>
                </div>
                
                <button type="button" id="btnLocate" class="btn-locate" onclick="getCurrentLocation()">
                    🎯 Lấy vị trí GPS hiện tại
                </button>
                
                <div id="map"></div>
                
                <div class="hint">💡 Click hoặc kéo marker trên bản đồ để chọn vị trí của bạn</div>
                
                <div id="locationStatus"></div>
                
                <div class="coords-row">
                    <input type="text" id="lat" value="<?= $currentLat ?? '' ?>" placeholder="Vĩ độ" readonly>
                    <input type="text" id="lng" value="<?= $currentLng ?? '' ?>" placeholder="Kinh độ" readonly>
                </div>
                
                <input type="text" id="address" class="address-input" placeholder="Địa chỉ của bạn..." readonly>
                
                <button type="button" id="btnSave" class="btn-save" onclick="manualSave()">
                    💾 Lưu vị trí
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let map, marker;
    const defaultLat = <?= $currentLat ?? DEFAULT_LAT ?>;
    const defaultLng = <?= $currentLng ?? DEFAULT_LNG ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        map = L.map('map').setView([defaultLat, defaultLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);
        
        marker.on('dragend', function(e) {
            const latlng = e.target.getLatLng();
            updateCoords(latlng.lat, latlng.lng);
        });
        
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng);
        });
        
        <?php if ($currentLat && $currentLng): ?>
        updateCoords(<?= $currentLat ?>, <?= $currentLng ?>);
        <?php endif; ?>
        
        // Enter để tìm kiếm
        document.getElementById('searchAddress').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress();
            }
        });
    });
    
    function updateCoords(lat, lng) {
        document.getElementById('lat').value = lat.toFixed(8);
        document.getElementById('lng').value = lng.toFixed(8);
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('address').value = data.display_name;
                }
            });
    }
    
    function saveLocation(lat, lng) {
        const statusEl = document.getElementById('locationStatus');
        statusEl.className = 'status-loading';
        statusEl.innerHTML = '💾 Đang lưu vị trí...';
        statusEl.style.display = 'block';
        
        const btnSave = document.getElementById('btnSave');
        btnSave.disabled = true;
        btnSave.innerHTML = '⏳ Đang lưu...';
        
        fetch('../api/shipper_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusEl.className = 'status-success';
                statusEl.innerHTML = '✓ Đã lưu vị trí thành công! Bạn sẽ nhận được đơn hàng gần nhất.';
                btnSave.innerHTML = '✓ Đã lưu!';
                btnSave.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
                setTimeout(() => {
                    btnSave.innerHTML = '💾 Lưu vị trí';
                    btnSave.disabled = false;
                }, 2000);
            } else {
                statusEl.className = 'status-error';
                statusEl.innerHTML = '⚠️ ' + data.message;
                btnSave.innerHTML = '💾 Lưu vị trí';
                btnSave.disabled = false;
            }
        })
        .catch(err => {
            statusEl.className = 'status-error';
            statusEl.innerHTML = '⚠️ Lỗi kết nối';
            btnSave.innerHTML = '💾 Lưu vị trí';
            btnSave.disabled = false;
        });
    }
    
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
    
    function searchAddress() {
        const query = document.getElementById('searchAddress').value.trim();
        if (!query) return;
        
        const resultsDiv = document.getElementById('searchResults');
        resultsDiv.innerHTML = '<div style="padding: 10px; color: #666;">🔍 Đang tìm...</div>';
        resultsDiv.style.display = 'block';
        
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=vn&limit=5&accept-language=vi`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding: 10px; color: #e74c3c;">Không tìm thấy</div>';
                    return;
                }
                
                let html = '';
                data.forEach(item => {
                    html += `<div onclick="selectAddress(${item.lat}, ${item.lon}, '${item.display_name.replace(/'/g, "\\'")}')" 
                        style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;"
                        onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='white'">
                        <strong>📍 ${item.display_name.split(',')[0]}</strong>
                        <p style="margin: 5px 0 0; font-size: 12px; color: #7f8c8d;">${item.display_name}</p>
                    </div>`;
                });
                resultsDiv.innerHTML = html;
            });
    }
    
    function selectAddress(lat, lng, address) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('searchAddress').value = '';
        
        map.setView([lat, lng], 17);
        marker.setLatLng([lat, lng]);
        
        document.getElementById('lat').value = parseFloat(lat).toFixed(8);
        document.getElementById('lng').value = parseFloat(lng).toFixed(8);
        document.getElementById('address').value = address;
    }
    
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
        statusEl.innerHTML = '🔍 Đang xác định vị trí...';
        statusEl.style.display = 'block';
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                updateCoords(lat, lng);
                
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
</body>
</html>
