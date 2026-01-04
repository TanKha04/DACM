<?php
/**
 * Tài khoản Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('shipper');

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
    <title>Tài khoản Shipper</title>
    <link rel="stylesheet" href="../assets/css/shipper.css">
    <style>
        .profile-card { background: white; border-radius: 15px; padding: 30px; max-width: 500px; margin: 40px auto; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 20px; }
        .profile-info { font-size: 16px; color: #333; }
        .profile-info strong { color: #ff6b35; }
        .profile-actions { margin-top: 30px; text-align: center; }
        .btn-edit { background: #ff6b35; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; cursor: pointer; }
        .btn-edit:hover { background: #e55a2b; }
    </style>
</head>
<body>
    <?php include '../includes/shipper_sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>⚙️ Tài khoản</h1>
        </div>
        <div class="profile-card">
            <div style="text-align:center;">
                <img src="<?= $user['avatar'] ? ($user['avatar'][0] === '/' ? $base . $user['avatar'] : $base . '/' . $user['avatar']) : $base . '/assets/img/default_avatar.png' ?>" class="profile-avatar" alt="Avatar">
            </div>
            <div class="profile-info">
                <p><strong>Họ tên:</strong> <?= htmlspecialchars($user['name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($user['phone']) ?></p>
                <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($user['address']) ?></p>
            </div>
            <div class="profile-actions">
                <a href="../auth/logout.php" class="btn-edit">Đăng xuất</a>
            </div>
        </div>
    </div>
</body>
</html>
