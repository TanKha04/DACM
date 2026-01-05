<?php
/**
 * Tài khoản Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

$pdo = getConnection();
$userId = $_SESSION['user_id'];

$message = '';
$error = '';

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (empty($name)) {
                $error = 'Vui lòng nhập họ tên';
            } else {
                // Upload avatar nếu có
                $avatarPath = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $filename = 'shipper_' . $userId . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                        $avatarPath = 'uploads/avatars/' . $filename;
                    }
                }
                
                if ($avatarPath) {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ?, avatar = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $address, $avatarPath, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $address, $userId]);
                }
                
                $_SESSION['user_name'] = $name;
                $message = 'Cập nhật thông tin thành công!';
            }
        } elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Lấy mật khẩu hiện tại
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = 'Mật khẩu hiện tại không đúng';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Mật khẩu xác nhận không khớp';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $message = 'Đổi mật khẩu thành công!';
            }
        }
    }
}

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
    <title>Tài khoản Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-card { background: white; border-radius: 15px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ff6b35; }
        .profile-name { font-size: 24px; font-weight: bold; color: #333; }
        .profile-role { color: #ff6b35; font-size: 14px; margin-top: 5px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #ff6b35; }
        .form-group input[type="file"] { padding: 10px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .btn { padding: 12px 25px; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #ff6b35; color: white; }
        .btn-primary:hover { background: #e55a2b; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        .card-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; background: #f0f0f0; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .tab-btn.active { background: #ff6b35; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .info-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #666; }
        .info-value { font-weight: 500; color: #333; }
        
        .avatar-upload { position: relative; display: inline-block; }
        .avatar-upload input[type="file"] { display: none; }
        .avatar-upload label { display: block; cursor: pointer; }
        .avatar-upload .upload-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; text-align: center; padding: 5px; font-size: 12px; border-radius: 0 0 50px 50px; opacity: 0; transition: opacity 0.3s; }
        .avatar-upload:hover .upload-overlay { opacity: 1; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>⚙️ Tài khoản</h1>
        </div>
        
        <div class="profile-container">
            <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('info')">📋 Thông tin</button>
                <button class="tab-btn" onclick="showTab('edit')">✏️ Chỉnh sửa</button>
                <button class="tab-btn" onclick="showTab('password')">🔒 Đổi mật khẩu</button>
            </div>
            
            <!-- Tab: Thông tin -->
            <div id="tab-info" class="tab-content active">
                <div class="profile-card">
                    <div class="profile-header">
                        <img src="<?= $user['avatar'] ? ($user['avatar'][0] === '/' ? $base . $user['avatar'] : $base . '/' . $user['avatar']) : $base . '/assets/img/default_avatar.png' ?>" class="profile-avatar" alt="Avatar">
                        <div>
                            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="profile-role">🚚 Shipper</div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">📧 Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📱 Số điện thoại</span>
                        <span class="info-value"><?= $user['phone'] ? htmlspecialchars($user['phone']) : '<em style="color:#999">Chưa cập nhật</em>' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📍 Địa chỉ</span>
                        <span class="info-value"><?= $user['address'] ? htmlspecialchars($user['address']) : '<em style="color:#999">Chưa cập nhật</em>' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📅 Ngày tham gia</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Chỉnh sửa -->
            <div id="tab-edit" class="tab-content">
                <div class="profile-card">
                    <div class="card-title">✏️ Chỉnh sửa thông tin</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div class="avatar-upload">
                                <label for="avatar-input">
                                    <img src="<?= $user['avatar'] ? ($user['avatar'][0] === '/' ? $base . $user['avatar'] : $base . '/' . $user['avatar']) : $base . '/assets/img/default_avatar.png' ?>" class="profile-avatar" alt="Avatar" id="avatar-preview">
                                    <div class="upload-overlay">📷 Đổi ảnh</div>
                                </label>
                                <input type="file" name="avatar" id="avatar-input" accept="image/*" onchange="previewAvatar(this)">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Họ tên *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background: #f5f5f5;">
                            <small style="color: #999;">Email không thể thay đổi</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Số điện thoại</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="0123456789">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Địa chỉ</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ của bạn">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
                    </form>
                </div>
            </div>
            
            <!-- Tab: Đổi mật khẩu -->
            <div id="tab-password" class="tab-content">
                <div class="profile-card">
                    <div class="card-title">🔒 Đổi mật khẩu</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Mật khẩu hiện tại *</label>
                            <input type="password" name="current_password" required placeholder="Nhập mật khẩu hiện tại">
                        </div>
                        
                        <div class="form-group">
                            <label>Mật khẩu mới *</label>
                            <input type="password" name="new_password" required placeholder="Nhập mật khẩu mới (ít nhất 6 ký tự)">
                        </div>
                        
                        <div class="form-group">
                            <label>Xác nhận mật khẩu mới *</label>
                            <input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">🔐 Đổi mật khẩu</button>
                    </form>
                </div>
            </div>
            
            <!-- Nút đăng xuất -->
            <div style="text-align: center; margin-top: 20px;">
                <a href="../auth/logout.php" class="btn btn-secondary">🚪 Đăng xuất</a>
            </div>
        </div>
    </div>
    
    <script>
    function showTab(tabName) {
        // Ẩn tất cả tab content
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        // Bỏ active tất cả tab button
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        // Hiện tab được chọn
        document.getElementById('tab-' + tabName).classList.add('active');
        // Active button
        event.target.classList.add('active');
    }
    
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
