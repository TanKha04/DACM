<?php
/**
 * Trang tìm kiếm - Tìm món ăn và cửa hàng
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maps_helper.php';

requireRole('customer');

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$base = getBaseUrl();

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$userLat = $user['lat'] ?? DEFAULT_LAT;
$userLng = $user['lng'] ?? DEFAULT_LNG;

// Lấy từ khóa tìm kiếm
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$priceMin = isset($_GET['price_min']) ? (int)$_GET['price_min'] : 0;
$priceMax = isset($_GET['price_max']) ? (int)$_GET['price_max'] : 0;

$products = [];
$shops = [];

if (!empty($query) || !empty($category)) {
    $searchTerm = '%' . $query . '%';
    
    // Tìm sản phẩm
    $productSql = "SELECT p.*, s.name as shop_name, s.latitude, s.longitude, s.status as shop_status,
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id) as avg_rating,
        (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
        FROM products p 
        JOIN shops s ON p.shop_id = s.id 
        WHERE p.status = 'active' AND s.status = 'active'";
    
    $params = [$userLat, $userLng, $userLat];
    
    if (!empty($query)) {
        $productSql .= " AND (p.name LIKE ? OR p.description LIKE ? OR s.name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $productSql .= " AND p.category = ?";
        $params[] = $category;
    }
    
    if ($priceMin > 0) {
        $productSql .= " AND p.price >= ?";
        $params[] = $priceMin;
    }
    
    if ($priceMax > 0) {
        $productSql .= " AND p.price <= ?";
        $params[] = $priceMax;
    }
    
    // Sắp xếp
    switch ($sortBy) {
        case 'price_asc':
            $productSql .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $productSql .= " ORDER BY p.price DESC";
            break;
        case 'rating':
            $productSql .= " ORDER BY avg_rating DESC";
            break;
        case 'distance':
            $productSql .= " ORDER BY distance ASC";
            break;
        default:
            $productSql .= " ORDER BY p.name ASC";
    }
    
    $productSql .= " LIMIT 50";
    
    $stmt = $pdo->prepare($productSql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Tìm cửa hàng
    if (!empty($query)) {
        $shopSql = "SELECT s.*, 
            (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE shop_id = s.id) as review_count,
            (SELECT COUNT(*) FROM products WHERE shop_id = s.id AND status = 'active') as product_count,
            (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) AS distance
            FROM shops s 
            WHERE s.status = 'active' AND (s.name LIKE ? OR s.description LIKE ? OR s.address LIKE ?)
            ORDER BY distance ASC
            LIMIT 20";
        
        $stmt = $pdo->prepare($shopSql);
        $stmt->execute([$userLat, $userLng, $userLat, $searchTerm, $searchTerm, $searchTerm]);
        $shops = $stmt->fetchAll();
    }
}

// Lấy danh mục
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY position");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm <?= htmlspecialchars($query) ?> - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .search-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .search-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
        }
        .search-header h1 { margin: 0 0 15px; font-size: 24px; }
        
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-form input[type="text"] {
            flex: 1;
            min-width: 250px;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
        }
        .search-form select {
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        .search-form button {
            padding: 12px 30px;
            background: #fbbf24;
            color: #7c2d12;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-form button:hover { background: #f59e0b; }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            color: white;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: rgba(255,255,255,0.3);
            border-color: #fbbf24;
        }
        
        .search-results { display: flex; gap: 25px; }
        .results-main { flex: 1; }
        .results-sidebar { width: 280px; }
        
        .result-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .result-section h2 {
            margin: 0 0 20px;
            font-size: 18px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .result-count {
            background: #dc2626;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .product-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }
        .product-card .info {
            padding: 15px;
        }
        .product-card h3 {
            margin: 0 0 8px;
            font-size: 15px;
            color: #2c3e50;
        }
        .product-card .shop-name {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        .product-card .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .product-card .price {
            color: #dc2626;
            font-weight: 700;
            font-size: 16px;
        }
        .product-card .distance {
            font-size: 12px;
            color: #3498db;
        }
        .product-card .btn-add {
            display: block;
            text-align: center;
            padding: 10px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .product-card .btn-add:hover { background: linear-gradient(135deg, #b91c1c, #991b1b); }
        
        .shop-card {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        .shop-card:hover {
            background: #fff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .shop-card img {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }
        .shop-card .info h3 {
            margin: 0 0 5px;
            font-size: 16px;
            color: #2c3e50;
        }
        .shop-card .info p {
            margin: 0 0 5px;
            font-size: 13px;
            color: #7f8c8d;
        }
        .shop-card .meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
        }
        .shop-card .meta span { color: #7f8c8d; }
        
        .category-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .category-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            font-size: 14px;
            transition: all 0.3s;
        }
        .category-item:hover, .category-item.active {
            background: #fef3c7;
            color: #dc2626;
        }
        .category-item .icon { font-size: 20px; }
        
        .no-results {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
        .no-results .icon { font-size: 60px; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .search-results { flex-direction: column; }
            .results-sidebar { width: 100%; order: -1; }
            .category-list { flex-direction: row; flex-wrap: wrap; }
            .category-item { flex: 0 0 auto; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="search-container">
        <div class="search-header">
            <h1>🔍 Tìm kiếm <?= !empty($query) ? ': "' . htmlspecialchars($query) . '"' : '' ?></h1>
            <form class="search-form" method="GET">
                <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Nhập tên món ăn, cửa hàng...">
                <select name="sort">
                    <option value="relevance" <?= $sortBy == 'relevance' ? 'selected' : '' ?>>Liên quan nhất</option>
                    <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Giá thấp → cao</option>
                    <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Giá cao → thấp</option>
                    <option value="rating" <?= $sortBy == 'rating' ? 'selected' : '' ?>>Đánh giá cao</option>
                    <option value="distance" <?= $sortBy == 'distance' ? 'selected' : '' ?>>Gần nhất</option>
                </select>
                <?php if (!empty($category)): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <?php endif; ?>
                <button type="submit">🔍 Tìm kiếm</button>
            </form>
            
            <div class="filters">
                <a href="?q=<?= urlencode($query) ?>" class="filter-btn <?= empty($category) ? 'active' : '' ?>">Tất cả</a>
                <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                <a href="?q=<?= urlencode($query) ?>&category=<?= urlencode($cat['slug']) ?>&sort=<?= $sortBy ?>" 
                   class="filter-btn <?= $category == $cat['slug'] ? 'active' : '' ?>">
                    <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="search-results">
            <div class="results-main">
                <?php if (!empty($shops)): ?>
                <div class="result-section">
                    <h2>🏪 Cửa hàng <span class="result-count"><?= count($shops) ?></span></h2>
                    <?php foreach (array_slice($shops, 0, 5) as $shop): 
                        $shopImage = $shop['image'] ? '../' . $shop['image'] : 'https://via.placeholder.com/80x80?text=Shop';
                        $rating = $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'Mới';
                    ?>
                    <a href="shop_detail.php?id=<?= $shop['id'] ?>" class="shop-card">
                        <img src="<?= $shopImage ?>" alt="">
                        <div class="info">
                            <h3><?= htmlspecialchars($shop['name']) ?></h3>
                            <p><?= htmlspecialchars(mb_substr($shop['address'], 0, 50)) ?>...</p>
                            <div class="meta">
                                <span>⭐ <?= $rating ?></span>
                                <span>📦 <?= $shop['product_count'] ?> món</span>
                                <?php if ($shop['distance']): ?>
                                <span>📍 <?= number_format($shop['distance'], 1) ?>km</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="result-section">
                    <h2>🍕 Món ăn <span class="result-count"><?= count($products) ?></span></h2>
                    
                    <?php if (empty($products)): ?>
                    <div class="no-results">
                        <div class="icon">🔍</div>
                        <h3>Không tìm thấy kết quả</h3>
                        <p>Thử tìm với từ khóa khác hoặc xem các danh mục bên cạnh</p>
                    </div>
                    <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): 
                            $productImage = $product['image'] ? (strpos($product['image'], 'http') === 0 ? $product['image'] : '../' . $product['image']) : 'https://via.placeholder.com/200x140?text=Food';
                        ?>
                        <div class="product-card">
                            <img src="<?= $productImage ?>" alt="">
                            <div class="info">
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="shop-name">🏪 <?= htmlspecialchars($product['shop_name']) ?></p>
                                <div class="meta">
                                    <span class="price"><?= number_format($product['price']) ?>đ</span>
                                    <?php if ($product['distance']): ?>
                                    <span class="distance">📍 <?= number_format($product['distance'], 1) ?>km</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn-add">🛒 Thêm vào giỏ</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="results-sidebar">
                <div class="result-section">
                    <h2>📂 Danh mục</h2>
                    <div class="category-list">
                        <?php foreach ($categories as $cat): ?>
                        <a href="?q=<?= urlencode($query) ?>&category=<?= urlencode($cat['slug']) ?>&sort=<?= $sortBy ?>" 
                           class="category-item <?= $category == $cat['slug'] ? 'active' : '' ?>">
                            <span class="icon"><?= $cat['icon'] ?></span>
                            <span><?= htmlspecialchars($cat['name']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
</body>
</html>
