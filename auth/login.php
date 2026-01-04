<?php
/**
 * Trang Đăng nhập / Đăng ký
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'login';
$registerType = $_GET['type'] ?? '';

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getConnection();
    
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ thông tin';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'blocked') {
                    $error = 'Tài khoản đã bị khóa';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['user_lat'] = $user['lat'];
                    $_SESSION['user_lng'] = $user['lng'];
                    
                    // Nếu là customer và chưa có vị trí, redirect đến trang set location
                    if ($user['role'] === 'customer' && (!$user['lat'] || !$user['lng'])) {
                        header('Location: ../customer/set_location.php');
                        exit;
                    }
                    
                    redirectByRole();
                }
            } else {
                $error = 'Email hoặc mật khẩu không đúng';
            }
        }
    }
    
    if ($_POST['action'] === 'register') {
        $type = $_POST['register_type'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($email) || empty($name) || empty($password)) {
            $error = 'Vui lòng điền đầy đủ thông tin';
            $activeTab = 'register';
            $registerType = $type;
        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp';
            $activeTab = 'register';
            $registerType = $type;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email đã được sử dụng';
                $activeTab = 'register';
                $registerType = $type;
            } else {
                $role = ($type === 'seller') ? 'seller' : 'customer';
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $email, $hashedPassword, $role]);
                $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
                $activeTab = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FastFood Delivery - Đặt món ngon, giao nhanh</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; }
        
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.9), rgba(42, 82, 152, 0.85)),
                        url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1920') center/cover;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 50px;
            background: linear-gradient(90deg, #1e3c72, #2a5298);
        }
        .logo { display: flex; align-items: center; gap: 10px; color: white; font-size: 20px; font-weight: bold; }
        .logo img { width: 40px; height: 40px; }
        .header-btns { display: flex; gap: 15px; }
        .btn-login-header { color: white; text-decoration: none; padding: 10px 20px; display: flex; align-items: center; gap: 8px; }
        .btn-register-header { background: #00bcd4; color: white; padding: 10px 25px; border-radius: 25px; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 50px;
        }
        .content-left { flex: 1; color: white; }
        .subtitle { font-size: 14px; letter-spacing: 3px; margin-bottom: 15px; opacity: 0.9; }
        .title { font-size: 48px; font-weight: bold; line-height: 1.2; margin-bottom: 40px; font-style: italic; }
        
        .action-btns { display: flex; gap: 20px; margin-bottom: 30px; }
        .btn-primary { background: #f5a623; color: white; padding: 15px 40px; border: none; border-radius: 30px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-outline { background: transparent; color: white; padding: 15px 40px; border: 2px solid white; border-radius: 30px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-primary:hover { background: #e09000; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }
        
        .footer-links { display: flex; gap: 30px; }
        .footer-links a { color: white; text-decoration: none; font-size: 14px; opacity: 0.9; }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        
        .modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 15px; right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        .modal-header {
            padding: 30px 30px 20px;
            text-align: center;
        }
        .modal-header h2 { color: #1e3c72; font-size: 24px; }
        
        .modal-body { padding: 0 30px 30px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
        }
        .form-group input:focus { outline: none; border-color: #1e3c72; }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(90deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover { opacity: 0.9; }
        
        .switch-text { text-align: center; margin-top: 20px; color: #666; }
        .switch-text a { color: #1e3c72; font-weight: 600; cursor: pointer; }
        
        .error-msg { background: #ffe6e6; color: #d63031; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success-msg { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        
        /* Register Type Selection */
        .register-types {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .register-type-card {
            flex: 1;
            padding: 25px 20px;
            border: 2px solid #ddd;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .register-type-card:hover { border-color: #1e3c72; }
        .register-type-card.active { border-color: #1e3c72; background: #f0f5ff; }
        .register-type-card .icon { font-size: 40px; margin-bottom: 10px; }
        .register-type-card h3 { color: #1e3c72; margin-bottom: 5px; }
        .register-type-card p { color: #666; font-size: 13px; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="header">
            <div class="logo">
                <span>🍔</span>
                <span>FastFood Delivery</span>
            </div>
            <div class="header-btns">
                <a href="#" class="btn-login-header" onclick="openModal('login')">👤 Đăng nhập</a>
                <a href="#" class="btn-register-header" onclick="openModal('register')">✨ Đăng ký</a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="content-left">
                <p class="subtitle">FASTFOOD DELIVERY</p>
                <h1 class="title">Đặt Món Ngon<br>Giao Nhanh</h1>
                
                <div class="action-btns">
                    <button class="btn-primary" onclick="openModal('login')">ĐĂNG NHẬP</button>
                    <button class="btn-outline" onclick="openModal('register')">ĐĂNG KÝ</button>
                </div>
                
                <div class="footer-links">
                    <a href="#">⇔ Quên mật khẩu?</a>
                    <a href="#">📘 Facebook</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Đăng nhập -->
    <div class="modal-overlay" id="loginModal">
        <div class="modal">
            <span class="modal-close" onclick="closeModal('login')">&times;</span>
            <div class="modal-header">
                <h2>Đăng nhập</h2>
            </div>
            <div class="modal-body">
                <?php if ($error && $activeTab === 'login'): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-msg"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Nhập email của bạn" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                    <button type="submit" class="btn-submit">Đăng nhập</button>
                </form>
                
                <p class="switch-text">Chưa có tài khoản? <a onclick="switchModal('register')">Đăng ký ngay</a></p>
            </div>
        </div>
    </div>

    <!-- Modal Đăng ký - Chọn loại -->
    <div class="modal-overlay" id="registerModal">
        <div class="modal">
            <span class="modal-close" onclick="closeModal('register')">&times;</span>
            <div class="modal-header">
                <h2>Đăng ký tài khoản</h2>
            </div>
            <div class="modal-body">
                <p style="text-align:center; color:#666; margin-bottom:25px;">Chọn loại tài khoản bạn muốn đăng ký</p>
                
                <div class="register-types">
                    <div class="register-type-card" onclick="openRegisterForm('buyer')">
                        <div class="icon">🛒</div>
                        <h3>Người mua</h3>
                        <p>Đặt món ăn yêu thích</p>
                    </div>
                    <div class="register-type-card" onclick="openRegisterForm('seller')">
                        <div class="icon">🏪</div>
                        <h3>Người bán</h3>
                        <p>Mở cửa hàng bán đồ ăn</p>
                    </div>
                </div>
                
                <p class="switch-text">Đã có tài khoản? <a onclick="switchModal('login')">Đăng nhập</a></p>
            </div>
        </div>
    </div>
    
    <!-- Modal Đăng ký Người mua -->
    <div class="modal-overlay" id="registerBuyerModal">
        <div class="modal">
            <span class="modal-close" onclick="closeModal('registerBuyer')">&times;</span>
            <div class="modal-header">
                <h2>🛒 Đăng ký Người mua</h2>
            </div>
            <div class="modal-body">
                <?php if ($error && $registerType === 'buyer'): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="register_type" value="customer">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Nhập email" required>
                    </div>
                    <div class="form-group">
                        <label>Tên tài khoản</label>
                        <input type="text" name="name" placeholder="Nhập tên của bạn" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                    <div class="form-group">
                        <label>Xác nhận mật khẩu</label>
                        <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <button type="submit" class="btn-submit">Đăng ký</button>
                </form>
                
                <p class="switch-text"><a onclick="switchModal('register')">← Quay lại</a></p>
            </div>
        </div>
    </div>
    
    <!-- Modal Đăng ký Người bán -->
    <div class="modal-overlay" id="registerSellerModal">
        <div class="modal">
            <span class="modal-close" onclick="closeModal('registerSeller')">&times;</span>
            <div class="modal-header">
                <h2>🏪 Đăng ký Người bán</h2>
            </div>
            <div class="modal-body">
                <?php if ($error && $registerType === 'seller'): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="register_type" value="seller">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Nhập email" required>
                    </div>
                    <div class="form-group">
                        <label>Tên tài khoản</label>
                        <input type="text" name="name" placeholder="Nhập tên của bạn" required>
                    </div>
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                    <div class="form-group">
                        <label>Xác nhận mật khẩu</label>
                        <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <button type="submit" class="btn-submit">Đăng ký</button>
                </form>
                
                <p class="switch-text"><a onclick="switchModal('register')">← Quay lại</a></p>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(type) {
            closeAllModals();
            document.getElementById(type + 'Modal').classList.add('active');
        }
        
        function closeModal(type) {
            document.getElementById(type + 'Modal').classList.remove('active');
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        }
        
        function switchModal(type) {
            closeAllModals();
            openModal(type);
        }
        
        function openRegisterForm(type) {
            closeAllModals();
            if (type === 'buyer') {
                openModal('registerBuyer');
            } else {
                openModal('registerSeller');
            }
        }
        
        // Auto open modal if there's error or success
        <?php if ($error || $success): ?>
            <?php if ($activeTab === 'login'): ?>
                openModal('login');
            <?php elseif ($registerType === 'buyer' || $registerType === 'customer'): ?>
                openModal('registerBuyer');
            <?php elseif ($registerType === 'seller'): ?>
                openModal('registerSeller');
            <?php endif; ?>
        <?php endif; ?>
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
