<?php
/**
 * Trang chủ - Redirect đến home.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Chuyển đến trang chủ home.php cho tất cả
header('Location: home.php');
exit;
