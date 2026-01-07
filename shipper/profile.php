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
                $avatarPath = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $filename = 'shipper_' . $userId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                        $avatarPath = 'uploads/avatars/' . $filename;
                    }
                }
                
                if ($avatarPath) {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=?, avatar=? WHERE id=?");
                    $stmt->execute([$name, $phone, $address, $avatarPath, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?");
                    $stmt->execute([$name, $phone, $address, $userId]);
                }
                $_SESSION['user_name'] = $name;
                $message = 'Cập nhật thông tin thành công!';
            }
        } elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if (!password_verify($currentPassword, $userData['password'])) {
                $error = 'Mật khẩu hiện tại không đúng';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Mật khẩu xác nhận không khớp';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                $message = 'Đổi mật khẩu thành công!';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(shipping_fee), 0) as total_earnings FROM orders WHERE shipper_id = ? AND status = 'delivered'");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

$base = getBaseUrl();
$hour = date('H');
$greeting = $hour < 12 ? 'Chào buổi sáng' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>⚙️ Tài khoản</h1>
            <span style="color: #7f8c8d;"><?= date('d/m/Y H:i') ?></span>
        </div>
        
        <style>
        .profile-banner { background: linear-gradient(135deg, rgba(185,28,28,0.9), rgba(220,38,38,0.85)), url('https://images.unsplash.com/photo-1526367790999-0150786686a2?w=1200&h=400&fit=crop'); background-size: cover; background-position: center; border-radius: 20px; padding: 40px; color: white; margin-bottom: 30px; position: relative; border: 3px solid #fbbf24; }
        .profile-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: transparent; border-radius: 20px; }
        .profile-banner-content { position: relative; z-index: 1; display: flex; align-items: center; gap: 30px; }
        .profile-avatar-large { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .profile-banner-info h2 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .profile-banner-badges { display: flex; gap: 10px; margin-bottom: 10px; }
        .profile-badge { background: rgba(251,191,36,0.3); border: 1px solid #fbbf24; padding: 6px 16px; border-radius: 20px; font-size: 13px; color: #fef3c7; }
        .profile-stats-row { display: flex; gap: 30px; margin-top: 15px; }
        .profile-stat { text-align: center; }
        .profile-stat-value { font-size: 24px; font-weight: bold; }
        .profile-stat-label { font-size: 12px; opacity: 0.9; }
        .profile-container { max-width: 900px; margin: 0 auto; }
        .tabs { display: flex; gap: 5px; background: white; padding: 8px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .tab-btn { flex: 1; padding: 14px 20px; background: transparent; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; color: #666; transition: all 0.3s; }
        .tab-btn:hover { background: #f5f5f5; }
        .tab-btn.active { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .profile-card { background: white; border-radius: 20px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .card-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .info-card { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 15px; padding: 20px; display: flex; align-items: center; gap: 15px; transition: transform 0.3s; }
        .info-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .info-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .info-icon.email { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .info-icon.phone { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .info-icon.address { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .info-icon.date { background: linear-gradient(135deg, #fbbf24, #d97706); }
        .info-content { flex: 1; }
        .info-label { font-size: 12px; color: #666; margin-bottom: 4px; }
        .info-value { font-size: 15px; font-weight: 600; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        .form-group input { width: 100%; padding: 14px 18px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 15px; transition: all 0.3s; background: #f9fafb; }
        .form-group input:focus { outline: none; border-color: #dc2626; background: white; }
        .form-group input:disabled { background: #f3f4f6; color: #9ca3af; }
        .form-group small { color: #9ca3af; font-size: 12px; margin-top: 5px; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .avatar-upload-section { text-align: center; margin-bottom: 30px; padding: 30px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 15px; }
        .avatar-upload { position: relative; display: inline-block; }
        .avatar-upload input[type="file"] { display: none; }
        .avatar-upload label { display: block; cursor: pointer; }
        .avatar-upload .profile-avatar-edit { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 5px 20px rgba(0,0,0,0.15); transition: transform 0.3s; }
        .avatar-upload:hover .profile-avatar-edit { transform: scale(1.05); }
        .avatar-upload .upload-overlay { position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .avatar-hint { color: #666; font-size: 13px; margin-top: 15px; }
        .btn { padding: 14px 30px; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #f87171); color: white; }
        .btn-danger:hover { transform: translateY(-2px); }
        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border: 1px solid #fca5a5; }
        .logout-section { text-align: center; padding: 30px; background: white; border-radius: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .logout-section p { color: #666; margin-bottom: 20px; }
        </style>
        
        <div class="profile-container">
            <?php if ($message): ?><div class="alert alert-success">✅ <?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">❌ <?= $error ?></div><?php endif; ?>
            
            <div class="profile-banner">
                <div class="profile-banner-content">
                    <img src="<?= $user['avatar'] ? $base . '/' . $user['avatar'] : $base . '/assets/img/default_avatar.png' ?>" class="profile-avatar-large" alt="Avatar">
                    <div class="profile-banner-info">
                        <h2><?= $greeting ?>, <?= htmlspecialchars($user['name']) ?>!</h2>
                        <div class="profile-banner-badges">
                            <span class="profile-badge">🚚 Shipper</span>
                            <span class="profile-badge">✅ Đã xác minh</span>
                        </div>
                        <div class="profile-stats-row">
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?= number_format($stats['total_orders']) ?></div>
                                <div class="profile-stat-label">Đơn đã giao</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?= number_format($stats['total_earnings']) ?>đ</div>
                                <div class="profile-stat-label">Tổng thu nhập</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('info', this)">📋 Thông tin</button>
                <button class="tab-btn" onclick="showTab('edit', this)">✏️ Chỉnh sửa</button>
                <button class="tab-btn" onclick="showTab('password', this)">🔒 Đổi mật khẩu</button>
            </div>
            
            <div id="tab-info" class="tab-content active">
                <div class="profile-card">
                    <div class="card-title">📋 Thông tin cá nhân</div>
                    <div class="info-grid">
                        <div class="info-card"><div class="info-icon email">📧</div><div class="info-content"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($user['email']) ?></div></div></div>
                        <div class="info-card"><div class="info-icon phone">📱</div><div class="info-content"><div class="info-label">Số điện thoại</div><div class="info-value"><?= $user['phone'] ?: 'Chưa cập nhật' ?></div></div></div>
                        <div class="info-card"><div class="info-icon address">📍</div><div class="info-content"><div class="info-label">Địa chỉ</div><div class="info-value"><?= $user['address'] ?: 'Chưa cập nhật' ?></div></div></div>
                        <div class="info-card"><div class="info-icon date">📅</div><div class="info-content"><div class="info-label">Ngày tham gia</div><div class="info-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div></div></div>
                    </div>
                </div>
            </div>
            
            <div id="tab-edit" class="tab-content">
                <div class="profile-card">
                    <div class="card-title">✏️ Chỉnh sửa thông tin</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="avatar-upload-section">
                            <div class="avatar-upload">
                                <label for="avatar-input">
                                    <img src="<?= $user['avatar'] ? $base . '/' . $user['avatar'] : $base . '/assets/img/default_avatar.png' ?>" class="profile-avatar-edit" alt="Avatar" id="avatar-preview">
                                    <div class="upload-overlay">📷 Đổi ảnh</div>
                                </label>
                                <input type="file" name="avatar" id="avatar-input" accept="image/*" onchange="previewAvatar(this)">
                            </div>
                            <p class="avatar-hint">Click vào ảnh để thay đổi avatar</p>
                        </div>
                        <div class="form-group"><label>👤 Họ tên *</label><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                        <div class="form-group"><label>📧 Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled><small>Email không thể thay đổi</small></div>
                        <div class="form-row">
                            <div class="form-group"><label>📱 Số điện thoại</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="0123456789"></div>
                            <div class="form-group"><label>📍 Địa chỉ</label><input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ"></div>
                        </div>
                        <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
                    </form>
                </div>
            </div>
            
            <div id="tab-password" class="tab-content">
                <div class="profile-card">
                    <div class="card-title">🔒 Đổi mật khẩu</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group"><label>🔑 Mật khẩu hiện tại *</label><input type="password" name="current_password" required placeholder="Nhập mật khẩu hiện tại"></div>
                        <div class="form-group"><label>🔐 Mật khẩu mới *</label><input type="password" name="new_password" required placeholder="Ít nhất 6 ký tự"></div>
                        <div class="form-group"><label>🔐 Xác nhận mật khẩu *</label><input type="password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới"></div>
                        <button type="submit" class="btn btn-primary">🔐 Đổi mật khẩu</button>
                    </form>
                </div>
            </div>
            
            <div class="logout-section">
                <p>Bạn muốn đăng xuất khỏi tài khoản?</p>
                <a href="../auth/logout.php" class="btn btn-danger">🚪 Đăng xuất</a>
            </div>
        </div>
    </div>
    
    <script>
    function showTab(tabName, btn) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        btn.classList.add('active');
    }
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
