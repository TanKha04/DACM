<?php
/**
 * Trang lỗi 403 - Không có quyền truy cập
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>403 - Không có quyền truy cập</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background: white;
            padding: 50px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        h1 { font-size: 80px; color: #e74c3c; margin: 0; }
        h2 { color: #2c3e50; margin: 20px 0; }
        p { color: #7f8c8d; }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #ff6b35;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Không có quyền truy cập</h2>
        <p>Bạn không có quyền truy cập trang này.</p>
        <a href="/">Về trang chủ</a>
    </div>
</body>
</html>
