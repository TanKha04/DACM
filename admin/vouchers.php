<?php
/**
 * Admin - Quản lý Voucher & Khuyến mãi
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';
$tab = $_GET['tab'] ?? 'vouchers';

// Kiểm tra và tạo bảng vouchers nếu chưa có
try {
    $pdo->query("SELECT 1 FROM vouchers LIMIT 1");
} catch (PDOException $e) {
    // Tạo bảng vouchers nếu chưa tồn tại
    $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        type ENUM('percent', 'fixed', 'freeship') DEFAULT 'percent',
        value DECIMAL(10, 2) DEFAULT 0,
        min_order DECIMAL(10, 2) DEFAULT 0,
        max_discount DECIMAL(10, 2) DEFAULT NULL,
        usage_limit INT DEFAULT NULL,
        used_count INT DEFAULT 0,
        user_limit INT DEFAULT 1,
        apply_to ENUM('all', 'new_user', 'vip') DEFAULT 'all',
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Voucher actions
    if ($action === 'add_voucher') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'percent';
        // Nếu là freeship thì value = 0
        $value = ($type === 'freeship') ? 0 : (float)($_POST['value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $usageLimit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $userLimit = (int)($_POST['user_limit'] ?? 1);
        $applyTo = $_POST['apply_to'] ?? 'all';
        // Chuyển đổi format datetime-local sang MySQL datetime
        $startDate = !empty($_POST['start_date']) ? date('Y-m-d H:i:s', strtotime($_POST['start_date'])) : '';
        $endDate = !empty($_POST['end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['end_date'])) : '';
        
        if ($name && $code && $startDate && $endDate) {
            $stmt = $pdo->prepare("SELECT id FROM vouchers WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $message = 'error:Mã voucher đã tồn tại';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO vouchers (name, code, type, value, min_order, max_discount, usage_limit, user_limit, apply_to, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$name, $code, $type, $value, $minOrder, $maxDiscount, $usageLimit, $userLimit, $applyTo, $startDate, $endDate]);
                    $message = 'success:Tạo voucher thành công!';
                } catch (PDOException $e) {
                    $message = 'error:Lỗi tạo voucher: ' . $e->getMessage();
                }
            }
        } else {
            $message = 'error:Vui lòng điền đầy đủ thông tin bắt buộc';
        }
    }
    
    if ($action === 'delete_voucher') {
        $id = (int)($_POST['voucher_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'success:Đã xóa voucher';
    }
    
    if ($action === 'toggle_voucher') {
        $id = (int)($_POST['voucher_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE vouchers SET status = IF(status='active','inactive','active') WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'success:Đã cập nhật trạng thái';
    }
    
    // Promotion actions
    if ($action === 'toggle_promo') {
        $id = (int)($_POST['promo_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE promotions SET status = IF(status='active','inactive','active') WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'success:Đã cập nhật trạng thái khuyến mãi';
        $tab = 'promotions';
    }
    
    if ($action === 'delete_promo') {
        $id = (int)($_POST['promo_id'] ?? 0);
        // Xóa các bản ghi sử dụng khuyến mãi trước
        $stmt = $pdo->prepare("DELETE FROM promotion_usage WHERE promotion_id = ?");
        $stmt->execute([$id]);
        // Sau đó xóa khuyến mãi
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'success:Đã xóa khuyến mãi';
        $tab = 'promotions';
    }
}

// Lấy vouchers
$vouchers = $pdo->query("SELECT * FROM vouchers ORDER BY created_at DESC")->fetchAll();

// Lấy promotions của tất cả shops
$promotions = $pdo->query("SELECT p.*, s.name as shop_name FROM promotions p JOIN shops s ON p.shop_id = s.id ORDER BY p.created_at DESC")->fetchAll();

// Thống kê
$stats = [
    'total_vouchers' => count($vouchers),
    'active_vouchers' => count(array_filter($vouchers, fn($v) => $v['status'] === 'active')),
    'total_promos' => count($promotions),
    'active_promos' => count(array_filter($promotions, fn($p) => $p['status'] === 'active'))
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher & Khuyến mãi - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .voucher-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #9b59b6; position: relative; }
        .voucher-card.inactive { border-left-color: #95a5a6; opacity: 0.7; }
        .voucher-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .voucher-code { background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 8px 20px; border-radius: 25px; font-weight: bold; font-size: 16px; letter-spacing: 1px; }
        .voucher-value { font-size: 28px; font-weight: bold; color: #e74c3c; }
        .voucher-type { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 11px; margin-left: 10px; }
        .voucher-type.percent { background: #e8f5e9; color: #27ae60; }
        .voucher-type.fixed { background: #fff3e0; color: #f39c12; }
        .voucher-type.freeship { background: #e3f2fd; color: #3498db; }
        .voucher-details { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin: 15px 0; padding: 15px 0; border-top: 1px solid #eee; }
        .voucher-detail .label { color: #7f8c8d; font-size: 12px; }
        .voucher-detail .value { font-weight: 600; color: #2c3e50; }
        .apply-badge { position: absolute; top: 15px; right: 15px; padding: 4px 10px; border-radius: 12px; font-size: 11px; }
        .apply-badge.all { background: #e8f5e9; color: #27ae60; }
        .apply-badge.new_user { background: #fff3e0; color: #f39c12; }
        .apply-badge.vip { background: #fce4ec; color: #e91e63; }
        .promo-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #27ae60; }
        .promo-card.inactive { border-left-color: #95a5a6; opacity: 0.7; }
        .shop-badge { background: #3498db; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px; }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🎫 Voucher & Khuyến mãi</h1>
            <?php if ($tab === 'vouchers'): ?>
            <button class="btn btn-primary" onclick="document.getElementById('voucherModal').classList.add('active')">+ Tạo Voucher</button>
            <?php endif; ?>
        </div>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">🎫</div>
                <div class="value"><?= $stats['total_vouchers'] ?></div>
                <div class="label">Tổng Voucher</div>
            </div>
            <div class="stat-card green">
                <div class="icon">✅</div>
                <div class="value"><?= $stats['active_vouchers'] ?></div>
                <div class="label">Voucher đang hoạt động</div>
            </div>
            <div class="stat-card orange">
                <div class="icon">🎁</div>
                <div class="value"><?= $stats['total_promos'] ?></div>
                <div class="label">Tổng KM Shop</div>
            </div>
            <div class="stat-card">
                <div class="icon">🏪</div>
                <div class="value"><?= $stats['active_promos'] ?></div>
                <div class="label">KM Shop đang hoạt động</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=vouchers" class="tab <?= $tab === 'vouchers' ? 'active' : '' ?>">🎫 Voucher hệ thống <span class="count"><?= $stats['total_vouchers'] ?></span></a>
            <a href="?tab=promotions" class="tab <?= $tab === 'promotions' ? 'active' : '' ?>">🎁 Khuyến mãi Shop <span class="count"><?= $stats['total_promos'] ?></span></a>
        </div>

        <?php if ($tab === 'vouchers'): ?>
        <!-- Vouchers Tab -->
        <?php if (empty($vouchers)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🎫</p>
            <h2>Chưa có voucher</h2>
            <p style="color: #7f8c8d;">Tạo voucher để thu hút khách hàng mới!</p>
        </div>
        <?php else: ?>
        <?php foreach ($vouchers as $v): ?>
        <div class="voucher-card <?= $v['status'] ?>">
            <span class="apply-badge <?= $v['apply_to'] ?>">
                <?php 
                switch($v['apply_to']) {
                    case 'all': echo '👥 Tất cả'; break;
                    case 'new_user': echo '🆕 Khách mới'; break;
                    case 'vip': echo '⭐ VIP'; break;
                }
                ?>
            </span>
            <div class="voucher-header">
                <div>
                    <span class="voucher-code"><?= htmlspecialchars($v['code']) ?></span>
                    <span class="voucher-type <?= $v['type'] ?>">
                        <?php 
                        switch($v['type']) {
                            case 'percent': echo '% Giảm giá'; break;
                            case 'fixed': echo '💵 Giảm tiền'; break;
                            case 'freeship': echo '🚚 Free ship'; break;
                        }
                        ?>
                    </span>
                    <h3 style="margin-top: 10px;"><?= htmlspecialchars($v['name']) ?></h3>
                </div>
                <div class="voucher-value">
                    <?php if ($v['type'] === 'percent'): ?>
                        -<?= number_format($v['value']) ?>%
                    <?php elseif ($v['type'] === 'fixed'): ?>
                        -<?= number_format($v['value']) ?>đ
                    <?php else: ?>
                        Miễn phí ship
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="voucher-details">
                <div class="voucher-detail">
                    <div class="label">Đơn tối thiểu</div>
                    <div class="value"><?= number_format($v['min_order']) ?>đ</div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Giảm tối đa</div>
                    <div class="value"><?= $v['max_discount'] ? number_format($v['max_discount']) . 'đ' : '∞' ?></div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Đã dùng</div>
                    <div class="value"><?= $v['used_count'] ?> / <?= $v['usage_limit'] ?: '∞' ?></div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Giới hạn/user</div>
                    <div class="value"><?= $v['user_limit'] ?> lần</div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Thời gian</div>
                    <div class="value"><?= date('d/m/Y', strtotime($v['start_date'])) ?> - <?= date('d/m/Y', strtotime($v['end_date'])) ?></div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                    <button type="submit" name="action" value="toggle_voucher" class="btn btn-sm <?= $v['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                        <?= $v['status'] === 'active' ? '⏸ Tắt' : '▶ Bật' ?>
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                    <button type="submit" name="action" value="delete_voucher" class="btn btn-sm btn-danger" onclick="return confirm('Xóa voucher này?')">🗑 Xóa</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Promotions Tab -->
        <?php if (empty($promotions)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🎁</p>
            <h2>Chưa có khuyến mãi từ Shop</h2>
            <p style="color: #7f8c8d;">Các shop chưa tạo chương trình khuyến mãi nào.</p>
        </div>
        <?php else: ?>
        <?php foreach ($promotions as $p): ?>
        <div class="promo-card <?= $p['status'] ?>">
            <div class="voucher-header">
                <div>
                    <span class="shop-badge">🏪 <?= htmlspecialchars($p['shop_name']) ?></span>
                    <span class="voucher-code" style="background: linear-gradient(135deg, #27ae60, #2ecc71); margin-left: 10px;"><?= htmlspecialchars($p['code']) ?></span>
                    <span class="voucher-type <?= $p['type'] ?>">
                        <?php 
                        switch($p['type']) {
                            case 'percent': echo '% Giảm giá'; break;
                            case 'fixed': echo '💵 Giảm tiền'; break;
                            case 'freeship': echo '🚚 Free ship'; break;
                            case 'gift': echo '🎁 Tặng kèm'; break;
                            case 'combo': echo '📦 Combo'; break;
                        }
                        ?>
                    </span>
                    <h3 style="margin-top: 10px;"><?= htmlspecialchars($p['name']) ?></h3>
                </div>
                <div class="voucher-value">
                    <?php if ($p['type'] === 'percent'): ?>
                        -<?= number_format($p['value']) ?>%
                    <?php elseif ($p['type'] === 'fixed'): ?>
                        -<?= number_format($p['value']) ?>đ
                    <?php elseif ($p['type'] === 'gift' || $p['type'] === 'combo'): ?>
                        🎁
                    <?php else: ?>
                        Free ship
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="voucher-details" style="grid-template-columns: repeat(4, 1fr);">
                <div class="voucher-detail">
                    <div class="label">Đơn tối thiểu</div>
                    <div class="value"><?= number_format($p['min_order']) ?>đ</div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Giảm tối đa</div>
                    <div class="value"><?= $p['max_discount'] ? number_format($p['max_discount']) . 'đ' : '∞' ?></div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Đã dùng</div>
                    <div class="value"><?= $p['used_count'] ?> / <?= $p['usage_limit'] ?: '∞' ?></div>
                </div>
                <div class="voucher-detail">
                    <div class="label">Thời gian</div>
                    <div class="value"><?= date('d/m/Y', strtotime($p['start_date'])) ?> - <?= date('d/m/Y', strtotime($p['end_date'])) ?></div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                    <button type="submit" name="action" value="toggle_promo" class="btn btn-sm <?= $p['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                        <?= $p['status'] === 'active' ? '⏸ Tắt' : '▶ Bật' ?>
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                    <button type="submit" name="action" value="delete_promo" class="btn btn-sm btn-danger" onclick="return confirm('Xóa khuyến mãi này?')">🗑 Xóa</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Tạo Voucher -->
    <div class="modal-overlay" id="voucherModal">
        <div class="modal" style="max-width: 550px;">
            <div class="modal-header">
                <h3>🎫 Tạo Voucher mới</h3>
                <span class="modal-close" onclick="document.getElementById('voucherModal').classList.remove('active')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_voucher">
                    
                    <div class="form-group">
                        <label>Tên voucher *</label>
                        <input type="text" name="name" placeholder="VD: Giảm 50% cho khách mới" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mã voucher *</label>
                            <input type="text" name="code" style="text-transform:uppercase" placeholder="VD: NEWUSER50" required>
                        </div>
                        <div class="form-group">
                            <label>Loại giảm giá *</label>
                            <select name="type" id="voucherType" onchange="toggleVoucherFields()">
                                <option value="percent">% Giảm theo phần trăm</option>
                                <option value="fixed">💵 Giảm số tiền cố định</option>
                                <option value="freeship">🚚 Miễn phí vận chuyển</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="valueFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Giá trị giảm *</label>
                                <input type="number" name="value" id="voucherValue" min="0" placeholder="VD: 50 (cho 50%)">
                            </div>
                            <div class="form-group">
                                <label>Giảm tối đa (đ)</label>
                                <input type="number" name="max_discount" min="0" placeholder="Để trống = không giới hạn">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Đơn tối thiểu (đ)</label>
                            <input type="number" name="min_order" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Áp dụng cho</label>
                            <select name="apply_to">
                                <option value="all">👥 Tất cả khách hàng</option>
                                <option value="new_user">🆕 Khách hàng mới</option>
                                <option value="vip">⭐ Khách VIP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tổng lượt dùng</label>
                            <input type="number" name="usage_limit" min="0" placeholder="Để trống = không giới hạn">
                        </div>
                        <div class="form-group">
                            <label>Giới hạn/người dùng</label>
                            <input type="number" name="user_limit" value="1" min="1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ngày bắt đầu *</label>
                            <input type="datetime-local" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>Ngày kết thúc *</label>
                            <input type="datetime-local" name="end_date" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%">🎫 Tạo Voucher</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleVoucherFields() {
        var type = document.getElementById('voucherType').value;
        var valueFields = document.getElementById('valueFields');
        var valueInput = document.getElementById('voucherValue');
        if (type === 'freeship') {
            valueFields.style.display = 'none';
            valueInput.removeAttribute('required');
        } else {
            valueFields.style.display = 'block';
            valueInput.setAttribute('required', 'required');
        }
    }
    
    // Khởi tạo khi load trang
    document.addEventListener('DOMContentLoaded', function() {
        toggleVoucherFields();
    });
    
    document.getElementById('voucherModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
    </script>
</body>
</html>
