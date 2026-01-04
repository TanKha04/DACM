<?php
/**
 * Trang chủ - Redirect theo trạng thái đăng nhập
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Hiển thị trang chủ cho tất cả
header('Location: home.php');
exit;
