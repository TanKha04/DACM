<?php
/**
 * Admin - Hồ sơ cá nhân
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($name) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $userId]);
            $_SESSION['user_name'] = $name;
            $message = 'success:Cập nhật thông tin thành công!';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
    
    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $newName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newName)) {
                    $avatarPath = 'uploads/avatars/' . $newName;
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$avatarPath, $userId]);
                    $message = 'success:Cập nhật avatar thành công!';
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                }
            } else {
                $message = 'error:Chỉ chấp nhận file ảnh (jpg, png, gif)';
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (password_verify($currentPass, $user['password'])) {
            if ($newPass === $confirmPass && strlen($newPass) >= 6) {
                $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPass, $userId]);
                $message = 'success:Đổi mật khẩu thành công!';
            } else {
                $message = 'error:Mật khẩu mới không khớp hoặc quá ngắn (tối thiểu 6 ký tự)';
            }
        } else {
            $message = 'error:Mật khẩu hiện tại không đúng';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .profile-container { max-width: 800px; }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            border-radius: 20px;
            color: white;
        }
        .avatar-section { text-align: center; }
        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: #ff6b35;
            margin-bottom: 15px;
            overflow: hidden;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload {
            position: relative;
            display: inline-block;
        }
        .avatar-upload input[type="file"] {
            display: none;
        }
        .avatar-upload label {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
        }
        .avatar-upload label:hover {
            background: rgba(255,255,255,0.3);
        }
        .profile-info h2 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        .profile-info p {
            opacity: 0.9;
        }
        .profile-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 13px;
            margin-top: 10px;
        }
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        .profile-tab {
            padding: 12px 24px;
            background: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }
        .profile-tab.active {
            background: #ff6b35;
            color: white;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>👤 Hồ sơ cá nhân</h1>
        </div>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-section">
                    <div class="avatar-large">
                        <?php if ($user['avatar']): ?>
                        <img src="../<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                        <?php else: ?>
                        <?= mb_substr($user['name'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="avatar-upload">
                        <input type="hidden" name="action" value="update_avatar">
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="this.form.submit()">
                        <label for="avatarInput">📷 Đổi avatar</label>
                    </form>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($user['name']) ?></h2>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <span class="profile-badge">👑 Quản trị viên</span>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="profile-tabs">
                <button class="profile-tab active" onclick="showTab('info')">📝 Thông tin</button>
                <button class="profile-tab" onclick="showTab('password')">🔒 Đổi mật khẩu</button>
            </div>
            
            <!-- Tab: Thông tin -->
            <div id="tab-info" class="tab-content active">
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Thông tin cá nhân</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-group">
                            <label>Họ và tên *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background: #f5f5f5;">
                            <small style="color: #999;">Email không thể thay đổi</small>
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Nhập số điện thoại">
                        </div>
                        <div class="form-group">
                            <label>Địa chỉ</label>
                            <textarea name="address" rows="3" placeholder="Nhập địa chỉ"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
                    </form>
                </div>
            </div>
            
            <!-- Tab: Đổi mật khẩu -->
            <div id="tab-password" class="tab-content">
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Đổi mật khẩu</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <label>Mật khẩu hiện tại *</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu mới *</label>
                            <input type="password" name="new_password" required minlength="6">
                            <small style="color: #999;">Tối thiểu 6 ký tự</small>
                        </div>
                        <div class="form-group">
                            <label>Xác nhận mật khẩu mới *</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">🔒 Đổi mật khẩu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.classList.add('active');
    }
    </script>
</body>
</html>
