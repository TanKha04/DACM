<?php
/**
 * Seller - Quản lý sản phẩm
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('seller');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Tạo thư mục uploads nếu chưa có
$uploadDir = __DIR__ . '/../uploads/products/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lấy shop
$stmt = $pdo->prepare("SELECT * FROM shops WHERE user_id = ?");
$stmt->execute([$userId]);
$shop = $stmt->fetch();

$isApproved = $shop && $shop['status'] === 'active';
$isPending = $shop && $shop['status'] === 'pending';
$hasNoShop = !$shop;

// Hàm xử lý upload ảnh
function uploadProductImage($file, $shopId) {
    global $uploadDir;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // Max 5MB
        return ['error' => 'Kích thước file không được vượt quá 5MB'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'product_' . $shopId . '_' . time() . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $newName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => 'uploads/products/' . $newName];
    }
    
    return ['error' => 'Không thể upload file'];
}

// Lấy message từ session (sau redirect)
$message = '';
if (isset($_SESSION['product_message'])) {
    $message = $_SESSION['product_message'];
    unset($_SESSION['product_message']);
}

// Xử lý actions - chỉ cho phép nếu shop đã được duyệt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isApproved) {
    $action = $_POST['action'] ?? '';
    
    // Thêm sản phẩm
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'phần');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = '';
        $error = '';
        
        // Xử lý upload ảnh
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadProductImage($_FILES['image'], $shop['id']);
            if (isset($uploadResult['success'])) {
                $image = $uploadResult['success'];
            } else {
                $error = $uploadResult['error'];
            }
        }
        
        if ($name && $price > 0 && !$error) {
            $stmt = $pdo->prepare("INSERT INTO products (shop_id, name, price, unit, category, description, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$shop['id'], $name, $price, $unit, $category, $description, $image]);
            $_SESSION['product_message'] = 'success:Thêm sản phẩm thành công!';
        } else {
            $_SESSION['product_message'] = 'error:' . ($error ?: 'Vui lòng điền đầy đủ thông tin');
        }
        header('Location: products.php');
        exit;
    }
    
    // Sửa sản phẩm
    if ($action === 'edit') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'phần');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $error = '';
        
        // Lấy ảnh cũ
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ? AND shop_id = ?");
        $stmt->execute([$productId, $shop['id']]);
        $oldProduct = $stmt->fetch();
        $image = $oldProduct['image'] ?? '';
        
        // Xử lý upload ảnh mới
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadProductImage($_FILES['image'], $shop['id']);
            if (isset($uploadResult['success'])) {
                // Xóa ảnh cũ nếu có
                if ($image && file_exists(__DIR__ . '/../' . $image)) {
                    unlink(__DIR__ . '/../' . $image);
                }
                $image = $uploadResult['success'];
            } else {
                $error = $uploadResult['error'];
            }
        }
        
        if (!$error) {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, unit = ?, category = ?, description = ?, image = ? WHERE id = ? AND shop_id = ?");
            $stmt->execute([$name, $price, $unit, $category, $description, $image, $productId, $shop['id']]);
            $_SESSION['product_message'] = 'success:Cập nhật sản phẩm thành công!';
        } else {
            $_SESSION['product_message'] = 'error:' . $error;
        }
        header('Location: products.php');
        exit;
    }
    
    // Xóa sản phẩm
    if ($action === 'delete') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ? AND shop_id = ?");
        $stmt->execute([$productId, $shop['id']]);
        $_SESSION['product_message'] = 'success:Đã xóa sản phẩm!';
        header('Location: products.php');
        exit;
    }
    
    // Ẩn/hiện sản phẩm
    if ($action === 'toggle') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products SET status = IF(status='active','hidden','active') WHERE id = ? AND shop_id = ?");
        $stmt->execute([$productId, $shop['id']]);
        $_SESSION['product_message'] = 'success:Đã cập nhật trạng thái!';
        header('Location: products.php');
        exit;
    }
}

// Lấy danh sách sản phẩm
$products = [];
$categories = [];
if ($isApproved) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE shop_id = ? AND status != 'deleted' ORDER BY category, name");
    $stmt->execute([$shop['id']]);
    $products = $stmt->fetchAll();
    $categories = array_unique(array_column($products, 'category'));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - Seller</title>
    <link rel="stylesheet" href="../assets/css/seller.css">
</head>
<body>
    <?php include '../includes/seller_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🍔 Quản lý sản phẩm</h1>
            <?php if ($isApproved): ?>
            <button class="btn btn-primary" onclick="openAddModal()">+ Thêm sản phẩm</button>
            <?php endif; ?>
        </div>
        
        <?php if ($hasNoShop): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">⚠️</p>
            <h2>Chưa có cửa hàng</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Bạn cần đăng ký mở cửa hàng trước khi có thể đăng sản phẩm.</p>
            <a href="register_shop.php" class="btn btn-primary">Đăng ký mở cửa hàng</a>
        </div>
        <?php elseif ($isPending): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">⏳</p>
            <h2>Đang chờ duyệt</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Yêu cầu mở cửa hàng của bạn đang được Admin xem xét.<br>Vui lòng chờ duyệt để có thể đăng sản phẩm.</p>
        </div>
        <?php else: ?>
        
        <?php if ($message): 
            $parts = explode(':', $message, 2);
        ?>
        <div class="alert alert-<?= $parts[0] ?>"><?= htmlspecialchars($parts[1]) ?></div>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <p style="font-size: 60px;">🍔</p>
            <h2>Chưa có sản phẩm</h2>
            <p style="color: #7f8c8d; margin: 15px 0;">Thêm sản phẩm đầu tiên cho cửa hàng của bạn!</p>
            <button class="btn btn-primary" onclick="openAddModal()">+ Thêm sản phẩm</button>
        </div>
        <?php else: ?>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Giá</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $pImage = $p['image'] ? (strpos($p['image'], 'http') === 0 ? $p['image'] : '../' . $p['image']) : 'https://via.placeholder.com/50?text=No+Image';
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?= $pImage ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                <div>
                                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                                    <?php if ($p['description']): ?>
                                    <br><small style="color: #7f8c8d;"><?= htmlspecialchars(mb_substr($p['description'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['category'] ?: 'Chưa phân loại') ?></td>
                        <td><strong style="color: #27ae60;"><?= number_format($p['price']) ?>đ</strong><small style="color: #7f8c8d;"> / <?= htmlspecialchars($p['unit'] ?? 'phần') ?></small></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] === 'active' ? 'Đang bán' : 'Đã ẩn' ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick='openEditModal(<?= json_encode($p) ?>)'>Sửa</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning"><?= $p['status'] === 'active' ? 'Ẩn' : 'Hiện' ?></button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Xóa sản phẩm này?')">Xóa</button>
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
    
    <!-- Modal Thêm/Sửa sản phẩm -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Thêm sản phẩm</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="productForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="product_id" id="productId">
                    
                    <div class="form-group">
                        <label>Tên sản phẩm *</label>
                        <input type="text" name="name" id="inputName" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Giá (VNĐ) *</label>
                            <input type="number" name="price" id="inputPrice" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Đơn vị</label>
                            <input type="text" name="unit" id="inputUnit" list="unitList" placeholder="VD: phần, ly, đĩa">
                            <datalist id="unitList">
                                <option value="phần">
                                <option value="ly">
                                <option value="đĩa">
                                <option value="tô">
                                <option value="chai">
                                <option value="lon">
                                <option value="hộp">
                                <option value="suất">
                            </datalist>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Danh mục</label>
                            <input type="text" name="category" id="inputCategory" list="categoryList" placeholder="VD: Đồ ăn, Đồ uống">
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea name="description" id="inputDesc" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ảnh sản phẩm</label>
                        <div class="image-upload-area" onclick="document.getElementById('inputImage').click()">
                            <input type="file" name="image" id="inputImage" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            <div id="uploadPlaceholder">
                                <div style="font-size: 40px;">📷</div>
                                <div>Click để chọn ảnh</div>
                                <small style="color: #999;">JPG, PNG, GIF, WEBP - Tối đa 5MB</small>
                            </div>
                            <img id="imagePreview" style="display: none; max-width: 100%; max-height: 200px; border-radius: 8px;">
                        </div>
                        <div id="currentImage" style="display: none; margin-top: 10px;">
                            <small>Ảnh hiện tại:</small><br>
                            <img id="currentImagePreview" style="max-width: 100px; border-radius: 8px; margin-top: 5px;">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu</button>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    .image-upload-area {
        border: 2px dashed #ddd;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: #fafafa;
    }
    .image-upload-area:hover {
        border-color: #4CAF50;
        background: #f0fff0;
    }
    .image-upload-area.has-image {
        border-color: #4CAF50;
        background: #f0fff0;
        padding: 10px;
    }
    </style>
    
    <script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('uploadPlaceholder');
        const uploadArea = input.closest('.image-upload-area');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                uploadArea.classList.add('has-image');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Thêm sản phẩm';
        document.getElementById('formAction').value = 'add';
        document.getElementById('productId').value = '';
        document.getElementById('inputName').value = '';
        document.getElementById('inputPrice').value = '';
        document.getElementById('inputUnit').value = 'phần';
        document.getElementById('inputCategory').value = '';
        document.getElementById('inputDesc').value = '';
        document.getElementById('inputImage').value = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.getElementById('currentImage').style.display = 'none';
        document.querySelector('.image-upload-area').classList.remove('has-image');
        document.getElementById('productModal').classList.add('active');
    }
    
    function openEditModal(product) {
        document.getElementById('modalTitle').textContent = 'Sửa sản phẩm';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('productId').value = product.id;
        document.getElementById('inputName').value = product.name;
        document.getElementById('inputPrice').value = product.price;
        document.getElementById('inputUnit').value = product.unit || 'phần';
        document.getElementById('inputCategory').value = product.category || '';
        document.getElementById('inputDesc').value = product.description || '';
        document.getElementById('inputImage').value = '';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadPlaceholder').style.display = 'block';
        document.querySelector('.image-upload-area').classList.remove('has-image');
        
        // Hiển thị ảnh hiện tại
        if (product.image) {
            document.getElementById('currentImage').style.display = 'block';
            document.getElementById('currentImagePreview').src = '../' + product.image;
        } else {
            document.getElementById('currentImage').style.display = 'none';
        }
        
        document.getElementById('productModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('productModal').classList.remove('active');
    }
    
    document.getElementById('productModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>
</html>
