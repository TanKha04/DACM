<?php
/**
 * Trang Đăng xuất
 * Hủy session và redirect về trang login
 */

require_once __DIR__ . '/../config/database.php';

// Hủy tất cả session
$_SESSION = [];

// Xóa cookie session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Redirect về trang login
header('Location: login.php');
exit;
