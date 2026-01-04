<?php
/**
 * Tool tạo mật khẩu hash
 * Truy cập: http://localhost/[folder]/tools/generate_password.php
 */

$password = $_GET['p'] ?? 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tạo mật khẩu Hash</title>
    <style>
        body { font-family: Arial; padding: 50px; background: #f5f5f5; }
        .box { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: auto; }
        input { padding: 10px; width: 200px; margin-right: 10px; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; cursor: pointer; }
        .result { background: #ecf0f1; padding: 15px; margin-top: 20px; word-break: break-all; border-radius: 5px; }
        code { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 Tạo mật khẩu Hash</h2>
        <form method="GET">
            <input type="text" name="p" placeholder="Nhập mật khẩu" value="<?= htmlspecialchars($password) ?>">
            <button type="submit">Tạo Hash</button>
        </form>
        
        <div class="result">
            <p><strong>Mật khẩu:</strong> <code><?= htmlspecialchars($password) ?></code></p>
            <p><strong>Hash (copy vào phpMyAdmin):</strong></p>
            <code><?= $hash ?></code>
        </div>
        
        <p style="margin-top:20px;color:#666;">Copy chuỗi hash ở trên → Dán vào ô password trong phpMyAdmin → Nhấn Go</p>
    </div>
</body>
</html>
