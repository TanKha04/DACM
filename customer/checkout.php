<?php
/**
 * Thanh toán đơn hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$shopId = (int)($_GET['shop_id'] ?? 0);
$message = '';

if (!$shopId) {
    header('Location: cart.php');
    exit;
}

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Lấy địa chỉ đã lưu
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

// Lấy giỏ hàng của shop này (sản phẩm thường)
$stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.shop_id = ?");
$stmt->execute([$userId, $shopId]);
$cartItems = $stmt->fetchAll();

// Lấy combo của shop này
$cartCombos = [];
try {
    $stmt = $pdo->prepare("SELECT cc.*, cb.name, cb.combo_price as price, cb.original_price, cb.image,
                           (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', ci.quantity) SEPARATOR ', ') FROM combo_items ci JOIN products p ON ci.product_id = p.id WHERE ci.combo_id = cb.id) as items_text
                           FROM cart_combos cc 
                           JOIN combos cb ON cc.combo_id = cb.id 
                           WHERE cc.user_id = ? AND cb.shop_id = ?");
    $stmt->execute([$userId, $shopId]);
    $cartCombos = $stmt->fetchAll();
} catch (PDOException $e) {
    // Bảng chưa tồn tại
}

if (empty($cartItems) && empty($cartCombos)) {
    header('Location: cart.php');
    exit;
}

// Lấy thông tin shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();

// Kiểm tra xem khách có đơn hàng đang xử lý từ shop này không
$stmt = $pdo->prepare("SELECT id, status FROM orders WHERE customer_id = ? AND shop_id = ? AND status NOT IN ('delivered', 'cancelled') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId, $shopId]);
$pendingOrder = $stmt->fetch();

if ($pendingOrder) {
    $statusLabels = [
        'pending' => 'chờ xác nhận',
        'confirmed' => 'đã xác nhận',
        'preparing' => 'đang chuẩn bị',
        'ready' => 'sẵn sàng giao',
        'picked' => 'shipper đã lấy hàng',
        'delivering' => 'đang giao'
    ];
    $statusText = $statusLabels[$pendingOrder['status']] ?? $pendingOrder['status'];
    $_SESSION['checkout_error'] = "Bạn đang có đơn hàng #{$pendingOrder['id']} ({$statusText}) từ cửa hàng này. Vui lòng đợi đơn hàng được giao xong trước khi đặt đơn mới.";
    header("Location: order_detail.php?id={$pendingOrder['id']}");
    exit;
}

// Tính tổng
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
// Cộng thêm combo
foreach ($cartCombos as $combo) {
    $subtotal += $combo['price'] * $combo['quantity'];
}

// Lấy phí ship
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$config = $stmt->fetch();

// Tính phí ship theo khoảng cách
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// Hàm tính phí ship
function calculateShippingFee($distance, $config, $subtotal) {
    $baseFee = $config['base_fee'] ?? 15000;
    $perKm = $config['price_per_km'] ?? 5000;
    $perKmFar = $config['price_per_km_far'] ?? 7000;
    $peakHourRate = $config['peak_hour_rate'] ?? 20;
    $freeShipMin = $config['free_ship_min'] ?? 200000;
    
    // Miễn phí ship nếu đơn hàng đủ điều kiện
    if ($subtotal >= $freeShipMin) {
        return ['fee' => 0, 'is_free' => true, 'is_peak' => false];
    }
    
    // Tính phí theo khoảng cách
    $distanceKm = ceil($distance);
    
    // Trong phạm vi 3km: phí cố định 12.000đ
    if ($distance <= 3) {
        $shippingFee = 12000;
    } elseif ($distanceKm <= 5) {
        // 3-5km: base + km * giá/km (công thức cũ)
        $shippingFee = $baseFee + $distanceKm * $perKm;
    } else {
        // Trên 5km: base + 5km giá thường + km còn lại giá xa
        $shippingFee = $baseFee + (5 * $perKm) + (($distanceKm - 5) * $perKmFar);
    }
    
    // Kiểm tra giờ cao điểm (11h-13h, 18h-20h)
    $currentHour = (int)date('H');
    $isPeakHour = ($currentHour >= 11 && $currentHour < 13) || ($currentHour >= 18 && $currentHour < 20);
    
    if ($isPeakHour) {
        $shippingFee = $shippingFee * (100 + $peakHourRate) / 100;
    }
    
    return ['fee' => round($shippingFee), 'is_free' => false, 'is_peak' => $isPeakHour];
}

$userLat = $user['lat'] ?? null;
$userLng = $user['lng'] ?? null;
$shopLat = $shop['latitude'] ?? null;
$shopLng = $shop['longitude'] ?? null;
if ($userLat && $userLng && $shopLat && $shopLng) {
    $distance = haversine($userLat, $userLng, $shopLat, $shopLng);
} else {
    $distance = 3; // Mặc định 3km nếu thiếu dữ liệu
}

// Tính phí ship
$shippingResult = calculateShippingFee($distance, $config, $subtotal);
$shippingFee = $shippingResult['fee'];
$isFreeShip = $shippingResult['is_free'];
$isPeakHour = $shippingResult['is_peak'];
$serviceFee = $config['service_fee'] ?? 3000;
$freeShipMin = $config['free_ship_min'] ?? 200000;
$total = $subtotal + $shippingFee + $serviceFee;

// Biến lưu thông tin giảm giá
$discount = 0;
$appliedVoucher = null;
$appliedPromo = null;
$autoAppliedPromo = null;
$giftProduct = null;

// TỰ ĐỘNG ÁP DỤNG KHUYẾN MÃI CỦA SHOP (không cần nhập mã)
$stmt = $pdo->prepare("SELECT p.*, pr.name as gift_product_name, pr.price as gift_product_price
                       FROM promotions p 
                       LEFT JOIN products pr ON p.gift_product_id = pr.id
                       WHERE p.shop_id = ? AND p.status = 'active' 
                       AND p.start_date <= NOW() AND p.end_date >= NOW()
                       AND (p.usage_limit IS NULL OR p.used_count < p.usage_limit)
                       AND p.min_order <= ?
                       ORDER BY p.value DESC LIMIT 1");
$stmt->execute([$shopId, $subtotal]);
$autoPromo = $stmt->fetch();

if ($autoPromo) {
    $autoAppliedPromo = $autoPromo;
    
    switch ($autoPromo['type']) {
        case 'percent':
            $discount = $subtotal * ($autoPromo['value'] / 100);
            if ($autoPromo['max_discount'] && $discount > $autoPromo['max_discount']) {
                $discount = $autoPromo['max_discount'];
            }
            break;
        case 'fixed':
            $discount = $autoPromo['value'];
            break;
        case 'freeship':
            $shippingFee = 0;
            $isFreeShip = true;
            break;
        case 'gift':
            $giftProduct = [
                'id' => $autoPromo['gift_product_id'],
                'name' => $autoPromo['gift_product_name'],
                'quantity' => $autoPromo['gift_quantity']
            ];
            break;
        case 'combo':
            $discount = $autoPromo['value'];
            break;
    }
}
$voucherError = '';

// Xử lý áp dụng mã giảm giá
if (isset($_POST['apply_code'])) {
    $code = strtoupper(trim($_POST['voucher_code'] ?? ''));
    
    if ($code) {
        // Kiểm tra voucher hệ thống
        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
        $stmt->execute([$code]);
        $voucher = $stmt->fetch();
        
        if ($voucher) {
            // Kiểm tra giới hạn sử dụng
            if ($voucher['usage_limit'] && $voucher['used_count'] >= $voucher['usage_limit']) {
                $voucherError = 'Voucher đã hết lượt sử dụng';
            } elseif ($subtotal < $voucher['min_order']) {
                $voucherError = 'Đơn hàng tối thiểu ' . number_format($voucher['min_order']) . 'đ';
            } else {
                // Kiểm tra user đã dùng bao nhiêu lần
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_usage WHERE voucher_id = ? AND user_id = ?");
                $stmt->execute([$voucher['id'], $userId]);
                $userUsed = $stmt->fetchColumn();
                
                if ($userUsed >= $voucher['user_limit']) {
                    $voucherError = 'Bạn đã sử dụng hết lượt cho voucher này';
                } else {
                    $appliedVoucher = $voucher;
                    if ($voucher['type'] === 'percent') {
                        $discount = $subtotal * ($voucher['value'] / 100);
                        if ($voucher['max_discount'] && $discount > $voucher['max_discount']) {
                            $discount = $voucher['max_discount'];
                        }
                    } elseif ($voucher['type'] === 'fixed') {
                        $discount = $voucher['value'];
                    } elseif ($voucher['type'] === 'freeship') {
                        $discount = $shippingFee;
                    }
                }
            }
        } else {
            // Kiểm tra promotion của shop
            $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND shop_id = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
            $stmt->execute([$code, $shopId]);
            $promo = $stmt->fetch();
            
            if ($promo) {
                if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
                    $voucherError = 'Mã khuyến mãi đã hết lượt sử dụng';
                } elseif ($subtotal < $promo['min_order']) {
                    $voucherError = 'Đơn hàng tối thiểu ' . number_format($promo['min_order']) . 'đ';
                } else {
                    $appliedPromo = $promo;
                    if ($promo['type'] === 'percent') {
                        $discount = $subtotal * ($promo['value'] / 100);
                        if ($promo['max_discount'] && $discount > $promo['max_discount']) {
                            $discount = $promo['max_discount'];
                        }
                    } elseif ($promo['type'] === 'fixed') {
                        $discount = $promo['value'];
                    } elseif ($promo['type'] === 'freeship') {
                        $discount = $shippingFee;
                    }
                }
            } else {
                $voucherError = 'Mã không hợp lệ hoặc đã hết hạn';
            }
        }
    }
}

// Tính lại tổng sau giảm giá
$total = $subtotal + $shippingFee - $discount;

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $note = trim($_POST['note'] ?? '');
    $voucherCode = strtoupper(trim($_POST['applied_voucher'] ?? ''));
    $promoCode = strtoupper(trim($_POST['applied_promo'] ?? ''));
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);
    $autoPromoId = (int)($_POST['auto_promo_id'] ?? 0);
    $giftProductId = (int)($_POST['gift_product_id'] ?? 0);
    $giftQuantity = (int)($_POST['gift_quantity'] ?? 0);
    
    // Lấy tọa độ từ form (nếu người dùng chọn trên bản đồ)
    $orderLat = floatval($_POST['user_lat'] ?? 0);
    $orderLng = floatval($_POST['user_lng'] ?? 0);
    
    // Tính lại khoảng cách nếu có tọa độ mới
    $shopLat = $shop['latitude'] ?? null;
    $shopLng = $shop['longitude'] ?? null;
    if ($orderLat && $orderLng && $shopLat && $shopLng) {
        $distance = haversine($orderLat, $orderLng, $shopLat, $shopLng);
        $shippingResult = calculateShippingFee($distance, $config, $subtotal);
        $shippingFee = $shippingResult['fee'];
    }
    
    if (empty($name) || empty($phone) || empty($address)) {
        $message = 'error:Vui lòng điền đầy đủ thông tin giao hàng';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Tính hoa hồng
            $commissionRate = $shop['commission_rate'] ?? 10;
            $commissionFee = $subtotal * ($commissionRate / 100);
            
            // Tạo đơn hàng (thêm distance_km)
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, shop_id, total_amount, shipping_fee, commission_fee, delivery_address, delivery_phone, delivery_name, distance_km, payment_method, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$userId, $shopId, $subtotal - $discountAmount, $shippingFee, $commissionFee, $address, $phone, $name, round($distance, 2), $paymentMethod, $note]);
            $orderId = $pdo->lastInsertId();
            
            // Thêm chi tiết đơn hàng (sản phẩm thường)
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cartItems as $item) {
                $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
            }
            
            // Thêm combo vào đơn hàng
            foreach ($cartCombos as $combo) {
                // Lấy các sản phẩm trong combo
                $stmtItems = $pdo->prepare("SELECT ci.product_id, ci.quantity, p.name, p.price FROM combo_items ci JOIN products p ON ci.product_id = p.id WHERE ci.combo_id = ?");
                $stmtItems->execute([$combo['combo_id']]);
                $comboItems = $stmtItems->fetchAll();
                
                // Tính tỷ lệ giảm giá của combo
                $originalTotal = 0;
                foreach ($comboItems as $ci) {
                    $originalTotal += $ci['price'] * $ci['quantity'];
                }
                $discountRatio = $originalTotal > 0 ? $combo['price'] / $originalTotal : 1;
                
                // Thêm từng sản phẩm trong combo với giá đã giảm theo tỷ lệ
                foreach ($comboItems as $ci) {
                    $itemPrice = round($ci['price'] * $discountRatio);
                    $itemQty = $ci['quantity'] * $combo['quantity'];
                    $stmt->execute([$orderId, $ci['product_id'], '🎯 ' . $ci['name'] . ' (Combo: ' . $combo['name'] . ')', $itemQty, $itemPrice, $itemPrice * $itemQty]);
                }
            }
            
            // Thêm quà tặng vào đơn hàng (nếu có)
            if ($giftProductId && $giftQuantity > 0) {
                $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$giftProductId]);
                $giftName = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, 0, 0)");
                $stmt->execute([$orderId, $giftProductId, '🎁 ' . $giftName . ' (Quà tặng)', $giftQuantity]);
            }
            
            // Ghi nhận sử dụng khuyến mãi tự động
            if ($autoPromoId && $discountAmount >= 0) {
                $stmt = $pdo->prepare("INSERT INTO promotion_usage (promotion_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)");
                $stmt->execute([$autoPromoId, $userId, $orderId, $discountAmount]);
                $stmt = $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE id = ?");
                $stmt->execute([$autoPromoId]);
            }
            
            // Ghi nhận sử dụng voucher
            if ($voucherCode && $discountAmount > 0) {
                $stmt = $pdo->prepare("SELECT id FROM vouchers WHERE code = ?");
                $stmt->execute([$voucherCode]);
                $voucherId = $stmt->fetchColumn();
                if ($voucherId) {
                    $stmt = $pdo->prepare("INSERT INTO voucher_usage (voucher_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$voucherId, $userId, $orderId, $discountAmount]);
                    $stmt = $pdo->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?");
                    $stmt->execute([$voucherId]);
                }
            }
            
            // Ghi nhận sử dụng promotion
            if ($promoCode && $discountAmount > 0) {
                $stmt = $pdo->prepare("SELECT id FROM promotions WHERE code = ?");
                $stmt->execute([$promoCode]);
                $promoId = $stmt->fetchColumn();
                if ($promoId) {
                    $stmt = $pdo->prepare("INSERT INTO promotion_usage (promotion_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$promoId, $userId, $orderId, $discountAmount]);
                    $stmt = $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE id = ?");
                    $stmt->execute([$promoId]);
                }
            }
            
            // Tạo payment record
            $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, amount, method, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$orderId, $userId, $subtotal + $shippingFee - $discountAmount, $paymentMethod]);
            
            // Xóa giỏ hàng (sản phẩm thường)
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id IN (SELECT id FROM products WHERE shop_id = ?)");
            $stmt->execute([$userId, $shopId]);
            
            // Xóa combo trong giỏ hàng
            try {
                $stmt = $pdo->prepare("DELETE FROM cart_combos WHERE user_id = ? AND combo_id IN (SELECT id FROM combos WHERE shop_id = ?)");
                $stmt->execute([$userId, $shopId]);
            } catch (PDOException $e) {}
            
            // Tạo thông báo cho khách hàng
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
            $stmt->execute([$userId, 'Đặt hàng thành công', "Đơn hàng #$orderId đã được tạo"]);
            
            // Gửi thông báo cho seller (chủ shop)
            $sellerStmt = $pdo->prepare("SELECT user_id FROM shops WHERE id = ?");
            $sellerStmt->execute([$shopId]);
            $sellerId = $sellerStmt->fetchColumn();
            if ($sellerId) {
                $stmt->execute([$sellerId, '🔔 Đơn hàng mới!', "Bạn có đơn hàng mới #$orderId từ khách hàng. Vui lòng xác nhận!"]);
            }
            
            $pdo->commit();
            
            header("Location: order_detail.php?id=$orderId&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'error:Có lỗi xảy ra, vui lòng thử lại';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .checkout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }
        .order-summary { position: sticky; top: 100px; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .summary-total { font-size: 20px; font-weight: bold; color: #ff6b35; }
        .payment-methods { display: flex; gap: 15px; flex-wrap: wrap; }
        .payment-method { flex: 1; min-width: 120px; padding: 15px; border: 2px solid #ddd; border-radius: 10px; text-align: center; cursor: pointer; }
        .payment-method.selected { border-color: #ff6b35; background: #fff5f0; }
        .payment-method input { display: none; }
        .address-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .address-item { padding: 15px; border: 2px solid #ddd; border-radius: 10px; cursor: pointer; }
        .address-item.selected { border-color: #ff6b35; background: #fff5f0; }
        .voucher-box { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
        .voucher-input { display: flex; gap: 10px; }
        .voucher-input input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; text-transform: uppercase; }
        .voucher-input button { padding: 10px 20px; background: #ff6b35; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .voucher-applied { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .voucher-error { background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 8px; margin-top: 10px; }
        .discount-row { color: #27ae60; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h1 style="margin-bottom: 30px;">💳 Thanh toán</h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="checkout-grid">
                <div>
                    <div class="section">
                        <h2>📍 Thông tin giao hàng</h2>
                        
                        <?php if (!empty($addresses)): ?>
                        <p style="margin-bottom: 15px; color: #7f8c8d;">Chọn địa chỉ đã lưu:</p>
                        <div class="address-list">
                            <?php foreach ($addresses as $addr): ?>
                            <label class="address-item">
                                <input type="radio" name="saved_address" value="<?= $addr['id'] ?>" onchange="fillAddress(this, '<?= htmlspecialchars($addr['name']) ?>', '<?= htmlspecialchars($addr['phone']) ?>', '<?= htmlspecialchars($addr['address']) ?>')">
                                <strong><?= htmlspecialchars($addr['name']) ?></strong> - <?= htmlspecialchars($addr['phone']) ?><br>
                                <small><?= htmlspecialchars($addr['address']) ?></small>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin-bottom: 15px; color: #7f8c8d;">Hoặc nhập mới:</p>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>Họ và tên *</label>
                            <input type="text" name="name" id="input_name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại *</label>
                            <input type="tel" name="phone" id="input_phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Địa chỉ giao hàng *
                                <button type="button" onclick="openMapModal()" style="margin-left: 10px; background: #3498db; color: white; border: none; border-radius: 6px; padding: 6px 14px; font-size: 14px; cursor: pointer;">📍 Chọn vị trí trên bản đồ</button>
                            </label>
                            <textarea name="address" id="input_address" rows="3" required><?= htmlspecialchars($user['address']) ?></textarea>
                            <input type="hidden" name="user_lat" id="user_lat">
                            <input type="hidden" name="user_lng" id="user_lng">
                        </div>
                        
                        <!-- Leaflet Map Modal -->
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                        <div id="mapModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
                            <div style="background:white; border-radius:12px; padding:20px; max-width:95vw; max-height:90vh; position:relative;">
                                <h3 style="margin-bottom: 15px;">📍 Chọn vị trí giao hàng</h3>
                                <div id="leafletMap" style="width:500px; max-width:85vw; height:350px; border-radius: 8px;"></div>
                                <div style="margin-top: 15px; text-align: right;">
                                    <button type="button" onclick="closeMapModal()" style="padding:8px 20px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; margin-right: 10px;">Đóng</button>
                                    <button type="button" onclick="selectLocation()" style="padding:8px 20px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer;">Chọn vị trí này</button>
                                </div>
                            </div>
                        </div>
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        <script>
                        let leafletMap, leafletMarker, selectedLat, selectedLng;
                        
                        function openMapModal() {
                            document.getElementById('mapModal').style.display = 'flex';
                            setTimeout(initLeafletMap, 100);
                        }
                        
                        function closeMapModal() {
                            document.getElementById('mapModal').style.display = 'none';
                        }
                        
                        function initLeafletMap() {
                            if (leafletMap) return;
                            
                            // Lấy vị trí hiện tại hoặc mặc định
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(function(pos) {
                                    setupMap([pos.coords.latitude, pos.coords.longitude]);
                                }, function() {
                                    setupMap([10.762622, 106.660172]); // HCM mặc định
                                });
                            } else {
                                setupMap([10.762622, 106.660172]);
                            }
                        }
                        
                        function setupMap(center) {
                            selectedLat = center[0];
                            selectedLng = center[1];
                            
                            leafletMap = L.map('leafletMap').setView(center, 16);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '© OpenStreetMap'
                            }).addTo(leafletMap);
                            
                            leafletMarker = L.marker(center, {draggable: true}).addTo(leafletMap);
                            
                            leafletMarker.on('dragend', function(e) {
                                const latlng = e.target.getLatLng();
                                selectedLat = latlng.lat;
                                selectedLng = latlng.lng;
                            });
                            
                            leafletMap.on('click', function(e) {
                                selectedLat = e.latlng.lat;
                                selectedLng = e.latlng.lng;
                                leafletMarker.setLatLng(e.latlng);
                            });
                        }
                        
                        function selectLocation() {
                            if (!selectedLat || !selectedLng) {
                                alert('Vui lòng chọn vị trí trên bản đồ!');
                                return;
                            }
                            
                            document.getElementById('user_lat').value = selectedLat;
                            document.getElementById('user_lng').value = selectedLng;
                            
                            // Lấy địa chỉ từ tọa độ qua Nominatim
                            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${selectedLat}&lon=${selectedLng}&accept-language=vi`)
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.display_name) {
                                        document.getElementById('input_address').value = data.display_name;
                                    }
                                    closeMapModal();
                                })
                                .catch(() => {
                                    closeMapModal();
                                });
                        }
                        </script>
                        <div class="form-group">
                            <label>Ghi chú</label>
                            <textarea name="note" rows="2" placeholder="Ghi chú cho cửa hàng hoặc shipper..."></textarea>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>💰 Phương thức thanh toán</h2>
                        <div class="payment-methods">
                            <label class="payment-method selected">
                                <input type="radio" name="payment_method" value="cash" checked onchange="selectPayment(this)">
                                <div>💵</div>
                                <div>Tiền mặt</div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="card" onchange="selectPayment(this)">
                                <div>💳</div>
                                <div>Thẻ</div>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="ewallet" onchange="selectPayment(this)">
                                <div>📱</div>
                                <div>Ví điện tử</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="section order-summary">
                        <h2>🛒 Đơn hàng</h2>
                        <p style="color: #7f8c8d; margin-bottom: 15px;">🏪 <?= htmlspecialchars($shop['name']) ?></p>
                        
                        <?php // Hiển thị combo trước ?>
                        <?php foreach ($cartCombos as $combo): 
                            $discount = round(($combo['original_price'] - $combo['price']) / $combo['original_price'] * 100);
                        ?>
                        <div class="summary-item" style="background: #fff9f0; padding: 10px; border-radius: 8px; margin-bottom: 5px;">
                            <div>
                                <span style="font-weight: 600;">🎯 <?= htmlspecialchars($combo['name']) ?> x<?= $combo['quantity'] ?></span>
                                <br><small style="color: #666;"><?= htmlspecialchars($combo['items_text']) ?></small>
                            </div>
                            <div style="text-align: right;">
                                <span style="color: #e74c3c; font-weight: bold;"><?= number_format($combo['price'] * $combo['quantity']) ?>đ</span>
                                <br><small style="text-decoration: line-through; color: #999;"><?= number_format($combo['original_price'] * $combo['quantity']) ?>đ</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php // Hiển thị sản phẩm thường ?>
                        <?php foreach ($cartItems as $item): ?>
                        <div class="summary-item">
                            <span><?= htmlspecialchars($item['name']) ?> x<?= $item['quantity'] ?></span>
                            <span><?= number_format($item['price'] * $item['quantity']) ?>đ</span>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="summary-item">
                            <span>Tạm tính</span>
                            <span><?= number_format($subtotal) ?>đ</span>
                        </div>
                        <div class="summary-item">
                            <span>
                                Phí giao hàng (<?= round($distance, 1) ?> km)
                                <?php if ($isPeakHour): ?>
                                <span style="background: #e74c3c; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">Giờ cao điểm</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($isFreeShip): ?>
                            <span style="color: #27ae60;"><s style="color: #999;"><?= number_format($config['base_fee'] + ceil($distance) * $config['price_per_km']) ?>đ</s> <strong>MIỄN PHÍ</strong></span>
                            <?php else: ?>
                            <span><?= number_format($shippingFee) ?>đ</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$isFreeShip && $subtotal < $freeShipMin): ?>
                        <div style="background: #fff3cd; padding: 8px 12px; border-radius: 6px; font-size: 13px; color: #856404; margin-bottom: 10px;">
                            💡 Mua thêm <strong><?= number_format($freeShipMin - $subtotal) ?>đ</strong> để được miễn phí ship!
                        </div>
                        <?php endif; ?>
                        <div class="summary-item">
                            <span>Phí dịch vụ</span>
                            <span><?= number_format($serviceFee) ?>đ</span>
                        </div>
                        
                        <!-- Voucher/Mã giảm giá -->
                        <div class="voucher-box">
                            <?php if ($autoAppliedPromo): ?>
                            <!-- Khuyến mãi tự động áp dụng -->
                            <div style="background: #d4edda; border: 1px solid #28a745; padding: 12px 15px; border-radius: 8px; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                    <span style="font-size: 20px;">🎁</span>
                                    <strong style="color: #155724;">Khuyến mãi đã áp dụng!</strong>
                                </div>
                                <p style="color: #155724; font-size: 14px; margin: 0;">
                                    <?= htmlspecialchars($autoAppliedPromo['name']) ?>
                                    <?php 
                                    $promoType = $autoAppliedPromo['type'];
                                    $promoValue = floatval($autoAppliedPromo['value']);
                                    // Nếu type là percent nhưng value > 100, coi như là fixed
                                    if ($promoType === 'percent' && $promoValue > 100) {
                                        $promoType = 'fixed';
                                    }
                                    
                                    if ($promoType === 'percent'): ?>
                                    - Giảm <?= number_format($promoValue, 0) ?>%
                                    <?php elseif ($promoType === 'fixed'): ?>
                                    - Giảm <?= number_format($promoValue, 0) ?>đ
                                    <?php elseif ($autoAppliedPromo['type'] === 'freeship'): ?>
                                    - Miễn phí giao hàng
                                    <?php elseif ($autoAppliedPromo['type'] === 'gift'): ?>
                                    - Tặng <?= htmlspecialchars($autoAppliedPromo['gift_product_name']) ?> (x<?= $autoAppliedPromo['gift_quantity'] ?>)
                                    <?php endif; ?>
                                </p>
                            </div>
                            <input type="hidden" name="auto_promo_id" value="<?= $autoAppliedPromo['id'] ?>">
                            <input type="hidden" name="discount_amount" value="<?= $discount ?>">
                            <?php if ($giftProduct): ?>
                            <input type="hidden" name="gift_product_id" value="<?= $giftProduct['id'] ?>">
                            <input type="hidden" name="gift_quantity" value="<?= $giftProduct['quantity'] ?>">
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($giftProduct): ?>
                            <div style="background: #fff3cd; padding: 10px 15px; border-radius: 8px; margin-bottom: 10px;">
                                <span style="font-size: 16px;">🎁</span>
                                <strong>Quà tặng:</strong> <?= htmlspecialchars($giftProduct['name']) ?> x<?= $giftProduct['quantity'] ?>
                            </div>
                            <?php endif; ?>
                            
                            <p style="margin-bottom: 10px; font-weight: 500;">🎫 Mã giảm giá thêm</p>
                            <?php if ($appliedVoucher || $appliedPromo): ?>
                            <div class="voucher-applied">
                                <span>
                                    ✅ <?= htmlspecialchars($appliedVoucher ? $appliedVoucher['code'] : $appliedPromo['code']) ?>
                                    (-<?= number_format($discount) ?>đ)
                                </span>
                                <a href="checkout.php?shop_id=<?= $shopId ?>" style="color: #721c24;">✕ Hủy</a>
                            </div>
                            <input type="hidden" name="applied_voucher" value="<?= $appliedVoucher ? htmlspecialchars($appliedVoucher['code']) : '' ?>">
                            <input type="hidden" name="applied_promo" value="<?= $appliedPromo ? htmlspecialchars($appliedPromo['code']) : '' ?>">
                            <?php if (!$autoAppliedPromo): ?>
                            <input type="hidden" name="discount_amount" value="<?= $discount ?>">
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="voucher-input">
                                <input type="text" name="voucher_code" placeholder="Nhập mã giảm giá thêm...">
                                <button type="submit" name="apply_code" value="1">Áp dụng</button>
                            </div>
                            <?php if ($voucherError): ?>
                            <div class="voucher-error">❌ <?= htmlspecialchars($voucherError) ?></div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                        <div class="summary-item discount-row">
                            <span>Giảm giá</span>
                            <span>-<?= number_format($discount) ?>đ</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-item summary-total">
                            <span>Tổng cộng</span>
                            <span><?= number_format($total - $discount) ?>đ</span>
                        </div>
                        
                        <button type="submit" name="place_order" value="1" class="btn-primary" style="width: 100%; margin-top: 20px; padding: 15px; font-size: 16px;">
                            ✓ Đặt hàng
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
    function fillAddress(el, name, phone, address) {
        document.getElementById('input_name').value = name;
        document.getElementById('input_phone').value = phone;
        document.getElementById('input_address').value = address;
        document.querySelectorAll('.address-item').forEach(i => i.classList.remove('selected'));
        el.parentElement.classList.add('selected');
    }
    function selectPayment(el) {
        document.querySelectorAll('.payment-method').forEach(i => i.classList.remove('selected'));
        el.parentElement.classList.add('selected');
    }
    </script>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
