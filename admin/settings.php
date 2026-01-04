<?php
/**
 * Admin - Cấu hình hệ thống
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';

// Lấy cấu hình hiện tại
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$config = $stmt->fetch();

if (!$config) {
    $pdo->query("INSERT INTO shipping_config (base_fee, price_per_km, default_commission, service_fee) VALUES (15000, 5000, 10, 3000)");
    $stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
    $config = $stmt->fetch();
}

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_shipping') {
        $baseFee = (float)($_POST['base_fee'] ?? 15000);
        $pricePerKm = (float)($_POST['price_per_km'] ?? 5000);
        $pricePerKmFar = (float)($_POST['price_per_km_far'] ?? 7000);
        $peakHourRate = (float)($_POST['peak_hour_rate'] ?? 20);
        $commission = (float)($_POST['default_commission'] ?? 10);
        $serviceFee = (float)($_POST['service_fee'] ?? 3000);
        $freeShipMin = (float)($_POST['free_ship_min'] ?? 200000);
        $maxShopDistance = (int)($_POST['max_shop_distance'] ?? 15);
        
        $stmt = $pdo->prepare("UPDATE shipping_config SET base_fee = ?, price_per_km = ?, price_per_km_far = ?, peak_hour_rate = ?, default_commission = ?, service_fee = ?, free_ship_min = ?, max_shop_distance = ?");
        $stmt->execute([$baseFee, $pricePerKm, $pricePerKmFar, $peakHourRate, $commission, $serviceFee, $freeShipMin, $maxShopDistance]);
        $message = 'success:Đã cập nhật cấu hình';
        
        // Refresh
        $stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
        $config = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cấu hình - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>⚙️ Cấu hình hệ thống</h1>
        </div>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div class="card">
                <h2 style="margin-bottom: 20px;">🚚 Cấu hình phí ship</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_shipping">
                    
                    <div class="form-group">
                        <label>Phí cơ bản (VNĐ)</label>
                        <input type="number" name="base_fee" value="<?= (int)$config['base_fee'] ?>" min="0" step="1000">
                        <small style="color: #7f8c8d;">Phí ship tối thiểu cho mỗi đơn hàng</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phí theo km (VNĐ/km)</label>
                        <input type="number" name="price_per_km" value="<?= (int)$config['price_per_km'] ?>" min="0" step="1000">
                        <small style="color: #7f8c8d;">Phí ship tính theo khoảng cách</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phí km xa (trên 5km) (VNĐ/km)</label>
                        <input type="number" name="price_per_km_far" value="<?= (int)($config['price_per_km_far'] ?? 7000) ?>" min="0" step="1000">
                        <small style="color: #7f8c8d;">Phí ship cho mỗi km sau 5km đầu</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phí giờ cao điểm (%)</label>
                        <input type="number" name="peak_hour_rate" value="<?= (int)($config['peak_hour_rate'] ?? 20) ?>" min="0" max="100">
                        <small style="color: #7f8c8d;">Phụ thu vào giờ cao điểm (11h-13h, 18h-20h)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Hoa hồng mặc định (%)</label>
                        <input type="number" name="default_commission" value="<?= (int)$config['default_commission'] ?>" min="0" max="100">
                        <small style="color: #7f8c8d;">Phần trăm hoa hồng hệ thống thu từ mỗi đơn</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Phí dịch vụ (VNĐ)</label>
                        <input type="number" name="service_fee" value="<?= (int)($config['service_fee'] ?? 3000) ?>" min="0" step="1000">
                        <small style="color: #7f8c8d;">Phí dịch vụ cố định cho mỗi đơn hàng</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Miễn phí ship từ (VNĐ)</label>
                        <input type="number" name="free_ship_min" value="<?= (int)($config['free_ship_min'] ?? 200000) ?>" min="0" step="10000">
                        <small style="color: #7f8c8d;">Đơn hàng từ số tiền này được miễn phí ship</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Khoảng cách hiển thị cửa hàng (km)</label>
                        <input type="number" name="max_shop_distance" value="<?= (int)($config['max_shop_distance'] ?? 15) ?>" min="1" max="100">
                        <small style="color: #7f8c8d;">Chỉ hiển thị cửa hàng trong bán kính này với khách hàng</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                </form>
            </div>
            
            <div class="card">
                <h2 style="margin-bottom: 20px;">📊 Bảng tính phí ship</h2>
                
                <div class="config-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h3>Công thức tính phí ship</h3>
                    <div style="margin-top: 10px; font-size: 14px;">
                        <p><strong>Khoảng cách ≤ 5km:</strong></p>
                        <p style="color: #27ae60; margin-left: 15px;">Phí = <?= number_format($config['base_fee']) ?>đ + (km × <?= number_format($config['price_per_km']) ?>đ)</p>
                        
                        <p style="margin-top: 10px;"><strong>Khoảng cách > 5km:</strong></p>
                        <p style="color: #e67e22; margin-left: 15px;">Phí = <?= number_format($config['base_fee']) ?>đ + (5km × <?= number_format($config['price_per_km']) ?>đ) + (km dư × <?= number_format($config['price_per_km_far'] ?? 7000) ?>đ)</p>
                        
                        <p style="margin-top: 10px;"><strong>Giờ cao điểm (11h-13h, 18h-20h):</strong></p>
                        <p style="color: #e74c3c; margin-left: 15px;">Phí × <?= 100 + ($config['peak_hour_rate'] ?? 20) ?>%</p>
                    </div>
                </div>
                
                <div class="config-card" style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h3>🎁 Miễn phí ship</h3>
                    <p style="margin-top: 10px;">Đơn hàng từ <strong style="color: #27ae60;"><?= number_format($config['free_ship_min'] ?? 200000) ?>đ</strong> được miễn phí ship!</p>
                </div>
                
                <div class="config-card" style="background: #fff3e0; padding: 15px; border-radius: 8px;">
                    <h3>📋 Ví dụ tính phí</h3>
                    <table style="width: 100%; margin-top: 10px; font-size: 14px;">
                        <tr>
                            <td>3km (giờ thường):</td>
                            <td style="text-align: right;"><strong><?= number_format($config['base_fee'] + 3 * $config['price_per_km']) ?>đ</strong></td>
                        </tr>
                        <tr>
                            <td>5km (giờ thường):</td>
                            <td style="text-align: right;"><strong><?= number_format($config['base_fee'] + 5 * $config['price_per_km']) ?>đ</strong></td>
                        </tr>
                        <tr>
                            <td>8km (giờ thường):</td>
                            <td style="text-align: right;"><strong><?= number_format($config['base_fee'] + 5 * $config['price_per_km'] + 3 * ($config['price_per_km_far'] ?? 7000)) ?>đ</strong></td>
                        </tr>
                        <tr>
                            <td>5km (giờ cao điểm):</td>
                            <td style="text-align: right;"><strong style="color: #e74c3c;"><?= number_format(($config['base_fee'] + 5 * $config['price_per_km']) * (100 + ($config['peak_hour_rate'] ?? 20)) / 100) ?>đ</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
