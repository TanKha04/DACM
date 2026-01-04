<?php
/**
 * Trang cài đặt tài khoản khách hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';



$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';
$tab = $_GET['tab'] ?? 'profile';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Lấy địa chỉ
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Cập nhật thông tin cá nhân
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $address, $userId]);
        $_SESSION['user_name'] = $name;
        $message = 'success:Cập nhật thông tin thành công!';
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    
    // Đổi mật khẩu
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPass, $user['password'])) {
            $message = 'error:Mật khẩu hiện tại không đúng';
        } elseif (strlen($newPass) < 6) {
            $message = 'error:Mật khẩu mới phải có ít nhất 6 ký tự';
        } elseif ($newPass !== $confirmPass) {
            $message = 'error:Mật khẩu xác nhận không khớp';
        } else {
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPass, $userId]);
            $message = 'success:Đổi mật khẩu thành công!';
        }
        $tab = 'password';
    }
    
    // Thêm địa chỉ
    if ($action === 'add_address') {
        $name = trim($_POST['addr_name'] ?? '');
        $phone = trim($_POST['addr_phone'] ?? '');
        $address = trim($_POST['addr_address'] ?? '');
        $latitude = isset($_POST['addr_latitude']) ? floatval($_POST['addr_latitude']) : null;
        $longitude = isset($_POST['addr_longitude']) ? floatval($_POST['addr_longitude']) : null;
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        if ($isDefault) {
            $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        }
        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, name, phone, address, latitude, longitude, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $phone, $address, $latitude, $longitude, $isDefault]);
        $message = 'success:Thêm địa chỉ thành công!';
        $tab = 'address';
        // Refresh addresses
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
        $stmt->execute([$userId]);
        $addresses = $stmt->fetchAll();
    }
    
    // Xóa địa chỉ
    if ($action === 'delete_address') {
        $addrId = (int)($_POST['address_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$addrId, $userId]);
        $message = 'success:Đã xóa địa chỉ!';
        $tab = 'address';
        
        // Refresh addresses
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
        $stmt->execute([$userId]);
        $addresses = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .profile-grid { display: grid; grid-template-columns: 250px 1fr; gap: 30px; }
        .profile-menu { background: white; border-radius: 15px; padding: 20px; height: fit-content; }
        .profile-menu a { display: block; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .profile-menu a:hover { background: #f8f9fa; }
        .profile-menu a.active { background: #ff6b35; color: white; }
        .address-card { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: start; }
        .address-info { flex: 1; }
        .address-default { background: #ff6b35; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">⚙️ Cài đặt tài khoản</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <div class="profile-menu">
                <a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">👤 Thông tin cá nhân</a>
                <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">🔒 Đổi mật khẩu</a>
                <a href="?tab=address" class="<?= $tab === 'address' ? 'active' : '' ?>">📍 Địa chỉ giao hàng</a>
                <a href="payments.php">💳 Lịch sử thanh toán</a>
                <a href="reviews.php">⭐ Đánh giá của tôi</a>
            </div>
            
            <div class="section">
                <?php if ($tab === 'profile'): ?>
                <h2>Thông tin cá nhân</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Họ và tên</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small style="color: #7f8c8d;">Email không thể thay đổi</small>
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Địa chỉ mặc định</label>
                        <textarea name="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Lưu thay đổi</button>
                </form>
                
                <?php elseif ($tab === 'password'): ?>
                <h2>Đổi mật khẩu</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu mới</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Xác nhận mật khẩu mới</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Đổi mật khẩu</button>
                </form>
                
                <?php elseif ($tab === 'address'): ?>
                <h2>Địa chỉ giao hàng</h2>
                
                <?php foreach ($addresses as $addr): ?>
                <div class="address-card">
                    <div class="address-info">
                        <strong><?= htmlspecialchars($addr['name']) ?></strong>
                        <?php if ($addr['is_default']): ?><span class="address-default">Mặc định</span><?php endif; ?>
                        <br>
                        <span style="color: #7f8c8d;"><?= htmlspecialchars($addr['phone']) ?></span>
                        <br>
                        <?= htmlspecialchars($addr['address']) ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_address">
                        <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                        <button type="submit" class="btn-danger" style="padding: 5px 10px;" onclick="return confirm('Xóa địa chỉ này?')">Xóa</button>
                    </form>
                </div>
                <?php endforeach; ?>
                
                <h3 style="margin: 30px 0 20px;">Thêm địa chỉ mới</h3>
                                <form method="POST">
                                        <input type="hidden" name="action" value="add_address">
                                        <div class="form-group">
                                                <label>Tên người nhận</label>
                                                <input type="text" name="addr_name" required>
                                        </div>
                                        <div class="form-group">
                                                <label>Số điện thoại</label>
                                                <input type="tel" name="addr_phone" required>
                                        </div>
                                        <div class="form-group">
                                                <label>Địa chỉ
                                                        <button type="button" onclick="openMapModal()" style="margin-left: 10px; background: #3498db; color: white; border: none; border-radius: 6px; padding: 4px 12px; font-size: 13px; cursor: pointer;">📍 Chọn địa chỉ</button>
                                                </label>
                                                <textarea name="addr_address" id="addr_address" rows="2" required readonly style="background:#f5f5f5;"></textarea>
                                                <input type="hidden" name="addr_latitude" id="addr_latitude">
                                                <input type="hidden" name="addr_longitude" id="addr_longitude">
                                        </div>
                                        <div class="form-group">
                                                <label><input type="checkbox" name="is_default"> Đặt làm địa chỉ mặc định</label>
                                        </div>
                                        <button type="submit" class="btn-primary">Thêm địa chỉ</button>
                                </form>
                                <!-- Leaflet Map Modal (OpenStreetMap) -->
                                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                                <div id="mapModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
                                    <div style="background:white; border-radius:10px; padding:20px; max-width:95vw; max-height:90vh; position:relative;">
                                        <h3>Chọn vị trí trên bản đồ</h3>
                                        <div id="leafletMap" style="width:90vw; max-width:500px; height:350px;"></div>
                                        <div style="margin-top:10px; text-align:right;">
                                            <button type="button" onclick="closeMapModal()" style="padding:6px 18px; background:#dc3545; color:white; border:none; border-radius:5px;">Đóng</button>
                                            <button type="button" onclick="selectLeafletLocation()" style="padding:6px 18px; background:#28a745; color:white; border:none; border-radius:5px;">Chọn</button>
                                        </div>
                                    </div>
                                </div>
                                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                                <script>
                                let leafletMap, leafletMarker, selectedLat, selectedLng;
                                function openMapModal() {
                                    document.getElementById('mapModal').style.display = 'flex';
                                    setTimeout(initLeafletMap, 100);
                                }
                                function closeMapModal() {
                                    document.getElementById('mapModal').style.display = 'none';
                                    if (leafletMap) {
                                        leafletMap.remove();
                                        leafletMap = null;
                                    }
                                    leafletMarker = null;
                                    selectedLat = null;
                                    selectedLng = null;
                                }
                                function initLeafletMap() {
                                    if (leafletMap) return;
                                    let center;
                                    if (navigator.geolocation) {
                                        navigator.geolocation.getCurrentPosition(function(pos) {
                                            center = [pos.coords.latitude, pos.coords.longitude];
                                            setupMap(center);
                                        }, function() {
                                            center = [21.028511, 105.804817]; // Hà Nội
                                            setupMap(center);
                                        });
                                    } else {
                                        center = [21.028511, 105.804817];
                                        setupMap(center);
                                    }
                                    function setupMap(center) {
                                        selectedLat = center[0];
                                        selectedLng = center[1];
                                        leafletMap = L.map('leafletMap').setView(center, 16);
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            maxZoom: 19,
                                            attribution: '© OpenStreetMap'
                                        }).addTo(leafletMap);
                                        leafletMarker = L.marker(center, {draggable:true}).addTo(leafletMap);
                                        leafletMarker.on('dragend', function(e) {
                                            const latlng = e.target.getLatLng();
                                            selectedLat = latlng.lat;
                                            selectedLng = latlng.lng;
                                        });
                                    }
                                }
                                function selectLeafletLocation() {
                                    // Đảm bảo luôn cho phép bấm nút Chọn, chỉ báo lỗi nếu không lấy được địa chỉ
                                    if (typeof selectedLat === 'undefined' || typeof selectedLng === 'undefined' || selectedLat === null || selectedLng === null) {
                                        alert('Vui lòng chọn vị trí trên bản đồ!');
                                        return;
                                    }
                                    fetch(`../tools/nominatim_proxy.php?lat=${selectedLat}&lon=${selectedLng}`)
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data && data.display_name) {
                                                document.getElementById('addr_address').value = data.display_name;
                                                document.getElementById('addr_latitude').value = selectedLat;
                                                document.getElementById('addr_longitude').value = selectedLng;
                                                closeMapModal();
                                            } else {
                                                document.getElementById('addr_address').value = '';
                                                alert('Không lấy được địa chỉ!');
                                            }
                                        })
                                        .catch(function() {
                                            alert('Có lỗi khi lấy địa chỉ!');
                                        });
                                }
                                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
