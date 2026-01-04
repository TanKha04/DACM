<?php
/**
 * Giỏ hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Kiểm tra thông báo lỗi từ checkout
if (isset($_SESSION['checkout_error'])) {
    $message = 'error:' . $_SESSION['checkout_error'];
    unset($_SESSION['checkout_error']);
}

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        // Kiểm tra seller không thể mua sản phẩm của chính mình
        $stmt = $pdo->prepare("SELECT p.shop_id, s.user_id FROM products p JOIN shops s ON p.shop_id = s.id WHERE p.id = ?");
        $stmt->execute([$productId]);
        $productInfo = $stmt->fetch();
        
        if ($productInfo && $productInfo['user_id'] == $userId) {
            $message = 'error:Bạn không thể đặt hàng sản phẩm của chính mình!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
            $stmt->execute([$userId, $productId, $quantity, $quantity]);
            $message = 'success:Đã thêm vào giỏ hàng!';
        }
    }
    
    if ($action === 'update') {
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($quantity > 0) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cartId, $userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartId, $userId]);
        }
    }
    
    if ($action === 'remove') {
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartId, $userId]);
        $message = 'success:Đã xóa khỏi giỏ hàng!';
    }
    
    if ($action === 'clear') {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        // Xóa cả combo
        try {
            $stmt = $pdo->prepare("DELETE FROM cart_combos WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {}
        $message = 'success:Đã xóa toàn bộ giỏ hàng!';
    }
    
    // Xóa combo
    if ($action === 'remove_combo') {
        $cartComboId = (int)($_POST['cart_combo_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM cart_combos WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartComboId, $userId]);
        $message = 'success:Đã xóa combo khỏi giỏ hàng!';
    }
    
    // Cập nhật số lượng combo
    if ($action === 'update_combo') {
        $cartComboId = (int)($_POST['cart_combo_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($quantity > 0) {
            $stmt = $pdo->prepare("UPDATE cart_combos SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cartComboId, $userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart_combos WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartComboId, $userId]);
        }
    }
}

// Lấy giỏ hàng sản phẩm
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image, s.name as shop_name, s.id as shop_id, s.latitude as shop_lat, s.longitude as shop_lng
                       FROM cart c 
                       JOIN products p ON c.product_id = p.id 
                       JOIN shops s ON p.shop_id = s.id 
                       WHERE c.user_id = ?");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// Lấy giỏ hàng combo
$cartCombos = [];
try {
    $stmt = $pdo->prepare("SELECT cc.*, c.name, c.combo_price, c.original_price, c.image, s.name as shop_name, s.id as shop_id, s.latitude as shop_lat, s.longitude as shop_lng,
                           (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', ci.quantity) SEPARATOR ', ') FROM combo_items ci JOIN products p ON ci.product_id = p.id WHERE ci.combo_id = c.id) as items_text
                           FROM cart_combos cc 
                           JOIN combos c ON cc.combo_id = c.id 
                           JOIN shops s ON c.shop_id = s.id 
                           WHERE cc.user_id = ?");
    $stmt->execute([$userId]);
    $cartCombos = $stmt->fetchAll();
} catch (PDOException $e) {
    // Bảng chưa tồn tại
}

// Lấy thông tin user để tính khoảng cách (dùng địa chỉ mặc định nếu có)
$userLocation = ['lat' => null, 'lng' => null];
$stmt = $pdo->prepare("SELECT latitude as lat, longitude as lng FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$userId]);
$defaultAddr = $stmt->fetch();
if ($defaultAddr) {
    $userLocation = $defaultAddr;
}

// Lấy cấu hình phí ship
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$shippingConfig = $stmt->fetch();

// Hàm tính khoảng cách
function haversineCart($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// Hàm tính phí ship
function calcShipFee($distance, $config, $subtotal) {
    $baseFee = $config['base_fee'] ?? 15000;
    $perKm = $config['price_per_km'] ?? 5000;
    $perKmFar = $config['price_per_km_far'] ?? 7000;
    $peakHourRate = $config['peak_hour_rate'] ?? 20;
    $freeShipMin = $config['free_ship_min'] ?? 200000;
    
    if ($subtotal >= $freeShipMin) {
        return ['fee' => 0, 'is_free' => true, 'is_peak' => false, 'distance' => $distance];
    }
    
    $distanceKm = ceil($distance);
    
    // Trong phạm vi 3km: phí cố định 12.000đ
    if ($distance <= 3) {
        $shippingFee = 12000;
    } elseif ($distanceKm <= 5) {
        // 3-5km: base + km * giá/km
        $shippingFee = $baseFee + $distanceKm * $perKm;
    } else {
        // Trên 5km: base + 5km giá thường + km còn lại giá xa
        $shippingFee = $baseFee + (5 * $perKm) + (($distanceKm - 5) * $perKmFar);
    }
    
    $currentHour = (int)date('H');
    $isPeakHour = ($currentHour >= 11 && $currentHour < 13) || ($currentHour >= 18 && $currentHour < 20);
    
    if ($isPeakHour) {
        $shippingFee = $shippingFee * (100 + $peakHourRate) / 100;
    }
    
    return ['fee' => round($shippingFee), 'is_free' => false, 'is_peak' => $isPeakHour, 'distance' => $distance];
}

// Lấy danh sách voucher hợp lệ (admin cấp)
$vouchers = [];

// Kiểm tra bảng vouchers tồn tại và lấy voucher admin
try {
    // Lấy tất cả voucher active và còn hạn (dùng DATE để so sánh ngày)
    $stmtVoucher = $pdo->query("SELECT * FROM vouchers 
        WHERE status = 'active' 
        AND (start_date IS NULL OR DATE(start_date) <= CURDATE()) 
        AND (end_date IS NULL OR DATE(end_date) >= CURDATE()) 
        AND (usage_limit IS NULL OR used_count < usage_limit)");
    $vouchers = $stmtVoucher->fetchAll();
} catch (PDOException $e) {
    // Bảng vouchers chưa tồn tại - tạo bảng
    try {
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
            start_date DATETIME,
            end_date DATETIME,
            status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e2) {
        // Bỏ qua
    }
}

// Nhóm theo shop
$cartByShop = [];
$totalAmount = 0;
$grandTotal = 0;

// Thêm sản phẩm thường
foreach ($cartItems as $item) {
    if (!isset($cartByShop[$item['shop_id']])) {
        $cartByShop[$item['shop_id']] = [
            'shop_name' => $item['shop_name'],
            'shop_lat' => $item['shop_lat'],
            'shop_lng' => $item['shop_lng'],
            'items' => [],
            'combos' => [],
            'subtotal' => 0
        ];
    }
    $cartByShop[$item['shop_id']]['items'][] = $item;
    $cartByShop[$item['shop_id']]['subtotal'] += $item['price'] * $item['quantity'];
    $totalAmount += $item['price'] * $item['quantity'];
}

// Thêm combo
foreach ($cartCombos as $combo) {
    if (!isset($cartByShop[$combo['shop_id']])) {
        $cartByShop[$combo['shop_id']] = [
            'shop_name' => $combo['shop_name'],
            'shop_lat' => $combo['shop_lat'],
            'shop_lng' => $combo['shop_lng'],
            'items' => [],
            'combos' => [],
            'subtotal' => 0
        ];
    }
    $cartByShop[$combo['shop_id']]['combos'][] = $combo;
    $cartByShop[$combo['shop_id']]['subtotal'] += $combo['combo_price'] * $combo['quantity'];
    $totalAmount += $combo['combo_price'] * $combo['quantity'];
}

// Tính phí ship cho từng shop và lấy khuyến mãi
foreach ($cartByShop as $shopId => &$shopData) {
    $userLat = $userLocation['lat'] ?? null;
    $userLng = $userLocation['lng'] ?? null;
    $shopLat = $shopData['shop_lat'] ?? null;
    $shopLng = $shopData['shop_lng'] ?? null;
    
    if ($userLat && $userLng && $shopLat && $shopLng) {
        $distance = haversineCart($userLat, $userLng, $shopLat, $shopLng);
    } else {
        $distance = 3; // Mặc định 3km
    }
    
    $shopData['shipping'] = calcShipFee($distance, $shippingConfig, $shopData['subtotal']);
    
    // Lấy khuyến mãi tự động của shop
    $stmt = $pdo->prepare("SELECT p.*, pr.name as gift_product_name 
                           FROM promotions p 
                           LEFT JOIN products pr ON p.gift_product_id = pr.id
                           WHERE p.shop_id = ? AND p.status = 'active' 
                           AND p.start_date <= NOW() AND p.end_date >= NOW()
                           AND (p.usage_limit IS NULL OR p.used_count < p.usage_limit)
                           AND p.min_order <= ?
                           ORDER BY p.value DESC LIMIT 1");
    $stmt->execute([$shopId, $shopData['subtotal']]);
    $shopPromo = $stmt->fetch();
    
    $shopData['promo'] = null;
    $shopData['discount'] = 0;
    
    if ($shopPromo) {
        $shopData['promo'] = $shopPromo;
        switch ($shopPromo['type']) {
            case 'percent':
                $shopData['discount'] = $shopData['subtotal'] * ($shopPromo['value'] / 100);
                if ($shopPromo['max_discount'] && $shopData['discount'] > $shopPromo['max_discount']) {
                    $shopData['discount'] = $shopPromo['max_discount'];
                }
                break;
            case 'fixed':
                $shopData['discount'] = $shopPromo['value'];
                break;
            case 'freeship':
                $shopData['shipping']['fee'] = 0;
                $shopData['shipping']['is_free'] = true;
                break;
        }
    }
    
    $grandTotal += $shopData['subtotal'] + $shopData['shipping']['fee'] - $shopData['discount'];
}
unset($shopData);

$freeShipMin = $shippingConfig['free_ship_min'] ?? 200000;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">🛒 Giỏ hàng của bạn</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if (empty($cartItems) && empty($cartCombos)): ?>
        <div class="section" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px; margin-bottom: 20px;">🛒</p>
            <h2>Giỏ hàng trống</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Hãy thêm món ăn yêu thích vào giỏ hàng!</p>
            <a href="shops.php" class="btn-primary" style="display: inline-block; text-decoration: none; margin-top: 15px;">Xem cửa hàng</a>
        </div>
        <?php else: ?>
        
        <?php foreach ($cartByShop as $shopId => $shopCart): ?>
        <div class="section">
            <h2>🏪 <?= htmlspecialchars($shopCart['shop_name']) ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php // Hiển thị combo trước ?>
                    <?php foreach ($shopCart['combos'] as $combo): 
                        $comboImage = $combo['image'] ? (strpos($combo['image'], 'http') === 0 ? $combo['image'] : '../' . $combo['image']) : 'https://via.placeholder.com/60?text=Combo';
                        $discount = round(($combo['original_price'] - $combo['combo_price']) / $combo['original_price'] * 100);
                    ?>
                    <tr style="background: #fff9f0;">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="position: relative;">
                                    <img src="<?= $comboImage ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                    <span style="position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; font-size: 10px; padding: 2px 5px; border-radius: 10px;">-<?= $discount ?>%</span>
                                </div>
                                <div>
                                    <span style="font-weight: 600;">🎯 <?= htmlspecialchars($combo['name']) ?></span>
                                    <br><small style="color: #666;"><?= htmlspecialchars($combo['items_text']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color: #e74c3c; font-weight: bold;"><?= number_format($combo['combo_price']) ?>đ</span>
                            <br><small style="text-decoration: line-through; color: #999;"><?= number_format($combo['original_price']) ?>đ</small>
                        </td>
                        <td>
                            <form method="POST" style="display: flex; align-items: center; gap: 5px;" oninput="this.submit()">
                                <input type="hidden" name="action" value="update_combo">
                                <input type="hidden" name="cart_combo_id" value="<?= $combo['id'] ?>">
                                <input type="number" name="quantity" value="<?= $combo['quantity'] ?>" min="1" max="99" style="width: 60px; padding: 5px; text-align: center;">
                            </form>
                        </td>
                        <td style="font-weight: bold; color: #e74c3c;"><?= number_format($combo['combo_price'] * $combo['quantity']) ?>đ</td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove_combo">
                                <input type="hidden" name="cart_combo_id" value="<?= $combo['id'] ?>">
                                <button type="submit" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php // Hiển thị sản phẩm thường ?>
                    <?php foreach ($shopCart['items'] as $item): 
                        $itemImage = $item['image'] ? (strpos($item['image'], 'http') === 0 ? $item['image'] : '../' . $item['image']) : 'https://via.placeholder.com/60';
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?= $itemImage ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                <span><?= htmlspecialchars($item['name']) ?></span>
                            </div>
                        </td>
                        <td><?= number_format($item['price']) ?>đ</td>
                        <td>
                                <form method="POST" style="display: flex; align-items: center; gap: 5px;" oninput="this.submit()">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="99" style="width: 60px; padding: 5px; text-align: center;">
                                </form>
                        </td>
                        <td style="font-weight: bold; color: #ff6b35;"><?= number_format($item['price'] * $item['quantity']) ?>đ</td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                <button type="submit" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Voucher và phí ship -->
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                
                <?php if ($shopCart['promo']): ?>
                <!-- Khuyến mãi tự động áp dụng -->
                <div style="background: #d4edda; border: 1px solid #28a745; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 20px;">🎁</span>
                        <div>
                            <strong style="color: #155724;">Khuyến mãi: <?= htmlspecialchars($shopCart['promo']['name']) ?></strong>
                            <p style="color: #155724; font-size: 13px; margin: 3px 0 0;">
                                <?php 
                                $promoType = $shopCart['promo']['type'];
                                $promoValue = floatval($shopCart['promo']['value']);
                                
                                // Nếu type là percent nhưng value > 100, coi như là fixed
                                if ($promoType === 'percent' && $promoValue > 100) {
                                    $promoType = 'fixed';
                                }
                                
                                switch ($promoType) {
                                    case 'percent': 
                                        echo 'Giảm ' . number_format($promoValue, 0) . '% (-' . number_format($shopCart['discount']) . 'đ)'; 
                                        break;
                                    case 'fixed': 
                                        echo 'Giảm ' . number_format($promoValue, 0) . 'đ'; 
                                        break;
                                    case 'freeship': 
                                        echo 'Miễn phí giao hàng'; 
                                        break;
                                    case 'gift': 
                                        echo 'Tặng ' . $shopCart['promo']['gift_product_name']; 
                                        break;
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Voucher -->
                <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #ddd;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span>🎫 <strong>Voucher thêm:</strong></span>
                        
                        <!-- Nút chọn voucher -->
                        <button type="button" onclick="openVoucherModal(<?= $shopId ?>, <?= $shopCart['subtotal'] ?>, <?= $shopCart['shipping']['fee'] ?>, <?= $shopCart['discount'] ?>)" 
                                id="voucher_btn_<?= $shopId ?>"
                                style="padding: 10px 20px; background: white; border: 2px dashed #ff6b35; border-radius: 8px; cursor: pointer; color: #ff6b35; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                            <span>🎟️</span>
                            <span id="voucher_text_<?= $shopId ?>">Chọn voucher</span>
                            <span style="font-size: 18px;">›</span>
                        </button>
                        
                        <span id="discount_<?= $shopId ?>" style="color: #27ae60; font-weight: bold;"></span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <strong>Tạm tính:</strong> <?= number_format($shopCart['subtotal']) ?>đ
                        <?php if ($shopCart['discount'] > 0): ?>
                        <span style="color: #27ae60; margin-left: 10px;">-<?= number_format($shopCart['discount']) ?>đ</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Phí ship (<?= round($shopCart['shipping']['distance'], 1) ?>km):</strong>
                        <?php if ($shopCart['shipping']['is_free']): ?>
                        <span style="color: #27ae60; font-weight: bold;">MIỄN PHÍ 🎉</span>
                        <?php else: ?>
                        <span id="ship_<?= $shopId ?>" style="color: #3498db; font-weight: bold;"><?= number_format($shopCart['shipping']['fee']) ?>đ</span>
                        <?php if ($shopCart['shipping']['is_peak']): ?>
                        <span style="background: #e74c3c; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">Giờ cao điểm</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Tổng:</strong> <span id="total_<?= $shopId ?>" style="color: #ff6b35; font-weight: bold; font-size: 18px;"><?= number_format($shopCart['subtotal'] + $shopCart['shipping']['fee'] - $shopCart['discount']) ?>đ</span>
                    </div>
                    <a href="checkout.php?shop_id=<?= $shopId ?>" class="btn-primary" style="text-decoration: none;">Đặt hàng từ cửa hàng này</a>
                </div>
                <?php if (!$shopCart['shipping']['is_free'] && $shopCart['subtotal'] < $freeShipMin): ?>
                <div style="margin-top: 10px; padding: 8px 12px; background: #fff3cd; border-radius: 6px; font-size: 13px; color: #856404;">
                    💡 Mua thêm <strong><?= number_format($freeShipMin - $shopCart['subtotal']) ?>đ</strong> để được miễn phí ship!
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn-danger" onclick="return confirm('Xóa toàn bộ giỏ hàng?')">Xóa tất cả</button>
                    </form>
                </div>
                <div style="font-size: 20px;">
                    <strong>Tổng cộng (bao gồm phí ship): <span style="color: #ff6b35;"><?= number_format($grandTotal) ?>đ</span></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <!-- Modal Chọn Voucher -->
    <div id="voucherModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: flex-end;">
        <div style="background: white; width: 100%; max-width: 500px; max-height: 80vh; border-radius: 20px 20px 0 0; overflow: hidden; animation: slideUp 0.3s ease;">
            <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">🎫 Chọn Voucher</h3>
                <button onclick="closeVoucherModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
            </div>
            <div id="voucherList" style="padding: 15px; overflow-y: auto; max-height: calc(80vh - 140px);">
                <!-- Voucher list will be inserted here -->
            </div>
            <div style="padding: 15px; border-top: 1px solid #eee;">
                <button onclick="closeVoucherModal()" style="width: 100%; padding: 15px; background: #f0f0f0; border: none; border-radius: 10px; font-size: 16px; cursor: pointer;">Đóng</button>
            </div>
        </div>
    </div>
    
    <style>
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    .voucher-item { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 15px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s; }
    .voucher-item:hover { border-color: #ff6b35; background: #fff9f7; }
    .voucher-item.selected { border-color: #27ae60; background: #f0fff4; }
    .voucher-item.disabled { opacity: 0.5; cursor: not-allowed; }
    .voucher-item .voucher-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .voucher-item .voucher-code { background: linear-gradient(135deg, #ff6b35, #ff8c5a); color: white; padding: 5px 12px; border-radius: 15px; font-weight: bold; font-size: 13px; }
    .voucher-item .voucher-value { font-size: 22px; font-weight: bold; color: #e74c3c; }
    .voucher-item .voucher-name { font-weight: 600; margin-bottom: 5px; }
    .voucher-item .voucher-desc { color: #7f8c8d; font-size: 13px; }
    .voucher-item .voucher-condition { display: flex; gap: 15px; margin-top: 10px; font-size: 12px; color: #999; }
    .voucher-item .check-icon { display: none; color: #27ae60; font-size: 20px; }
    .voucher-item.selected .check-icon { display: block; }
    </style>
    
    <script>
    var currentShopId = null;
    var currentSubtotal = 0;
    var currentShippingFee = 0;
    var currentShopDiscount = 0;
    var appliedVouchers = {};
    var voucherList = <?= json_encode($vouchers) ?>;
    
    function openVoucherModal(shopId, subtotal, shippingFee, shopDiscount) {
        currentShopId = shopId;
        currentSubtotal = subtotal;
        currentShippingFee = shippingFee;
        currentShopDiscount = shopDiscount || 0;
        
        const listEl = document.getElementById('voucherList');
        
        if (voucherList.length === 0) {
            listEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;"><p style="font-size: 50px;">🎫</p><p>Chưa có voucher nào</p></div>';
        } else {
            let html = '';
            
            // Thêm option bỏ chọn voucher
            html += `
                <div class="voucher-item ${!appliedVouchers[shopId] ? 'selected' : ''}" onclick="selectVoucher(null)">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div class="voucher-name">❌ Không sử dụng voucher</div>
                            <div class="voucher-desc">Bỏ qua voucher giảm giá</div>
                        </div>
                        <span class="check-icon">✓</span>
                    </div>
                </div>
            `;
            
            voucherList.forEach(v => {
                const isDisabled = subtotal < parseFloat(v.min_order);
                const isSelected = appliedVouchers[shopId] && appliedVouchers[shopId].id == v.id;
                
                let valueText = '';
                let typeIcon = '';
                if (v.type === 'percent') {
                    valueText = '-' + parseFloat(v.value) + '%';
                    typeIcon = '💰';
                } else if (v.type === 'fixed') {
                    valueText = '-' + formatMoney(v.value) + 'đ';
                    typeIcon = '💵';
                } else {
                    valueText = 'FREE SHIP';
                    typeIcon = '🚚';
                }
                
                html += `
                    <div class="voucher-item ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}" 
                         onclick="${isDisabled ? '' : 'selectVoucher(' + JSON.stringify(v).replace(/"/g, '&quot;') + ')'}">
                        <div class="voucher-header">
                            <span class="voucher-code">${v.code}</span>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="voucher-value">${valueText}</span>
                                <span class="check-icon">✓</span>
                            </div>
                        </div>
                        <div class="voucher-name">${typeIcon} ${v.name}</div>
                        <div class="voucher-condition">
                            <span>📦 Đơn tối thiểu: ${formatMoney(v.min_order)}đ</span>
                            ${v.max_discount ? '<span>🔒 Giảm tối đa: ' + formatMoney(v.max_discount) + 'đ</span>' : ''}
                        </div>
                        ${isDisabled ? '<div style="color: #e74c3c; font-size: 12px; margin-top: 8px;">⚠️ Chưa đạt giá trị đơn tối thiểu</div>' : ''}
                    </div>
                `;
            });
            
            listEl.innerHTML = html;
        }
        
        document.getElementById('voucherModal').style.display = 'flex';
    }
    
    function closeVoucherModal() {
        document.getElementById('voucherModal').style.display = 'none';
    }
    
    function selectVoucher(voucher) {
        const shopId = currentShopId;
        const subtotal = currentSubtotal;
        const shippingFee = currentShippingFee;
        const shopDiscount = currentShopDiscount;
        
        const discountEl = document.getElementById('discount_' + shopId);
        const totalEl = document.getElementById('total_' + shopId);
        const shipEl = document.getElementById('ship_' + shopId);
        const btnText = document.getElementById('voucher_text_' + shopId);
        const btn = document.getElementById('voucher_btn_' + shopId);
        
        if (!voucher) {
            // Bỏ chọn voucher - tính lại với discount shop
            delete appliedVouchers[shopId];
            discountEl.textContent = '';
            totalEl.textContent = formatMoney(subtotal - shopDiscount + shippingFee) + 'đ';
            if (shipEl) shipEl.textContent = formatMoney(shippingFee) + 'đ';
            btnText.textContent = 'Chọn voucher';
            btn.style.borderColor = '#ff6b35';
            btn.style.color = '#ff6b35';
            btn.style.background = 'white';
            closeVoucherModal();
            return;
        }
        
        let voucherDiscount = 0;
        let newShipping = shippingFee;
        let discountText = '';
        
        if (voucher.type === 'percent') {
            voucherDiscount = subtotal * (parseFloat(voucher.value) / 100);
            if (voucher.max_discount && voucherDiscount > parseFloat(voucher.max_discount)) {
                voucherDiscount = parseFloat(voucher.max_discount);
            }
            discountText = '-' + formatMoney(Math.round(voucherDiscount)) + 'đ';
        } else if (voucher.type === 'fixed') {
            voucherDiscount = parseFloat(voucher.value);
            if (voucherDiscount > subtotal) voucherDiscount = subtotal;
            discountText = '-' + formatMoney(Math.round(voucherDiscount)) + 'đ';
        } else if (voucher.type === 'freeship') {
            newShipping = 0;
            discountText = 'Miễn phí ship';
            if (shipEl) shipEl.innerHTML = '<s style="color:#999;">' + formatMoney(shippingFee) + 'đ</s> <span style="color:#27ae60;">MIỄN PHÍ</span>';
        }
        
        // Lưu voucher đã áp dụng
        appliedVouchers[shopId] = {
            id: voucher.id,
            code: voucher.code,
            discount: Math.round(voucherDiscount),
            freeShip: voucher.type === 'freeship'
        };
        
        // Cập nhật UI
        discountEl.innerHTML = '<span style="color:#27ae60;">✓ ' + discountText + '</span>';
        // Tổng = Tạm tính - Giảm giá shop - Giảm giá voucher + Phí ship
        const total = subtotal - shopDiscount - voucherDiscount + newShipping;
        totalEl.textContent = formatMoney(Math.round(total)) + 'đ';
        
        // Cập nhật nút
        btnText.textContent = voucher.code;
        btn.style.borderColor = '#27ae60';
        btn.style.color = '#27ae60';
        btn.style.background = '#f0fff4';
        
        closeVoucherModal();
    }
    
    function formatMoney(num) {
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Đóng modal khi click bên ngoài
    document.getElementById('voucherModal').addEventListener('click', function(e) {
        if (e.target === this) closeVoucherModal();
    });
    </script>
</body>
</html>
