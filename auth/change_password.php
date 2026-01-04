<?php
/**
 * Đổi mật khẩu cho người dùng và quản trị
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $message = 'error:Vui lòng nhập đầy đủ thông tin.';
    } elseif ($new !== $confirm) {
        $message = 'error:Mật khẩu mới không khớp.';
    } elseif (strlen($new) < 6) {
        $message = 'error:Mật khẩu mới phải từ 6 ký tự.';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            $message = 'error:Mật khẩu hiện tại không đúng.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $userId]);
            $message = 'success:Đổi mật khẩu thành công!';
        }
    }
}

$base = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .change-password-form { max-width: 400px; margin: 40px auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 18px; }
        .form-group label { font-weight: 500; margin-bottom: 6px; display: block; }
        .form-group input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #e0e0e0; font-size: 15px; }
        .btn-submit { background: #ff6b35; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #e55a2b; }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 18px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="change-password-form">
        <h2 style="text-align:center; margin-bottom:20px;">Đổi mật khẩu</h2>
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Mật khẩu hiện tại</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu mới</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>Nhập lại mật khẩu mới</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">Đổi mật khẩu</button>
        </form>
    </div>
</body>
</html>
