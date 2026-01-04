<?php
/**
 * Gửi yêu cầu trở thành Seller hoặc Shipper
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Tạo thư mục uploads nếu chưa có
$uploadDir = __DIR__ . '/../uploads/requests/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Kiểm tra đã có yêu cầu pending chưa
$stmt = $pdo->prepare("SELECT * FROM role_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$existingRequest = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingRequest) {
    $requestedRole = $_POST['role'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $shopName = trim($_POST['shop_name'] ?? '');
    $shopAddress = trim($_POST['shop_address'] ?? '');
    $shopDescription = trim($_POST['shop_description'] ?? '');
    $vehicleType = trim($_POST['vehicle_type'] ?? '');
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $idCard = trim($_POST['id_card'] ?? '');
    
    if (!in_array($requestedRole, ['seller', 'shipper'])) {
        $error = 'Vai trò không hợp lệ';
    } elseif (empty($reason)) {
        $error = 'Vui lòng nhập lý do';
    } elseif ($requestedRole === 'seller' && empty($shopName)) {
        $error = 'Vui lòng nhập tên cửa hàng';
    } elseif ($requestedRole === 'shipper' && empty($idCard)) {
        $error = 'Vui lòng nhập số CMND/CCCD';
    } else {
        // Xử lý upload ảnh
        $uploadedImages = [];
        $imageFields = ['id_card_image', 'shop_image', 'vehicle_image'];
        
        foreach ($imageFields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($file['type'], $allowedTypes)) {
                    $error = 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)';
                    break;
                }
                
                if ($file['size'] > 5 * 1024 * 1024) { // Max 5MB
                    $error = 'Kích thước file không được vượt quá 5MB';
                    break;
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = $field . '_' . $userId . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $newName;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $uploadedImages[$field] = 'uploads/requests/' . $newName;
                }
            }
        }
        
        if (empty($error)) {
            // Chuẩn bị dữ liệu bổ sung dạng JSON
            $additionalData = [
                'shop_name' => $shopName,
                'shop_address' => $shopAddress,
                'shop_description' => $shopDescription,
                'vehicle_type' => $vehicleType,
                'vehicle_number' => $vehicleNumber,
                'id_card' => $idCard,
                'images' => $uploadedImages
            ];
            
            $stmt = $pdo->prepare("INSERT INTO role_requests (user_id, requested_role, reason, additional_data) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $requestedRole, $reason, json_encode($additionalData)]);
            $message = 'Yêu cầu đã được gửi. Vui lòng chờ admin duyệt.';
            
            // Refresh để hiển thị trạng thái mới
            $stmt = $pdo->prepare("SELECT * FROM role_requests WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$userId]);
            $existingRequest = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký vai trò - FastFood</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 30px; }
        .container { max-width: 700px; margin: 0 auto; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 20px; color: #2c3e50; }
        h3 { margin: 25px 0 15px; color: #34495e; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; }
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; }
        textarea { min-height: 100px; resize: vertical; }
        input:focus, select:focus, textarea:focus { border-color: #4CAF50; outline: none; }
        .btn { padding: 14px 28px; background: #4CAF50; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn:hover { background: #45a049; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .pending-box { background: #fff3cd; padding: 20px; border-radius: 8px; }
        a { color: #4CAF50; }
        
        /* Role specific sections */
        .role-section { display: none; }
        .role-section.active { display: block; }
        
        /* File upload styling */
        .file-upload { 
            border: 2px dashed #ccc; 
            border-radius: 8px; 
            padding: 20px; 
            text-align: center; 
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .file-upload:hover { border-color: #4CAF50; background: #f0fff0; }
        .file-upload.has-file { border-color: #4CAF50; background: #e8f5e9; }
        .file-upload input[type="file"] { display: none; }
        .file-upload .icon { font-size: 40px; margin-bottom: 10px; }
        .file-upload .text { color: #666; font-size: 14px; }
        .file-upload .preview { max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 8px; }
        .file-upload .file-name { color: #4CAF50; font-weight: 500; margin-top: 8px; }
        
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        
        .note { font-size: 13px; color: #666; margin-top: 5px; }
        .required { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📝 Đăng ký vai trò mới</h1>
            
            <?php if ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($existingRequest): ?>
                <div class="pending-box">
                    <h3 style="margin-top: 0; border: none;">⏳ Yêu cầu đang chờ duyệt</h3>
                    <p>Vai trò: <strong><?= ucfirst($existingRequest['requested_role']) ?></strong></p>
                    <p>Ngày gửi: <?= date('d/m/Y H:i', strtotime($existingRequest['created_at'])) ?></p>
                    <?php 
                    $additionalData = json_decode($existingRequest['additional_data'] ?? '{}', true);
                    if (!empty($additionalData['images'])): 
                    ?>
                        <p style="margin-top: 15px;"><strong>Ảnh đã gửi:</strong></p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                            <?php foreach ($additionalData['images'] as $key => $imgPath): ?>
                                <img src="../<?= htmlspecialchars($imgPath) ?>" style="max-width: 120px; border-radius: 8px; border: 1px solid #ddd;">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Chọn vai trò muốn đăng ký <span class="required">*</span></label>
                        <select name="role" id="roleSelect" required>
                            <option value="">-- Chọn vai trò --</option>
                            <option value="seller">🏪 Người bán (Seller)</option>
                            <option value="shipper">🚚 Shipper</option>
                        </select>
                    </div>
                    
                    <!-- Seller Section -->
                    <div id="sellerSection" class="role-section">
                        <h3>🏪 Thông tin cửa hàng</h3>
                        
                        <div class="form-group">
                            <label>Tên cửa hàng <span class="required">*</span></label>
                            <input type="text" name="shop_name" placeholder="Nhập tên cửa hàng của bạn">
                        </div>
                        
                        <div class="form-group">
                            <label>Địa chỉ cửa hàng <span class="required">*</span></label>
                            <input type="text" name="shop_address" placeholder="Địa chỉ chi tiết cửa hàng">
                        </div>
                        
                        <div class="form-group">
                            <label>Mô tả cửa hàng</label>
                            <textarea name="shop_description" placeholder="Giới thiệu về cửa hàng của bạn..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Ảnh cửa hàng</label>
                            <div class="file-upload" onclick="document.getElementById('shop_image').click()">
                                <input type="file" name="shop_image" id="shop_image" accept="image/*" onchange="previewFile(this, 'shopPreview')">
                                <div class="icon">🏪</div>
                                <div class="text">Click để chọn ảnh cửa hàng</div>
                                <div class="note">(JPG, PNG, GIF - Tối đa 5MB)</div>
                                <img id="shopPreview" class="preview" style="display: none;">
                                <div id="shopFileName" class="file-name"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipper Section -->
                    <div id="shipperSection" class="role-section">
                        <h3>🚚 Thông tin shipper</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Loại phương tiện <span class="required">*</span></label>
                                <select name="vehicle_type">
                                    <option value="">-- Chọn --</option>
                                    <option value="motorbike">Xe máy</option>
                                    <option value="bicycle">Xe đạp</option>
                                    <option value="car">Ô tô</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Biển số xe</label>
                                <input type="text" name="vehicle_number" placeholder="VD: 29-A1 12345">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Ảnh phương tiện</label>
                            <div class="file-upload" onclick="document.getElementById('vehicle_image').click()">
                                <input type="file" name="vehicle_image" id="vehicle_image" accept="image/*" onchange="previewFile(this, 'vehiclePreview')">
                                <div class="icon">🏍️</div>
                                <div class="text">Click để chọn ảnh phương tiện</div>
                                <div class="note">(JPG, PNG, GIF - Tối đa 5MB)</div>
                                <img id="vehiclePreview" class="preview" style="display: none;">
                                <div id="vehicleFileName" class="file-name"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Common Section -->
                    <div id="commonSection" class="role-section">
                        <h3>📋 Thông tin cá nhân</h3>
                        
                        <div class="form-group">
                            <label>Số CMND/CCCD <span class="required">*</span></label>
                            <input type="text" name="id_card" placeholder="Nhập số CMND hoặc CCCD">
                        </div>
                        
                        <div class="form-group">
                            <label>Ảnh CMND/CCCD (mặt trước) <span class="required">*</span></label>
                            <div class="file-upload" onclick="document.getElementById('id_card_image').click()">
                                <input type="file" name="id_card_image" id="id_card_image" accept="image/*" onchange="previewFile(this, 'idCardPreview')">
                                <div class="icon">🪪</div>
                                <div class="text">Click để chọn ảnh CMND/CCCD</div>
                                <div class="note">(JPG, PNG, GIF - Tối đa 5MB)</div>
                                <img id="idCardPreview" class="preview" style="display: none;">
                                <div id="idCardFileName" class="file-name"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Lý do đăng ký <span class="required">*</span></label>
                            <textarea name="reason" placeholder="Mô tả lý do bạn muốn trở thành seller/shipper..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn">🚀 Gửi yêu cầu</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <p style="margin-top: 20px;"><a href="index.php">← Quay lại trang chủ</a></p>
        </div>
    </div>
    
    <script>
        const roleSelect = document.getElementById('roleSelect');
        const sellerSection = document.getElementById('sellerSection');
        const shipperSection = document.getElementById('shipperSection');
        const commonSection = document.getElementById('commonSection');
        
        roleSelect.addEventListener('change', function() {
            const role = this.value;
            
            sellerSection.classList.remove('active');
            shipperSection.classList.remove('active');
            commonSection.classList.remove('active');
            
            if (role === 'seller') {
                sellerSection.classList.add('active');
                commonSection.classList.add('active');
            } else if (role === 'shipper') {
                shipperSection.classList.add('active');
                commonSection.classList.add('active');
            }
        });
        
        function previewFile(input, previewId) {
            const preview = document.getElementById(previewId);
            const fileNameDiv = document.getElementById(previewId.replace('Preview', 'FileName'));
            const uploadDiv = input.closest('.file-upload');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadDiv.classList.add('has-file');
                    
                    if (fileNameDiv) {
                        fileNameDiv.textContent = '✓ ' + input.files[0].name;
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
