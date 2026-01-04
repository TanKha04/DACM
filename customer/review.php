<?php
/**
 * Đánh giá đơn hàng - Shop và Shipper
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);
$message = '';

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT o.*, s.name as shop_name, s.id as shop_id, s.image as shop_image,
                       u.name as shipper_name, u.avatar as shipper_avatar
                       FROM orders o 
                       JOIN shops s ON o.shop_id = s.id 
                       LEFT JOIN users u ON o.shipper_id = u.id
                       WHERE o.id = ? AND o.customer_id = ? AND o.status = 'delivered'");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Kiểm tra đã đánh giá shop chưa
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shop_id IS NOT NULL");
$stmt->execute([$orderId, $userId]);
$shopReview = $stmt->fetch();

// Kiểm tra đã đánh giá shipper chưa
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shipper_id IS NOT NULL");
$stmt->execute([$orderId, $userId]);
$shipperReview = $stmt->fetch();

// Lấy sản phẩm trong đơn hàng
$stmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

// Xử lý đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Đánh giá shop
    if ($action === 'review_shop' && !$shopReview) {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, order_id, shop_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $orderId, $order['shop_id'], $rating, $comment]);
            $message = 'success:Cảm ơn bạn đã đánh giá cửa hàng!';
            
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shop_id IS NOT NULL");
            $stmt->execute([$orderId, $userId]);
            $shopReview = $stmt->fetch();
        }
    }
    
    // Sửa đánh giá shop
    if ($action === 'edit_shop' && $shopReview) {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$rating, $comment, $shopReview['id'], $userId]);
            $message = 'success:Đã cập nhật đánh giá cửa hàng!';
            
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shop_id IS NOT NULL");
            $stmt->execute([$orderId, $userId]);
            $shopReview = $stmt->fetch();
        }
    }
    
    // Đánh giá shipper
    if ($action === 'review_shipper' && !$shipperReview && $order['shipper_id']) {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, order_id, shipper_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $orderId, $order['shipper_id'], $rating, $comment]);
            $message = 'success:Cảm ơn bạn đã đánh giá shipper!';
            
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shipper_id IS NOT NULL");
            $stmt->execute([$orderId, $userId]);
            $shipperReview = $stmt->fetch();
        }
    }
    
    // Sửa đánh giá shipper
    if ($action === 'edit_shipper' && $shipperReview) {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$rating, $comment, $shipperReview['id'], $userId]);
            $message = 'success:Đã cập nhật đánh giá shipper!';
            
            // Refresh
            $stmt = $pdo->prepare("SELECT * FROM reviews WHERE order_id = ? AND user_id = ? AND shipper_id IS NOT NULL");
            $stmt->execute([$orderId, $userId]);
            $shipperReview = $stmt->fetch();
        }
    }
}

$shopImage = $order['shop_image'] ? (strpos($order['shop_image'], 'http') === 0 ? $order['shop_image'] : '../' . $order['shop_image']) : 'https://via.placeholder.com/80';
$shipperAvatar = $order['shipper_avatar'] ? (strpos($order['shipper_avatar'], 'http') === 0 ? $order['shipper_avatar'] : '../' . $order['shipper_avatar']) : 'https://via.placeholder.com/80';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá đơn hàng #<?= $orderId ?> - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .review-container { max-width: 700px; margin: 0 auto; }
        .order-summary { background: linear-gradient(135deg, #ff6b35, #ff8c5a); color: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; }
        .order-items-mini { display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding-bottom: 5px; }
        .order-item-mini { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.5); }
        
        .review-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .review-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .review-avatar { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; }
        .review-info h3 { margin: 0 0 5px; }
        .review-info p { color: #7f8c8d; margin: 0; font-size: 14px; }
        
        .star-rating { display: flex; gap: 8px; margin: 15px 0; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 35px; cursor: pointer; color: #ddd; transition: all 0.2s; }
        .star-rating label:hover { transform: scale(1.2); }
        .star-rating input:checked ~ label { color: #ddd; }
        .star-rating label:hover, .star-rating label:hover ~ label { color: #f39c12; }
        .star-rating:not(:hover) input:checked + label,
        .star-rating:not(:hover) input:checked ~ label { color: #ddd; }
        .star-rating:not(:hover) input:checked + label { color: #f39c12; }
        
        /* Fix star rating - reverse order */
        .star-rating { flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating:not(:hover) label { color: #ddd; }
        .star-rating input:checked ~ label { color: #f39c12 !important; }
        
        .rating-labels { display: flex; justify-content: space-between; font-size: 12px; color: #999; margin-top: -5px; }
        
        .review-done { background: #d4edda; border: 1px solid #28a745; padding: 20px; border-radius: 12px; }
        .review-done .stars { color: #f39c12; font-size: 24px; letter-spacing: 3px; }
        .review-done .comment { background: #f8f9fa; padding: 12px; border-radius: 8px; margin-top: 10px; font-style: italic; color: #555; }
        
        .quick-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 15px 0; }
        .quick-tag { padding: 8px 15px; background: #f0f0f0; border-radius: 20px; font-size: 13px; cursor: pointer; transition: all 0.2s; border: none; }
        .quick-tag:hover, .quick-tag.selected { background: #ff6b35; color: white; }
        
        .all-done { text-align: center; padding: 40px 20px; }
        .all-done .icon { font-size: 80px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container review-container">
        <h1 style="margin-bottom: 25px;">⭐ Đánh giá đơn hàng #<?= $orderId ?></h1>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <!-- Tóm tắt đơn hàng -->
        <div class="order-summary">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 18px;">🏪 <?= htmlspecialchars($order['shop_name']) ?></strong>
                    <p style="margin: 5px 0 0; opacity: 0.9;">Đơn hàng ngày <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                </div>
                <div style="text-align: right;">
                    <strong style="font-size: 20px;"><?= number_format($order['total_amount'] + $order['shipping_fee']) ?>đ</strong>
                </div>
            </div>
            <div class="order-items-mini">
                <?php foreach ($orderItems as $item): 
                    $itemImg = $item['image'] ? (strpos($item['image'], 'http') === 0 ? $item['image'] : '../' . $item['image']) : 'https://via.placeholder.com/50';
                ?>
                <img src="<?= $itemImg ?>" class="order-item-mini" title="<?= htmlspecialchars($item['product_name']) ?>">
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($shopReview && $shipperReview): ?>
        <!-- Đã đánh giá hết -->
        <div class="review-card all-done">
            <div class="icon">🎉</div>
            <h2>Cảm ơn bạn đã đánh giá!</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Đánh giá của bạn giúp cải thiện chất lượng dịch vụ.</p>
            <a href="orders.php" class="btn-primary" style="display: inline-block; text-decoration: none; margin-top: 10px;">← Quay lại đơn hàng</a>
        </div>
        <?php endif; ?>
        
        <!-- Đánh giá cửa hàng -->
        <div class="review-card">
            <div class="review-header">
                <img src="<?= $shopImage ?>" class="review-avatar">
                <div class="review-info">
                    <h3>🏪 <?= htmlspecialchars($order['shop_name']) ?></h3>
                    <p>Đánh giá chất lượng món ăn & dịch vụ</p>
                </div>
            </div>
            
            <?php if ($shopReview): ?>
            <div class="review-done" id="shop_review_display">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #155724; font-weight: bold;">✓ Đã đánh giá</span>
                        <span class="stars"><?= str_repeat('★', $shopReview['rating']) . str_repeat('☆', 5 - $shopReview['rating']) ?></span>
                    </div>
                    <button type="button" onclick="toggleEditShop()" style="background: none; border: none; color: #3498db; cursor: pointer; font-size: 14px;">✏️ Sửa</button>
                </div>
                <?php if ($shopReview['comment']): ?>
                <div class="comment">"<?= htmlspecialchars($shopReview['comment']) ?>"</div>
                <?php endif; ?>
            </div>
            
            <!-- Form sửa đánh giá shop -->
            <form method="POST" id="shop_edit_form" style="display: none;">
                <input type="hidden" name="action" value="edit_shop">
                
                <label style="font-weight: 600;">Sửa đánh giá của bạn:</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="edit_shop_star<?= $i ?>" value="<?= $i ?>" <?= $i == $shopReview['rating'] ? 'checked' : '' ?>>
                    <label for="edit_shop_star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
                
                <div class="form-group">
                    <textarea name="comment" rows="3" placeholder="Chia sẻ trải nghiệm của bạn..."><?= htmlspecialchars($shopReview['comment']) ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="toggleEditShop()" class="btn-secondary" style="flex: 1; padding: 12px;">Hủy</button>
                    <button type="submit" class="btn-primary" style="flex: 1; padding: 12px;">Lưu thay đổi</button>
                </div>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="review_shop">
                
                <label style="font-weight: 600;">Bạn đánh giá thế nào?</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="shop_star<?= $i ?>" value="<?= $i ?>" <?= $i == 5 ? 'checked' : '' ?>>
                    <label for="shop_star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
                <div class="rating-labels">
                    <span>Rất tệ</span>
                    <span>Tệ</span>
                    <span>Bình thường</span>
                    <span>Tốt</span>
                    <span>Tuyệt vời</span>
                </div>
                
                <label style="font-weight: 600; display: block; margin-top: 20px;">Nhận xét nhanh:</label>
                <div class="quick-tags">
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shop_comment')">Món ăn ngon</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shop_comment')">Đóng gói cẩn thận</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shop_comment')">Đúng như mô tả</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shop_comment')">Phần ăn đầy đặn</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shop_comment')">Sẽ đặt lại</button>
                </div>
                
                <div class="form-group">
                    <textarea name="comment" id="shop_comment" rows="3" placeholder="Chia sẻ trải nghiệm của bạn về món ăn..."></textarea>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                    Gửi đánh giá cửa hàng
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <!-- Đánh giá shipper -->
        <?php if ($order['shipper_id']): ?>
        <div class="review-card">
            <div class="review-header">
                <img src="<?= $shipperAvatar ?>" class="review-avatar">
                <div class="review-info">
                    <h3>🛵 <?= htmlspecialchars($order['shipper_name']) ?></h3>
                    <p>Đánh giá dịch vụ giao hàng</p>
                </div>
            </div>
            
            <?php if ($shipperReview): ?>
            <div class="review-done" id="shipper_review_display">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: #155724; font-weight: bold;">✓ Đã đánh giá</span>
                        <span class="stars"><?= str_repeat('★', $shipperReview['rating']) . str_repeat('☆', 5 - $shipperReview['rating']) ?></span>
                    </div>
                    <button type="button" onclick="toggleEditShipper()" style="background: none; border: none; color: #3498db; cursor: pointer; font-size: 14px;">✏️ Sửa</button>
                </div>
                <?php if ($shipperReview['comment']): ?>
                <div class="comment">"<?= htmlspecialchars($shipperReview['comment']) ?>"</div>
                <?php endif; ?>
            </div>
            
            <!-- Form sửa đánh giá shipper -->
            <form method="POST" id="shipper_edit_form" style="display: none;">
                <input type="hidden" name="action" value="edit_shipper">
                
                <label style="font-weight: 600;">Sửa đánh giá của bạn:</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="edit_shipper_star<?= $i ?>" value="<?= $i ?>" <?= $i == $shipperReview['rating'] ? 'checked' : '' ?>>
                    <label for="edit_shipper_star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
                
                <div class="form-group">
                    <textarea name="comment" rows="3" placeholder="Chia sẻ trải nghiệm của bạn..."><?= htmlspecialchars($shipperReview['comment']) ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="toggleEditShipper()" class="btn-secondary" style="flex: 1; padding: 12px;">Hủy</button>
                    <button type="submit" class="btn-primary" style="flex: 1; padding: 12px;">Lưu thay đổi</button>
                </div>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="review_shipper">
                
                <label style="font-weight: 600;">Bạn đánh giá thế nào?</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="shipper_star<?= $i ?>" value="<?= $i ?>" <?= $i == 5 ? 'checked' : '' ?>>
                    <label for="shipper_star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
                <div class="rating-labels">
                    <span>Rất tệ</span>
                    <span>Tệ</span>
                    <span>Bình thường</span>
                    <span>Tốt</span>
                    <span>Tuyệt vời</span>
                </div>
                
                <label style="font-weight: 600; display: block; margin-top: 20px;">Nhận xét nhanh:</label>
                <div class="quick-tags">
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shipper_comment')">Giao hàng nhanh</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shipper_comment')">Thái độ thân thiện</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shipper_comment')">Đúng giờ</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shipper_comment')">Cẩn thận</button>
                    <button type="button" class="quick-tag" onclick="addTag(this, 'shipper_comment')">Chuyên nghiệp</button>
                </div>
                
                <div class="form-group">
                    <textarea name="comment" id="shipper_comment" rows="3" placeholder="Chia sẻ trải nghiệm của bạn về shipper..."></textarea>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                    Gửi đánh giá shipper
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="order_detail.php?id=<?= $orderId ?>" style="color: #7f8c8d; text-decoration: none;">← Quay lại chi tiết đơn hàng</a>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <script>
    function addTag(btn, textareaId) {
        const textarea = document.getElementById(textareaId);
        const tag = btn.textContent;
        
        btn.classList.toggle('selected');
        
        if (btn.classList.contains('selected')) {
            if (textarea.value) {
                textarea.value += ', ' + tag;
            } else {
                textarea.value = tag;
            }
        } else {
            // Remove tag
            let tags = textarea.value.split(', ');
            tags = tags.filter(t => t !== tag);
            textarea.value = tags.join(', ');
        }
    }
    
    function toggleEditShop() {
        const display = document.getElementById('shop_review_display');
        const form = document.getElementById('shop_edit_form');
        if (display.style.display === 'none') {
            display.style.display = 'block';
            form.style.display = 'none';
        } else {
            display.style.display = 'none';
            form.style.display = 'block';
        }
    }
    
    function toggleEditShipper() {
        const display = document.getElementById('shipper_review_display');
        const form = document.getElementById('shipper_edit_form');
        if (display.style.display === 'none') {
            display.style.display = 'block';
            form.style.display = 'none';
        } else {
            display.style.display = 'none';
            form.style.display = 'block';
        }
    }
    </script>
</body>
</html>
