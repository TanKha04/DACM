<?php
/**
 * Auth Helper Functions
 * Các hàm hỗ trợ xác thực và phân quyền
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Kiểm tra người dùng đã đăng nhập chưa
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Kiểm tra có phải admin không (is_admin = 1)
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Lấy thông tin user hiện tại
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'is_admin' => $_SESSION['is_admin'] ?? 0
    ];
}

/**
 * Kiểm tra role của user
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Lấy base URL của project
 */
function getBaseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    while ($scriptDir !== '/' && $scriptDir !== '\\' && !empty($scriptDir)) {
        if (basename($scriptDir) === 'auth' || basename($scriptDir) === 'admin' || 
            basename($scriptDir) === 'seller' || basename($scriptDir) === 'shipper' || 
            basename($scriptDir) === 'customer') {
            return dirname($scriptDir);
        }
        $scriptDir = dirname($scriptDir);
    }
    return dirname($_SERVER['SCRIPT_NAME']);
}

/**
 * Yêu cầu đăng nhập
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $base = getBaseUrl();
        header("Location: $base/auth/login.php");
        exit;
    }
}

/**
 * Yêu cầu quyền admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $base = getBaseUrl();
        header("Location: $base/403.php");
        exit;
    }
}

/**
 * Yêu cầu role cụ thể (cho user thường)
 */
function requireRole($roles) {
    requireLogin();
    // Admin có thể truy cập mọi nơi
    if (isAdmin()) {
        return;
    }
    if (!hasRole($roles)) {
        $base = getBaseUrl();
        header("Location: $base/403.php");
        exit;
    }
}

/**
 * Redirect theo role của user
 */
function redirectByRole() {
    $base = getBaseUrl();
    
    if (!isLoggedIn()) {
        header("Location: $base/auth/login.php");
        exit;
    }
    
    // Nếu là admin -> vào thẳng trang quản trị
    if (isAdmin()) {
        header("Location: $base/admin/dashboard.php");
        exit;
    }
    
    // User thường -> theo role
    switch ($_SESSION['user_role']) {
        case 'seller':
            header("Location: $base/seller/dashboard.php");
            break;
        case 'shipper':
            header("Location: $base/shipper/dashboard.php");
            break;
        default:
            header("Location: $base/customer/index.php");
    }
    exit;
}
