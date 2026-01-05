<?php
/**
 * API Products - RESTful API cho sản phẩm
 * 
 * Endpoints:
 * GET    /api/products.php              - Lấy danh sách sản phẩm
 * GET    /api/products.php?id=1         - Lấy chi tiết sản phẩm
 * POST   /api/products.php              - Thêm sản phẩm mới
 * PUT    /api/products.php?id=1         - Cập nhật sản phẩm
 * DELETE /api/products.php?id=1         - Xóa sản phẩm
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Lấy dữ liệu JSON từ body
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?? [];
}

// Validate required fields
function validateRequired($data, $fields) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Trường '$field' là bắt buộc";
        }
    }
    return $errors;
}

try {
    switch ($method) {
        // ========== GET - Lấy sản phẩm ==========
        case 'GET':
            if (isset($_GET['id'])) {
                // Lấy chi tiết 1 sản phẩm
                $id = (int)$_GET['id'];
                $stmt = $pdo->prepare("
                    SELECT p.*, s.name as shop_name, c.name as category_name 
                    FROM products p 
                    LEFT JOIN shops s ON p.shop_id = s.id 
                    LEFT JOIN categories c ON p.category = c.slug
                    WHERE p.id = ?
                ");
                $stmt->execute([$id]);
                $product = $stmt->fetch();
                
                if ($product) {
                    jsonResponse([
                        'success' => true,
                        'data' => $product
                    ]);
                } else {
                    jsonResponse([
                        'success' => false,
                        'message' => 'Không tìm thấy sản phẩm'
                    ], 404);
                }
            } else {
                // Lấy danh sách sản phẩm
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 10);
                $offset = ($page - 1) * $limit;
                $shop_id = $_GET['shop_id'] ?? null;
                $category = $_GET['category'] ?? null;
                $search = $_GET['search'] ?? null;
                
                $where = "WHERE p.status != 'deleted'";
                $params = [];
                
                if ($shop_id) {
                    $where .= " AND p.shop_id = ?";
                    $params[] = $shop_id;
                }
                if ($category) {
                    $where .= " AND p.category = ?";
                    $params[] = $category;
                }
                if ($search) {
                    $where .= " AND p.name LIKE ?";
                    $params[] = "%$search%";
                }
                
                // Đếm tổng
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                // Lấy danh sách
                $params[] = $limit;
                $params[] = $offset;
                $stmt = $pdo->prepare("
                    SELECT p.*, s.name as shop_name 
                    FROM products p 
                    LEFT JOIN shops s ON p.shop_id = s.id 
                    $where 
                    ORDER BY p.created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                jsonResponse([
                    'success' => true,
                    'data' => $products,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        // ========== POST - Thêm sản phẩm ==========
        case 'POST':
            $data = getJsonInput();
            
            // Validate
            $errors = validateRequired($data, ['shop_id', 'name', 'price']);
            if (!empty($errors)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $errors
                ], 400);
            }
            
            // Kiểm tra shop tồn tại
            $stmt = $pdo->prepare("SELECT id FROM shops WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['shop_id']]);
            if (!$stmt->fetch()) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Shop không tồn tại hoặc chưa được duyệt'
                ], 400);
            }
            
            // Thêm sản phẩm
            $stmt = $pdo->prepare("
                INSERT INTO products (shop_id, name, description, price, unit, image, category, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['shop_id'],
                $data['name'],
                $data['description'] ?? '',
                $data['price'],
                $data['unit'] ?? 'phần',
                $data['image'] ?? null,
                $data['category'] ?? null,
                $data['status'] ?? 'active'
            ]);
            
            $productId = $pdo->lastInsertId();
            
            // Lấy sản phẩm vừa tạo
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Thêm sản phẩm thành công',
                'data' => $product
            ], 201);
            break;
            
        // ========== PUT - Cập nhật sản phẩm ==========
        case 'PUT':
            if (!isset($_GET['id'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Thiếu ID sản phẩm'
                ], 400);
            }
            
            $id = (int)$_GET['id'];
            $data = getJsonInput();
            
            // Kiểm tra sản phẩm tồn tại
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status != 'deleted'");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm'
                ], 404);
            }
            
            // Cập nhật các trường được gửi lên
            $updates = [];
            $params = [];
            
            $allowedFields = ['name', 'description', 'price', 'unit', 'image', 'category', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Không có dữ liệu để cập nhật'
                ], 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE products SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Lấy sản phẩm sau khi cập nhật
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data' => $product
            ]);
            break;
            
        // ========== DELETE - Xóa sản phẩm ==========
        case 'DELETE':
            if (!isset($_GET['id'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Thiếu ID sản phẩm'
                ], 400);
            }
            
            $id = (int)$_GET['id'];
            
            // Kiểm tra sản phẩm tồn tại
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status != 'deleted'");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm'
                ], 404);
            }
            
            // Soft delete - chỉ đánh dấu là deleted
            $stmt = $pdo->prepare("UPDATE products SET status = 'deleted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công'
            ]);
            break;
            
        default:
            jsonResponse([
                'success' => false,
                'message' => 'Method không được hỗ trợ'
            ], 405);
    }
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Lỗi server: ' . $e->getMessage()
    ], 500);
}
