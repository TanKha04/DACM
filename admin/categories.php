<?php
/**
 * Admin - Quản lý danh mục món ăn
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pdo = getConnection();
$message = '';
$error = '';

// Xử lý thêm danh mục
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '🍽️');
        $position = (int)($_POST['position'] ?? 0);
        
        if (empty($name) || empty($slug)) {
            $error = 'Vui lòng điền đầy đủ thông tin';
        } else {
            // Kiểm tra slug trùng
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $error = 'Slug đã tồn tại';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, position) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon, $position]);
                $message = 'Thêm danh mục thành công!';
            }
        }
    }
    
    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '🍽️');
        $position = (int)($_POST['position'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if (empty($name) || empty($slug)) {
            $error = 'Vui lòng điền đầy đủ thông tin';
        } else {
            // Kiểm tra slug trùng (trừ chính nó)
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $error = 'Slug đã tồn tại';
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, position = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $icon, $position, $status, $id]);
                $message = 'Cập nhật danh mục thành công!';
            }
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Xóa danh mục thành công!';
    }
}

// Lấy danh sách danh mục
$stmt = $pdo->query("SELECT * FROM categories ORDER BY position ASC, name ASC");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .category-card {
            background: linear-gradient(145deg, #2d3436, #242729);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        .category-card .icon-large {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
        }
        .category-card h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .category-card .slug {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            margin-bottom: 15px;
        }
        .category-card .position {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }
        .category-card .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .category-card .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .category-card .status-badge.active {
            background: rgba(39, 174, 96, 0.2);
            color: #2ecc71;
        }
        .category-card .status-badge.hidden {
            background: rgba(108, 117, 125, 0.2);
            color: #9ca3af;
        }
        .icon-picker {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
        }
        .icon-picker span {
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s;
        }
        .icon-picker span:hover {
            background: rgba(39, 174, 96, 0.2);
        }
        .icon-picker span.selected {
            background: #27ae60;
        }
        .icon-preview {
            font-size: 40px;
            display: inline-block;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🏷️ Quản lý danh mục</h1>
            <button class="btn btn-primary" onclick="openAddModal()">+ Thêm danh mục</button>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
            <div class="category-card">
                <span class="position">#<?= $cat['position'] ?></span>
                <span class="icon-large"><?= $cat['icon'] ?></span>
                <span class="status-badge <?= $cat['status'] ?>"><?= $cat['status'] === 'active' ? 'Hiển thị' : 'Ẩn' ?></span>
                <h3><?= htmlspecialchars($cat['name']) ?></h3>
                <div class="slug">/<?= htmlspecialchars($cat['slug']) ?></div>
                <div class="actions">
                    <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)">✏️ Sửa</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa danh mục này?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">🗑️ Xóa</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($categories)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: rgba(255,255,255,0.5);">
                <p style="font-size: 50px; margin-bottom: 15px;">📂</p>
                <p>Chưa có danh mục nào</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Thêm/Sửa -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">Thêm danh mục</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="categoryForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="form-group">
                        <label>Icon</label>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span class="icon-preview" id="iconPreview">🍽️</span>
                            <input type="hidden" name="icon" id="iconInput" value="🍽️">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleIconPicker()">Chọn icon</button>
                        </div>
                        <div class="icon-picker" id="iconPicker" style="display: none;">
                            <span onclick="selectIcon('🍔')">🍔</span>
                            <span onclick="selectIcon('🍕')">🍕</span>
                            <span onclick="selectIcon('🍗')">🍗</span>
                            <span onclick="selectIcon('🍝')">🍝</span>
                            <span onclick="selectIcon('🥤')">🥤</span>
                            <span onclick="selectIcon('🍰')">🍰</span>
                            <span onclick="selectIcon('🍚')">🍚</span>
                            <span onclick="selectIcon('🍜')">🍜</span>
                            <span onclick="selectIcon('🍱')">🍱</span>
                            <span onclick="selectIcon('🍣')">🍣</span>
                            <span onclick="selectIcon('🥗')">🥗</span>
                            <span onclick="selectIcon('🌮')">🌮</span>
                            <span onclick="selectIcon('🌯')">🌯</span>
                            <span onclick="selectIcon('🥪')">🥪</span>
                            <span onclick="selectIcon('🍟')">🍟</span>
                            <span onclick="selectIcon('🍿')">🍿</span>
                            <span onclick="selectIcon('🧁')">🧁</span>
                            <span onclick="selectIcon('🍩')">🍩</span>
                            <span onclick="selectIcon('🍪')">🍪</span>
                            <span onclick="selectIcon('🎂')">🎂</span>
                            <span onclick="selectIcon('🍦')">🍦</span>
                            <span onclick="selectIcon('☕')">☕</span>
                            <span onclick="selectIcon('🧃')">🧃</span>
                            <span onclick="selectIcon('🍵')">🍵</span>
                            <span onclick="selectIcon('🥛')">🥛</span>
                            <span onclick="selectIcon('🍺')">🍺</span>
                            <span onclick="selectIcon('🍷')">🍷</span>
                            <span onclick="selectIcon('🥂')">🥂</span>
                            <span onclick="selectIcon('🍽️')">🍽️</span>
                            <span onclick="selectIcon('🥡')">🥡</span>
                            <span onclick="selectIcon('🥢')">🥢</span>
                            <span onclick="selectIcon('🧆')">🧆</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tên danh mục *</label>
                        <input type="text" name="name" id="inputName" required placeholder="VD: Burger, Pizza...">
                    </div>
                    
                    <div class="form-group">
                        <label>Slug (URL) *</label>
                        <input type="text" name="slug" id="inputSlug" required placeholder="VD: burger, pizza...">
                        <small style="color: rgba(255,255,255,0.5);">Chỉ dùng chữ thường, số và dấu gạch ngang</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Thứ tự hiển thị</label>
                            <input type="number" name="position" id="inputPosition" value="0" min="0">
                        </div>
                        <div class="form-group" id="statusGroup" style="display: none;">
                            <label>Trạng thái</label>
                            <select name="status" id="inputStatus">
                                <option value="active">Hiển thị</option>
                                <option value="hidden">Ẩn</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Hủy</button>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Thêm danh mục';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('inputName').value = '';
        document.getElementById('inputSlug').value = '';
        document.getElementById('inputPosition').value = '0';
        document.getElementById('iconInput').value = '🍽️';
        document.getElementById('iconPreview').textContent = '🍽️';
        document.getElementById('statusGroup').style.display = 'none';
        document.getElementById('categoryModal').classList.add('active');
    }
    
    function openEditModal(cat) {
        document.getElementById('modalTitle').textContent = 'Sửa danh mục';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = cat.id;
        document.getElementById('inputName').value = cat.name;
        document.getElementById('inputSlug').value = cat.slug;
        document.getElementById('inputPosition').value = cat.position;
        document.getElementById('inputStatus').value = cat.status;
        document.getElementById('iconInput').value = cat.icon;
        document.getElementById('iconPreview').textContent = cat.icon;
        document.getElementById('statusGroup').style.display = 'block';
        document.getElementById('categoryModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('categoryModal').classList.remove('active');
        document.getElementById('iconPicker').style.display = 'none';
    }
    
    function toggleIconPicker() {
        const picker = document.getElementById('iconPicker');
        picker.style.display = picker.style.display === 'none' ? 'grid' : 'none';
    }
    
    function selectIcon(icon) {
        document.getElementById('iconInput').value = icon;
        document.getElementById('iconPreview').textContent = icon;
        document.getElementById('iconPicker').style.display = 'none';
    }
    
    // Tự động tạo slug từ tên
    document.getElementById('inputName').addEventListener('input', function() {
        const name = this.value;
        const slug = name.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'd')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        document.getElementById('inputSlug').value = slug;
    });
    
    // Đóng modal khi click bên ngoài
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>
</html>
