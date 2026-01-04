<?php
/**
 * Seller - Quản lý Combo
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Tạo thư mục uploads nếu chưa có
$uploadDir = __DIR__ . '/../uploads/combos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

$isApproved = $shop && $shop['status'] === 'active';

// Hàm upload ảnh
function uploadComboImage($file, $shopId) {
    global $uploadDir;
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Kích thước file không được vượt quá 5MB'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'combo_' . $shopId . '_' . time() . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => 'uploads/combos/' . $newName];
    }
    return ['error' => 'Không thể upload file'];
}

// Lấy message từ session
if (isset($_SESSION['combo_message'])) {
    $message = $_SESSION['combo_message'];
    unset($_SESSION['combo_message']);
}

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isApproved) {
    $action = $_POST['action'] ?? '';
    
    // Thêm combo
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comboPrice = (float)($_POST['combo_price'] ?? 0);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $image = '';
        $error = '';
        
        // Upload ảnh
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadComboImage($_FILES['image'], $shop['id']);
            if (isset($uploadResult['success'])) {
                $image = $uploadResult['success'];
            } else {
                $error = $uploadResult['error'];
            }
        }
        
        if ($name && $comboPrice > 0 && !empty($productIds) && !$error) {
            // Tính giá gốc
            $originalPrice = 0;
            foreach ($productIds as $idx => $pid) {
                $qty = (int)($quantities[$idx] ?? 1);
                $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ? AND shop_id = ?");
                $stmt->execute([$pid, $shop['id']]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $originalPrice += $prod['price'] * $qty;
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO combos (shop_id, name, description, image, original_price, combo_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$shop['id'], $name, $description, $image, $originalPrice, $comboPrice]);
            $comboId = $pdo->lastInsertId();
            
            // Thêm sản phẩm vào combo
            foreach ($productIds as $idx => $pid) {
                $qty = (int)($quantities[$idx] ?? 1);
                $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$comboId, $pid, $qty]);
            }
            
            $_SESSION['combo_message'] = 'success:Thêm combo thành công!';
        } else {
            $_SESSION['combo_message'] = 'error:' . ($error ?: 'Vui lòng điền đầy đủ thông tin và chọn ít nhất 1 sản phẩm');
        }
        header('Location: combos.php');
        exit;
    }
    
    // Sửa combo
    if ($action === 'edit') {
        $comboId = (int)($_POST['combo_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $comboPrice = (float)($_POST['combo_price'] ?? 0);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $error = '';
        
        // Lấy ảnh cũ
        $stmt = $pdo->prepare("SELECT image FROM combos WHERE id = ? AND shop_id = ?");
        $stmt->execute([$comboId, $shop['id']]);
        $oldCombo = $stmt->fetch();
        $image = $oldCombo['image'] ?? '';
        
        // Upload ảnh mới
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadComboImage($_FILES['image'], $shop['id']);
            if (isset($uploadResult['success'])) {
                if ($image && file_exists(__DIR__ . '/../' . $image)) {
                    unlink(__DIR__ . '/../' . $image);
                }
                $image = $uploadResult['success'];
            } else {
                $error = $uploadResult['error'];
            }
        }
        
        if (!$error && !empty($productIds)) {
            // Tính giá gốc
            $originalPrice = 0;
            foreach ($productIds as $idx => $pid) {
                $qty = (int)($quantities[$idx] ?? 1);
                $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ? AND shop_id = ?");
                $stmt->execute([$pid, $shop['id']]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $originalPrice += $prod['price'] * $qty;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE combos SET name = ?, description = ?, image = ?, original_price = ?, combo_price = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$name, $description, $image, $originalPrice, $comboPrice, $comboId, $shop['id']]);
            
            // Xóa items cũ và thêm mới
            $stmt = $pdo->prepare("DELETE FROM combo_items WHERE combo_id = ?");
            $stmt->execute([$comboId]);
            
            foreach ($productIds as $idx => $pid) {
                $qty = (int)($quantities[$idx] ?? 1);
                $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$comboId, $pid, $qty]);
            }
            
            $_SESSION['combo_message'] = 'success:Cập nhật combo thành công!';
        } else {
            $_SESSION['combo_message'] = 'error:' . ($error ?: 'Vui lòng chọn ít nhất 1 sản phẩm');
        }
        header('Location: combos.php');
        exit;
    }
    
    // Xóa combo
    if ($action === 'delete') {
        $comboId = (int)($_POST['combo_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE combos SET status = 'deleted' WHERE id = ? AND shop_id = ?");
        $stmt->execute([$comboId, $shop['id']]);
        $_SESSION['combo_message'] = 'success:Đã xóa combo!';
        header('Location: combos.php');
        exit;
    }
    
    // Ẩn/hiện combo
    if ($action === 'toggle') {
        $comboId = (int)($_POST['combo_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE combos SET status = IF(status='active','hidden','active') WHERE id = ? AND shop_id = ?");
        $stmt->execute([$comboId, $shop['id']]);
        $_SESSION['combo_message'] = 'success:Đã cập nhật trạng thái!';
        header('Location: combos.php');
        exit;
    }
}

// Lấy danh sách combo
$combos = [];
$products = [];
if ($isApproved) {
    $stmt = $pdo->prepare("SELECT c.*, 
        (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', ci.quantity) SEPARATOR ', ') 
         FROM combo_items ci JOIN products p ON ci.product_id = p.id WHERE ci.combo_id = c.id) as items_text
        FROM combos c WHERE c.shop_id = ? AND c.status != 'deleted' ORDER BY c.created_at DESC");
    $stmt->execute([$shop['id']]);
    $combos = $stmt->fetchAll();
    
    // Lấy sản phẩm để chọn
    $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$shop['id']]);
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Combo - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🎯 Quản lý Combo</h1>
            <?php if ($isApproved): ?>
            <button class="btn btn-primary" onclick="openAddModal()">+ Thêm combo</button>
            <?php endif; ?>
        </div>
        
        <?php if (!$isApproved): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">⚠️</p>
            <h2>Cửa hàng chưa được duyệt</h2>
            <p style="color: #7f8c8d;">Vui lòng chờ Admin duyệt cửa hàng để sử dụng tính năng này.</p>
        </div>
        <?php else: ?>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if (empty($combos)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🎯</p>
            <h2>Chưa có combo nào</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Tạo combo để tăng doanh số bán hàng!</p>
            <button class="btn btn-primary" onclick="openAddModal()">+ Thêm combo</button>
        </div>
        <?php else: ?>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Combo</th>
                        <th>Sản phẩm</th>
                        <th>Giá gốc</th>
                        <th>Giá combo</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($combos as $c): 
                        $cImage = $c['image'] ? '../' . $c['image'] : 'https://via.placeholder.com/50?text=Combo';
                        $discount = round(($c['original_price'] - $c['combo_price']) / $c['original_price'] * 100);
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?= $cImage ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                <div>
                                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                                    <br><small style="color: #e74c3c;">-<?= $discount ?>%</small>
                                </div>
                            </div>
                        </td>
                        <td><small><?= htmlspecialchars($c['items_text'] ?: 'Chưa có sản phẩm') ?></small></td>
                        <td><span style="text-decoration: line-through; color: #999;"><?= number_format($c['original_price']) ?>đ</span></td>
                        <td><strong style="color: #e74c3c;"><?= number_format($c['combo_price']) ?>đ</strong></td>
                        <td><span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] === 'active' ? 'Đang bán' : 'Đã ẩn' ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $c['id'] ?>)">Sửa</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="combo_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning"><?= $c['status'] === 'active' ? 'Ẩn' : 'Hiện' ?></button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="combo_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Xóa combo này?')">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal Thêm/Sửa Combo -->
    <div class="modal-overlay" id="comboModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Thêm combo</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="comboForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="combo_id" id="comboId">
                    
                    <div class="form-group">
                        <label>Tên combo *</label>
                        <input type="text" name="name" id="inputName" required placeholder="VD: Combo tiết kiệm">
                    </div>
                    
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea name="description" id="inputDesc" rows="2" placeholder="Mô tả combo..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Chọn sản phẩm *</label>
                        <div id="productList" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; padding: 10px;">
                            <?php foreach ($products as $p): ?>
                            <div class="product-select-item" style="display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid #eee;">
                                <input type="checkbox" class="product-checkbox" value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>">
                                <span style="flex: 1;"><?= htmlspecialchars($p['name']) ?> - <?= number_format($p['price']) ?>đ</span>
                                <input type="number" class="product-qty" min="1" value="1" style="width: 60px; padding: 4px;" disabled>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Giá gốc</label>
                            <input type="text" id="originalPrice" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Giá combo *</label>
                            <input type="number" name="combo_price" id="inputComboPrice" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ảnh combo</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    
                    <div id="hiddenInputs"></div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu combo</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    const combosData = <?= json_encode($combos) ?>;
    const comboItems = {};
    <?php 
    foreach ($combos as $c) {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM combo_items WHERE combo_id = ?");
        $stmt->execute([$c['id']]);
        $items = $stmt->fetchAll();
        echo "comboItems[{$c['id']}] = " . json_encode($items) . ";\n";
    }
    ?>
    
    // Tính giá gốc khi chọn sản phẩm
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const qtyInput = this.closest('.product-select-item').querySelector('.product-qty');
            qtyInput.disabled = !this.checked;
            calculateOriginalPrice();
        });
    });
    
    document.querySelectorAll('.product-qty').forEach(input => {
        input.addEventListener('change', calculateOriginalPrice);
    });
    
    function calculateOriginalPrice() {
        let total = 0;
        document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
            const price = parseFloat(cb.dataset.price);
            const qty = parseInt(cb.closest('.product-select-item').querySelector('.product-qty').value) || 1;
            total += price * qty;
        });
        document.getElementById('originalPrice').value = total.toLocaleString('vi-VN') + 'đ';
    }
    
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Thêm combo';
        document.getElementById('formAction').value = 'add';
        document.getElementById('comboId').value = '';
        document.getElementById('inputName').value = '';
        document.getElementById('inputDesc').value = '';
        document.getElementById('inputComboPrice').value = '';
        document.getElementById('originalPrice').value = '';
        
        // Reset checkboxes
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.product-select-item').querySelector('.product-qty').disabled = true;
            cb.closest('.product-select-item').querySelector('.product-qty').value = 1;
        });
        
        document.getElementById('comboModal').classList.add('active');
    }
    
    function openEditModal(comboId) {
        const combo = combosData.find(c => c.id == comboId);
        if (!combo) return;
        
        document.getElementById('modalTitle').textContent = 'Sửa combo';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('comboId').value = comboId;
        document.getElementById('inputName').value = combo.name;
        document.getElementById('inputDesc').value = combo.description || '';
        document.getElementById('inputComboPrice').value = combo.combo_price;
        
        // Reset và set checkboxes
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.product-select-item').querySelector('.product-qty').disabled = true;
            cb.closest('.product-select-item').querySelector('.product-qty').value = 1;
        });
        
        const items = comboItems[comboId] || [];
        items.forEach(item => {
            const cb = document.querySelector(`.product-checkbox[value="${item.product_id}"]`);
            if (cb) {
                cb.checked = true;
                const qtyInput = cb.closest('.product-select-item').querySelector('.product-qty');
                qtyInput.disabled = false;
                qtyInput.value = item.quantity;
            }
        });
        
        calculateOriginalPrice();
        document.getElementById('comboModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('comboModal').classList.remove('active');
    }
    
    // Thêm hidden inputs trước khi submit
    document.getElementById('comboForm').addEventListener('submit', function(e) {
        const hiddenInputs = document.getElementById('hiddenInputs');
        hiddenInputs.innerHTML = '';
        
        document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
            const qty = cb.closest('.product-select-item').querySelector('.product-qty').value;
            hiddenInputs.innerHTML += `<input type="hidden" name="product_ids[]" value="${cb.value}">`;
            hiddenInputs.innerHTML += `<input type="hidden" name="quantities[]" value="${qty}">`;
        });
    });
    
    document.getElementById('comboModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>
</html>
