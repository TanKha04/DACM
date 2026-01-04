<?php
/**
 * Chi tiết cửa hàng & menu - Thiết kế kiểu ShopeeFood
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$shopId = (int)($_GET['id'] ?? 0);
$message = '';

// Thêm vào giỏ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    $stmt = $pdo->prepare("SELECT s.user_id FROM products p JOIN shops s ON p.shop_id = s.id WHERE p.id = ?");
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

// Thêm combo vào giỏ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_combo'])) {
    $comboId = (int)$_POST['combo_id'];
    
    // Kiểm tra combo có thuộc shop này và không phải của chính mình
    $stmt = $pdo->prepare("SELECT c.*, s.user_id 
                           FROM combos c 
                           JOIN shops s ON c.shop_id = s.id 
                           WHERE c.id = ? AND c.status = 'active'");
    $stmt->execute([$comboId]);
    $combo = $stmt->fetch();
    
    if ($combo && $combo['user_id'] != $userId) {
        // Thêm vào bảng cart_combos
        $stmt = $pdo->prepare("INSERT INTO cart_combos (user_id, combo_id, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        $stmt->execute([$userId, $comboId]);
        $message = 'success:Đã thêm combo vào giỏ hàng!';
    } else {
        $message = 'error:Không thể thêm combo này!';
    }
}

// Lấy thông tin shop
$stmt = $pdo->prepare("SELECT s.*, u.name as owner_name,
                       (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
                       (SELECT COUNT(*) FROM reviews WHERE shop_id = s.id) as review_count
                       FROM shops s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.status = 'active'");
$stmt->execute([$shopId]);
$shop = $stmt->fetch();

if (!$shop) {
    header('Location: shops.php');
    exit;
}

// Kiểm tra đơn hàng đang xử lý
$hasPendingOrder = false;
$pendingOrderInfo = null;
$stmt = $pdo->prepare("SELECT id, status FROM orders WHERE customer_id = ? AND shop_id = ? AND status NOT IN ('delivered', 'cancelled') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId, $shopId]);
$pendingOrder = $stmt->fetch();
if ($pendingOrder) {
    $hasPendingOrder = true;
    $statusLabels = ['pending' => 'chờ xác nhận', 'confirmed' => 'đã xác nhận', 'preparing' => 'đang chuẩn bị', 'ready' => 'sẵn sàng giao', 'picked' => 'shipper đã lấy', 'delivering' => 'đang giao'];
    $pendingOrderInfo = ['id' => $pendingOrder['id'], 'status' => $statusLabels[$pendingOrder['status']] ?? $pendingOrder['status']];
}

// Lấy tất cả sản phẩm
$stmt = $pdo->prepare("SELECT p.*, 
                       (SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status = 'delivered') as sold_count
                       FROM products p WHERE p.shop_id = ? AND p.status = 'active' ORDER BY sold_count DESC");
$stmt->execute([$shopId]);
$allProducts = $stmt->fetchAll();

// Nhóm theo category
$productsByCategory = [];
foreach ($allProducts as $p) {
    $cat = $p['category'] ?: 'Khác';
    $productsByCategory[$cat][] = $p;
}

// Món phổ biến (bán chạy nhất)
$popularProducts = array_slice(array_filter($allProducts, fn($p) => $p['sold_count'] > 0), 0, 6);

// Lấy khuyến mãi
$stmt = $pdo->prepare("SELECT p.*, pr.name as gift_product_name, pr.price as gift_product_price
                       FROM promotions p 
                       LEFT JOIN products pr ON p.gift_product_id = pr.id
                       WHERE p.shop_id = ? AND p.status = 'active' 
                       AND p.start_date <= NOW() AND p.end_date >= NOW()
                       ORDER BY p.type = 'freeship' DESC, p.value DESC");
$stmt->execute([$shopId]);
$promotions = $stmt->fetchAll();

// Lấy combo
$stmt = $pdo->prepare("SELECT c.*, 
    (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', ci.quantity) SEPARATOR ', ') 
     FROM combo_items ci JOIN products p ON ci.product_id = p.id WHERE ci.combo_id = c.id) as items_text
    FROM combos c WHERE c.shop_id = ? AND c.status = 'active' ORDER BY c.created_at DESC");
$stmt->execute([$shopId]);
$combos = $stmt->fetchAll();

// Lấy số lượng sản phẩm trong giỏ hàng của shop này
$cartQuantities = [];
$stmt = $pdo->prepare("SELECT c.product_id, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.shop_id = ?");
$stmt->execute([$userId, $shopId]);
foreach ($stmt->fetchAll() as $row) {
    $cartQuantities[$row['product_id']] = $row['quantity'];
}

// Lấy số lượng combo trong giỏ hàng
$cartComboQuantities = [];
try {
    $stmt = $pdo->prepare("SELECT cc.combo_id, cc.quantity FROM cart_combos cc JOIN combos c ON cc.combo_id = c.id WHERE cc.user_id = ? AND c.shop_id = ?");
    $stmt->execute([$userId, $shopId]);
    foreach ($stmt->fetchAll() as $row) {
        $cartComboQuantities[$row['combo_id']] = $row['quantity'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shop['name']) ?> - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        * { box-sizing: border-box; }
        .shop-page { max-width: 900px; margin: 0 auto; padding: 15px; background: #f5f5f5; min-height: 100vh; }
        
        /* Header */
        .shop-top { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; }
        
        /* Promo tags */
        .promo-tags { padding-top: 12px; }
        .promo-tag { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; color: #333; }
        .promo-tag-icon { color: #ff6b35; font-size: 16px; }
        .promo-more { color: #ff6b35; font-size: 13px; margin-left: auto; }
        
        /* Section */
        .section-box { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: 600; color: #333; }
        .section-more { color: #ff6b35; font-size: 13px; text-decoration: none; }
        
        /* Popular products - horizontal scroll */
        .popular-scroll { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 10px; }
        .popular-scroll::-webkit-scrollbar { height: 4px; }
        .popular-scroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
        .popular-item { min-width: 140px; position: relative; }
        .popular-item img { width: 140px; height: 100px; object-fit: cover; border-radius: 10px; }
        .popular-badge { position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 3px 8px; border-radius: 10px; }
        .popular-name { font-size: 13px; margin-top: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .popular-price { color: #ff6b35; font-weight: 600; font-size: 14px; }
        .popular-add { position: absolute; bottom: 45px; right: 6px; width: 28px; height: 28px; background: #ff6b35; color: white; border: none; border-radius: 8px; font-size: 18px; cursor: pointer; }
        
        /* Quantity badge */
        .add-btn-wrapper { position: absolute; bottom: 45px; right: 6px; }
        .add-btn-wrapper .popular-add { position: relative; bottom: auto; right: auto; }
        .qty-badge { position: absolute; top: -8px; right: -8px; background: #e74c3c; color: white; font-size: 11px; font-weight: bold; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
        .product-add-wrapper { position: relative; display: inline-block; }
        .product-add-wrapper .qty-badge { top: -6px; right: -6px; }
        
        /* Tabs */
        .menu-tabs { display: flex; gap: 20px; border-bottom: 1px solid #eee; margin-bottom: 15px; overflow-x: auto; }
        .menu-tab { padding: 10px 0; font-size: 14px; color: #666; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent; }
        .menu-tab.active { color: #ff6b35; border-bottom-color: #ff6b35; font-weight: 600; }
        
        /* Flash Sale */
        .flash-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .flash-logo { background: linear-gradient(135deg, #ff6b35, #ff4444); color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .flash-timer { display: flex; gap: 4px; }
        .flash-timer span { background: #333; color: white; padding: 4px 6px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        
        /* Product item */
        .product-item { display: flex; gap: 12px; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .product-item:last-child { border-bottom: none; }
        .product-img { position: relative; }
        .product-img img { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; }
        .product-discount { position: absolute; top: 0; right: 0; background: #ff4444; color: white; font-size: 11px; padding: 2px 6px; border-radius: 0 10px 0 8px; }
        .product-info { flex: 1; }
        .product-name { font-size: 15px; font-weight: 500; margin-bottom: 4px; }
        .product-desc { font-size: 12px; color: #999; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-stats { font-size: 12px; color: #666; margin-bottom: 8px; }
        .product-stats span { margin-right: 10px; }
        .product-price-row { display: flex; align-items: center; justify-content: space-between; }
        .product-price { color: #ff6b35; font-weight: 600; font-size: 16px; }
        .product-price-old { color: #999; text-decoration: line-through; font-size: 13px; margin-left: 8px; }
        .product-add { width: 32px; height: 32px; background: #ff6b35; color: white; border: none; border-radius: 8px; font-size: 20px; cursor: pointer; }
        .product-add:disabled { background: #ccc; cursor: not-allowed; }
        
        /* Hot badge */
        .hot-badge { background: #ff4444; color: white; font-size: 10px; padding: 3px 8px; border-radius: 10px; display: inline-block; margin-top: 5px; }
        
        /* Category section */
        .category-title { font-size: 16px; font-weight: 600; margin: 20px 0 15px; padding-left: 10px; border-left: 3px solid #ff6b35; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="shop-page">
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div style="background: <?= $parts[0] === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $parts[0] === 'success' ? '#155724' : '#721c24' ?>; padding: 12px 15px; border-radius: 10px; margin-bottom: 12px;">
            <?= htmlspecialchars($parts[1]) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($hasPendingOrder): ?>
        <div style="background: #fff3cd; padding: 12px 15px; border-radius: 10px; margin-bottom: 12px; color: #856404;">
            <strong>⚠️ Đang có đơn #<?= $pendingOrderInfo['id'] ?></strong> (<?= $pendingOrderInfo['status'] ?>)
            <a href="order_detail.php?id=<?= $pendingOrderInfo['id'] ?>" style="color: #856404; margin-left: 10px;">Xem →</a>
        </div>
        <?php endif; ?>
        
        <!-- Promos -->
        <?php if (!empty($promotions)): ?>
        <div class="shop-top">
            <div class="promo-tags">
                <?php foreach (array_slice($promotions, 0, 2) as $promo): 
                    $promoType = $promo['type'];
                    $promoValue = floatval($promo['value']);
                    // Nếu type là percent nhưng value > 100, coi như là fixed
                    if ($promoType === 'percent' && $promoValue > 100) {
                        $promoType = 'fixed';
                    }
                ?>
                <div class="promo-tag">
                    <span class="promo-tag-icon">🎫</span>
                    <?php 
                    switch ($promoType) {
                        case 'freeship': echo 'Miễn phí vận chuyển'; break;
                        case 'percent': echo 'Giảm ' . number_format($promoValue, 0) . '% cho đơn từ ' . number_format($promo['min_order']) . 'đ'; break;
                        case 'fixed': echo 'Giảm ' . number_format($promoValue, 0) . 'đ cho đơn từ ' . number_format($promo['min_order']) . 'đ'; break;
                        case 'gift': echo 'Tặng ' . $promo['gift_product_name'] . ' cho đơn từ ' . number_format($promo['min_order']) . 'đ'; break;
                        default: echo htmlspecialchars($promo['name']);
                    }
                    ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($promotions) > 2): ?>
                <span class="promo-more">Xem thêm ›</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Combo -->
        <?php if (!empty($combos)): ?>
        <div class="section-box">
            <div class="section-header">
                <span class="section-title">🎯 Combo tiết kiệm</span>
            </div>
            <div class="popular-scroll">
                <?php foreach ($combos as $combo): 
                    $comboImage = $combo['image'] ? '../' . $combo['image'] : 'https://via.placeholder.com/140x100?text=Combo';
                    $discount = round(($combo['original_price'] - $combo['combo_price']) / $combo['original_price'] * 100);
                    $comboQty = $cartComboQuantities[$combo['id']] ?? 0;
                ?>
                <div class="popular-item" style="min-width: 160px;">
                    <img src="<?= $comboImage ?>" alt="" style="width: 160px;">
                    <span class="popular-badge" style="background: #e74c3c;">-<?= $discount ?>%</span>
                    <form data-ajax data-type="combo" data-id="<?= $combo['id'] ?>" style="display: inline;">
                        <div class="add-btn-wrapper">
                            <button type="submit" class="popular-add" <?= $hasPendingOrder ? 'disabled' : '' ?>>+</button>
                            <?php if ($comboQty > 0): ?>
                            <span class="qty-badge"><?= $comboQty ?></span>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="popular-name" style="font-weight: 600;"><?= htmlspecialchars($combo['name']) ?></div>
                    <div style="font-size: 11px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($combo['items_text']) ?></div>
                    <div>
                        <span class="popular-price"><?= number_format($combo['combo_price']) ?>đ</span>
                        <span style="text-decoration: line-through; color: #999; font-size: 12px;"><?= number_format($combo['original_price']) ?>đ</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Món phổ biến -->
        <?php if (!empty($popularProducts)): ?>
        <div class="section-box">
            <div class="section-header">
                <span class="section-title">🔥 Món phổ biến</span>
            </div>
            <div class="popular-scroll">
                <?php foreach ($popularProducts as $item): 
                    $itemImage = $item['image'] ? '../' . $item['image'] : 'https://via.placeholder.com/140x100?text=Food';
                    $itemQty = $cartQuantities[$item['id']] ?? 0;
                ?>
                <div class="popular-item">
                    <img src="<?= $itemImage ?>" alt="">
                    <span class="popular-badge"><?= $item['sold_count'] ?> đã bán</span>
                    <form data-ajax data-type="product" data-id="<?= $item['id'] ?>" style="display: inline;">
                        <div class="add-btn-wrapper">
                            <button type="submit" class="popular-add" <?= $hasPendingOrder ? 'disabled' : '' ?>>+</button>
                            <?php if ($itemQty > 0): ?>
                            <span class="qty-badge"><?= $itemQty ?></span>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div class="popular-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="popular-price"><?= number_format($item['price']) ?>đ</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Menu chính -->
        <div class="section-box">
            <div class="menu-tabs">
                <div class="menu-tab active" data-cat="all">Tất cả</div>
                <?php foreach (array_keys($productsByCategory) as $cat): ?>
                <div class="menu-tab" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></div>
                <?php endforeach; ?>
            </div>
            
            <div id="menu-content">
                <?php foreach ($productsByCategory as $category => $items): ?>
                <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                    <h3 class="category-title"><?= htmlspecialchars($category) ?> (<?= count($items) ?>)</h3>
                    
                    <?php foreach ($items as $item): 
                        $itemImage = $item['image'] ? '../' . $item['image'] : 'https://via.placeholder.com/100?text=Food';
                        $isHot = $item['sold_count'] >= 10;
                        $itemQty = $cartQuantities[$item['id']] ?? 0;
                    ?>
                    <div class="product-item">
                        <div class="product-img">
                            <img src="<?= $itemImage ?>" alt="">
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="product-desc"><?= htmlspecialchars($item['description'] ?: 'Món ngon từ ' . $shop['name']) ?></div>
                            <div class="product-stats">
                                <?php if ($item['sold_count'] > 0): ?>
                                <span><?= $item['sold_count'] ?> đã bán</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isHot): ?>
                            <span class="hot-badge">🔥 ĐANG BÁN CHẠY</span>
                            <?php endif; ?>
                            <div class="product-price-row">
                                <span class="product-price"><?= number_format($item['price']) ?>đ</span>
                                <form data-ajax data-type="product" data-id="<?= $item['id'] ?>" style="display: inline;">
                                    <div class="product-add-wrapper">
                                        <button type="submit" class="product-add" <?= $hasPendingOrder ? 'disabled' : '' ?>>+</button>
                                        <?php if ($itemQty > 0): ?>
                                        <span class="qty-badge"><?= $itemQty ?></span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    // Tab switching
    document.querySelectorAll('.menu-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.menu-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const cat = this.dataset.cat;
            document.querySelectorAll('.category-section').forEach(section => {
                if (cat === 'all' || section.dataset.category === cat) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        });
    });
    
    // AJAX thêm vào giỏ hàng
    const baseUrl = '<?= getBaseUrl() ?>';
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = this.querySelector('button');
            const wrapper = this.querySelector('.add-btn-wrapper, .product-add-wrapper');
            const type = this.dataset.type;
            const id = this.dataset.id;
            
            // Disable button
            btn.disabled = true;
            btn.textContent = '...';
            
            fetch(baseUrl + '/api/add_to_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                credentials: 'same-origin',
                body: `type=${type}&id=${id}&quantity=1`
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = '+';
                
                if (data.success) {
                    // Cập nhật badge số lượng
                    let badge = wrapper.querySelector('.qty-badge');
                    if (badge) {
                        badge.textContent = data.quantity;
                    } else {
                        badge = document.createElement('span');
                        badge.className = 'qty-badge';
                        badge.textContent = data.quantity;
                        wrapper.appendChild(badge);
                    }
                    
                    // Hiện thông báo nhỏ
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch((err) => {
                btn.disabled = false;
                btn.textContent = '+';
                showToast('Có lỗi xảy ra!', 'error');
                console.error(err);
            });
        });
    });
    
    // Toast notification
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            background: ${type === 'success' ? '#28a745' : '#dc3545'}; color: white;
            padding: 12px 24px; border-radius: 25px; font-size: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 9999;
            animation: fadeInUp 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
    </script>
    
    <style>
    @keyframes fadeInUp { from { opacity: 0; transform: translate(-50%, 20px); } to { opacity: 1; transform: translate(-50%, 0); } }
    @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    </style>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
