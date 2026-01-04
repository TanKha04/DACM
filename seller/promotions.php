<?php
/**
 * Seller - Quản lý khuyến mãi
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: dashboard.php');
    exit;
}

// Lấy danh sách sản phẩm
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE shop_id = ? AND status = 'active'");
$stmt->execute([$shop['id']]);
$products = $stmt->fetchAll();

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['type'] ?? 'percent';
        $value = (float)($_POST['value'] ?? 0);
        $minOrder = (float)($_POST['min_order'] ?? 0);
        $maxDiscount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $giftProductId = !empty($_POST['gift_product_id']) ? (int)$_POST['gift_product_id'] : null;
        $giftQuantity = (int)($_POST['gift_quantity'] ?? 1);
        $buyQuantity = !empty($_POST['buy_quantity']) ? (int)$_POST['buy_quantity'] : null;
        $usageLimit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        if ($name && $code && $startDate && $endDate) {
            $stmt = $pdo->prepare("SELECT id FROM promotions WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $message = 'error:Mã khuyến mãi đã tồn tại';
            } else {
                $stmt = $pdo->prepare("INSERT INTO promotions (shop_id, name, code, type, value, min_order, max_discount, gift_product_id, gift_quantity, buy_quantity, usage_limit, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$shop['id'], $name, $code, $type, $value, $minOrder, $maxDiscount, $giftProductId, $giftQuantity, $buyQuantity, $usageLimit, $startDate, $endDate]);
                $message = 'success:Tạo khuyến mãi thành công!';
            }
        }
    }
    
    if ($action === 'delete') {
        $promoId = (int)($_POST['promo_id'] ?? 0);
        // Xóa các bản ghi sử dụng trước
        $stmt = $pdo->prepare("DELETE FROM promotion_usage WHERE promotion_id = ?");
        $stmt->execute([$promoId]);
        // Sau đó xóa promotion
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ? AND shop_id = ?");
        $stmt->execute([$promoId, $shop['id']]);
        $message = 'success:Đã xóa khuyến mãi';
    }
    
    if ($action === 'toggle') {
        $promoId = (int)($_POST['promo_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE promotions SET status = IF(status='active','inactive','active') WHERE id = ? AND shop_id = ?");
        $stmt->execute([$promoId, $shop['id']]);
        $message = 'success:Đã cập nhật trạng thái';
    }
}

// Lấy danh sách khuyến mãi
$stmt = $pdo->prepare("SELECT p.*, pr.name as gift_name FROM promotions p LEFT JOIN products pr ON p.gift_product_id = pr.id WHERE p.shop_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$shop['id']]);
$promotions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
    <style>
        .promo-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #27ae60; }
        .promo-card.inactive { border-left-color: #95a5a6; opacity: 0.7; }
        .promo-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .promo-code { background: #27ae60; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
        .promo-value { font-size: 24px; font-weight: bold; color: #e74c3c; }
        .promo-type { display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 11px; margin-left: 10px; background: #e8f5e9; color: #27ae60; }
        .gift-box { background: #fce4ec; padding: 10px 15px; border-radius: 8px; margin: 10px 0; }
        .promo-details { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 15px 0; padding: 15px 0; border-top: 1px solid #eee; }
        .promo-detail .label { color: #7f8c8d; font-size: 12px; }
        .type-fields { display: none; }
        .type-fields.active { display: block; }
    </style>
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🎁 Chương trình khuyến mãi</h1>
            <button class="btn btn-primary" onclick="document.getElementById('promoModal').classList.add('active')">+ Tạo khuyến mãi</button>
        </div>
        
        <?php if ($message): $parts = explode(':', $message, 2); ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if (empty($promotions)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🎁</p>
            <h2>Chưa có khuyến mãi</h2>
            <p style="color: #7f8c8d;">Tạo chương trình khuyến mãi để thu hút khách hàng!</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($promotions as $promo): ?>
        <div class="promo-card <?= $promo['status'] ?>">
            <div class="promo-header">
                <div>
                    <span class="promo-code"><?= htmlspecialchars($promo['code']) ?></span>
                    <span class="promo-type">
                        <?php 
                        switch($promo['type']) {
                            case 'percent': echo '% Giảm giá'; break;
                            case 'fixed': echo '💵 Giảm tiền'; break;
                            case 'freeship': echo '🚚 Free ship'; break;
                            case 'gift': echo '🎁 Tặng kèm'; break;
                            case 'combo': echo '📦 Combo'; break;
                        }
                        ?>
                    </span>
                    <h3 style="margin-top: 10px;"><?= htmlspecialchars($promo['name']) ?></h3>
                </div>
                <div class="promo-value">
                    <?php if ($promo['type'] === 'percent'): ?>
                        -<?= number_format($promo['value']) ?>%
                    <?php elseif ($promo['type'] === 'fixed'): ?>
                        -<?= number_format($promo['value']) ?>đ
                    <?php elseif ($promo['type'] === 'gift' || $promo['type'] === 'combo'): ?>
                        🎁 Tặng quà
                    <?php else: ?>
                        Miễn phí ship
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (($promo['type'] === 'gift' || $promo['type'] === 'combo') && $promo['gift_name']): ?>
            <div class="gift-box">
                🎁 Tặng kèm: <strong><?= htmlspecialchars($promo['gift_name']) ?></strong> (x<?= $promo['gift_quantity'] ?>)
                <?php if ($promo['buy_quantity']): ?>
                - Khi mua <?= $promo['buy_quantity'] ?> sản phẩm
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="promo-details">
                <div class="promo-detail">
                    <div class="label">Đơn tối thiểu</div>
                    <div><?= number_format($promo['min_order']) ?>đ</div>
                </div>
                <div class="promo-detail">
                    <div class="label">Giảm tối đa</div>
                    <div><?= $promo['max_discount'] ? number_format($promo['max_discount']) . 'đ' : '∞' ?></div>
                </div>
                <div class="promo-detail">
                    <div class="label">Đã dùng</div>
                    <div><?= $promo['used_count'] ?> / <?= $promo['usage_limit'] ?: '∞' ?></div>
                </div>
                <div class="promo-detail">
                    <div class="label">Thời gian</div>
                    <div><?= date('d/m/Y', strtotime($promo['start_date'])) ?> - <?= date('d/m/Y', strtotime($promo['end_date'])) ?></div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                    <button type="submit" name="action" value="toggle" class="btn btn-sm <?= $promo['status'] === 'active' ? 'btn-warning' : 'btn-primary' ?>">
                        <?= $promo['status'] === 'active' ? 'Tắt' : 'Bật' ?>
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Xóa?')">Xóa</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal -->
    <div class="modal-overlay" id="promoModal">
        <div class="modal" style="max-width: 550px;">
            <div class="modal-header">
                <h3>Tạo khuyến mãi</h3>
                <span class="modal-close" onclick="document.getElementById('promoModal').classList.remove('active')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Tên chương trình *</label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mã khuyến mãi *</label>
                            <input type="text" name="code" style="text-transform:uppercase" required>
                        </div>
                        <div class="form-group">
                            <label>Loại *</label>
                            <select name="type" id="promoType" onchange="toggleFields()">
                                <option value="percent">% Giảm giá</option>
                                <option value="fixed">💵 Giảm tiền cố định</option>
                                <option value="freeship">🚚 Miễn phí ship</option>
                                <option value="gift">🎁 Tặng kèm sản phẩm</option>
                                <option value="combo">📦 Combo (Mua X tặng Y)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="discountFields" class="type-fields active">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Giá trị giảm</label>
                                <input type="number" name="value" min="0">
                            </div>
                            <div class="form-group">
                                <label>Giảm tối đa (đ)</label>
                                <input type="number" name="max_discount" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div id="giftFields" class="type-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Sản phẩm tặng</label>
                                <select name="gift_product_id">
                                    <option value="">-- Chọn --</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Số lượng tặng</label>
                                <input type="number" name="gift_quantity" value="1" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div id="comboFields" class="type-fields">
                        <div class="form-group">
                            <label>Mua bao nhiêu sản phẩm</label>
                            <input type="number" name="buy_quantity" min="1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Đơn tối thiểu (đ)</label>
                            <input type="number" name="min_order" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Giới hạn lượt dùng</label>
                            <input type="number" name="usage_limit" min="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bắt đầu *</label>
                            <input type="datetime-local" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>Kết thúc *</label>
                            <input type="datetime-local" name="end_date" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width:100%">Tạo khuyến mãi</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleFields() {
        var type = document.getElementById('promoType').value;
        document.querySelectorAll('.type-fields').forEach(f => f.classList.remove('active'));
        
        if (type === 'percent' || type === 'fixed') {
            document.getElementById('discountFields').classList.add('active');
        } else if (type === 'gift') {
            document.getElementById('giftFields').classList.add('active');
        } else if (type === 'combo') {
            document.getElementById('giftFields').classList.add('active');
            document.getElementById('comboFields').classList.add('active');
        }
    }
    
    document.getElementById('promoModal').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
    </script>
</body>
</html>
