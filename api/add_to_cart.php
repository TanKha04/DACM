<?php
/**
 * API thêm sản phẩm/combo vào giỏ hàng
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Chỉ cần kiểm tra user đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];

$type = $_POST['type'] ?? 'product';
$id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    if ($type === 'product') {
        // Kiểm tra không phải sản phẩm của chính mình
        $stmt = $pdo->prepare("SELECT s.user_id FROM products p JOIN shops s ON p.shop_id = s.id WHERE p.id = ?");
        $stmt->execute([$id]);
        $productInfo = $stmt->fetch();
        
        if ($productInfo && $productInfo['user_id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể đặt hàng sản phẩm của chính mình!']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->execute([$userId, $id, $quantity, $quantity]);
        
        // Lấy số lượng mới
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $id]);
        $newQty = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'message' => 'Đã thêm vào giỏ hàng!', 'quantity' => $newQty]);
        
    } elseif ($type === 'combo') {
        // Kiểm tra combo
        $stmt = $pdo->prepare("SELECT c.*, s.user_id FROM combos c JOIN shops s ON c.shop_id = s.id WHERE c.id = ? AND c.status = 'active'");
        $stmt->execute([$id]);
        $combo = $stmt->fetch();
        
        if (!$combo) {
            echo json_encode(['success' => false, 'message' => 'Combo không tồn tại!']);
            exit;
        }
        
        if ($combo['user_id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể đặt combo của chính mình!']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO cart_combos (user_id, combo_id, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        $stmt->execute([$userId, $id]);
        
        // Lấy số lượng mới
        $stmt = $pdo->prepare("SELECT quantity FROM cart_combos WHERE user_id = ? AND combo_id = ?");
        $stmt->execute([$userId, $id]);
        $newQty = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'message' => 'Đã thêm combo vào giỏ hàng!', 'quantity' => $newQty]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
}
