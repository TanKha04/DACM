-- =============================================
-- DATABASE: FASTFOOD DELIVERY SYSTEM
-- =============================================

CREATE DATABASE IF NOT EXISTS fastfood_delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fastfood_delivery;

-- =============================================
-- BẢNG USERS
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    role ENUM('customer', 'seller', 'shipper') DEFAULT 'customer',
    is_admin TINYINT(1) DEFAULT 0,
    status ENUM('active', 'pending', 'blocked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- BẢNG SHOPS
-- =============================================
CREATE TABLE shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    image VARCHAR(255),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    food_safety_cert VARCHAR(255) DEFAULT NULL,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    status ENUM('active', 'pending', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BẢNG PRODUCTS
-- =============================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(50) DEFAULT 'phần',
    image VARCHAR(255),
    category VARCHAR(50),
    status ENUM('active', 'hidden', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BẢNG CART - Giỏ hàng
-- =============================================
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart (user_id, product_id)
) ENGINE=InnoDB;

-- =============================================
-- BẢNG ORDERS
-- =============================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    shop_id INT NOT NULL,
    shipper_id INT DEFAULT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    shipping_fee DECIMAL(10, 2) DEFAULT 0,
    commission_fee DECIMAL(10, 2) DEFAULT 0,
    delivery_address TEXT NOT NULL,
    delivery_phone VARCHAR(20),
    delivery_name VARCHAR(100),
    distance_km DECIMAL(5, 2) DEFAULT 0,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'picked', 'delivering', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'ewallet') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    note TEXT,
    cancelled_by ENUM('customer', 'seller', 'admin') DEFAULT NULL,
    cancel_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    FOREIGN KEY (shipper_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- BẢNG ORDER_ITEMS
-- =============================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150),
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;


-- =============================================
-- BẢNG SHIPPER_INFO
-- =============================================
CREATE TABLE shipper_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(20),
    id_card VARCHAR(20),
    current_lat DECIMAL(10, 8),
    current_lng DECIMAL(11, 8),
    is_available TINYINT(1) DEFAULT 1,
    total_deliveries INT DEFAULT 0,
    total_earnings DECIMAL(12, 2) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BẢNG REVIEWS - Đánh giá
-- =============================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    shop_id INT DEFAULT NULL,
    product_id INT DEFAULT NULL,
    shipper_id INT DEFAULT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    reply TEXT,
    reply_at TIMESTAMP NULL,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (shipper_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- BẢNG USER_ADDRESSES - Địa chỉ giao hàng
-- =============================================
CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BẢNG PAYMENTS - Thanh toán
-- =============================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    method ENUM('cash', 'card', 'ewallet') DEFAULT 'cash',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================
-- BẢNG SHIPPING_CONFIG
-- =============================================
CREATE TABLE shipping_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    base_fee DECIMAL(10, 2) DEFAULT 15000,
    price_per_km DECIMAL(10, 2) DEFAULT 5000,
    price_per_km_far DECIMAL(10, 2) DEFAULT 7000,
    peak_hour_rate DECIMAL(5, 2) DEFAULT 20,
    default_commission DECIMAL(5, 2) DEFAULT 10.00,
    service_fee DECIMAL(10, 2) DEFAULT 3000,
    free_ship_min DECIMAL(10, 2) DEFAULT 200000,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- BẢNG BANNERS
-- =============================================
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    image VARCHAR(255) NOT NULL,
    link VARCHAR(255),
    position INT DEFAULT 0,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- BẢNG NOTIFICATIONS
-- =============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('order', 'system', 'promo') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- DỮ LIỆU MẪU
-- =============================================
INSERT INTO shipping_config (base_fee, price_per_km, price_per_km_far, default_commission, service_fee) VALUES (15000, 5000, 7000, 10.00, 3000);


-- =============================================
-- BẢNG PROMOTIONS - Chương trình khuyến mãi
-- =============================================
CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) UNIQUE,
    type ENUM('percent', 'fixed', 'freeship', 'gift', 'combo') DEFAULT 'percent',
    value DECIMAL(10, 2) NOT NULL,
    min_order DECIMAL(10, 2) DEFAULT 0,
    max_discount DECIMAL(10, 2) DEFAULT NULL,
    gift_product_id INT DEFAULT NULL,
    gift_quantity INT DEFAULT 1,
    buy_quantity INT DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    FOREIGN KEY (gift_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- BẢNG PROMOTION_USAGE - Lịch sử sử dụng mã
-- =============================================
CREATE TABLE promotion_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

-- =============================================
-- BẢNG VOUCHERS - Voucher toàn hệ thống (Admin)
-- =============================================
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('percent', 'fixed', 'freeship') DEFAULT 'percent',
    value DECIMAL(10, 2) NOT NULL,
    min_order DECIMAL(10, 2) DEFAULT 0,
    max_discount DECIMAL(10, 2) DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    user_limit INT DEFAULT 1,
    apply_to ENUM('all', 'new_user', 'vip') DEFAULT 'all',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- BẢNG VOUCHER_USAGE - Lịch sử sử dụng voucher
-- =============================================
CREATE TABLE voucher_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;


-- =============================================
-- BẢNG SUPPORT_TICKETS - Yêu cầu hỗ trợ
-- =============================================
CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    category ENUM('account', 'order', 'payment', 'technical', 'other') DEFAULT 'other',
    message TEXT NOT NULL,
    status ENUM('open', 'processing', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    admin_reply TEXT,
    admin_id INT DEFAULT NULL,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- =============================================
-- BẢNG ORDER_MESSAGES - Tin nhắn theo đơn hàng
-- =============================================
CREATE TABLE order_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
) ENGINE=InnoDB;


-- =============================================
-- CẬP NHẬT: Thêm cột vị trí cho users và cấu hình khoảng cách
-- =============================================
ALTER TABLE users ADD COLUMN lat DECIMAL(10, 8) DEFAULT NULL;
ALTER TABLE users ADD COLUMN lng DECIMAL(11, 8) DEFAULT NULL;
ALTER TABLE shipping_config ADD COLUMN max_shop_distance INT DEFAULT 5;


-- =============================================
-- BẢNG COMBOS - Combo sản phẩm
-- =============================================
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    original_price DECIMAL(10, 2) NOT NULL,
    combo_price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'hidden', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- BẢNG COMBO_ITEMS - Sản phẩm trong combo
-- =============================================
CREATE TABLE combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- =============================================
-- BẢNG CART_COMBOS - Combo trong giỏ hàng
-- =============================================
CREATE TABLE cart_combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    combo_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_combo (user_id, combo_id)
) ENGINE=InnoDB;


-- =============================================
-- BẢNG CATEGORIES - Danh mục món ăn
-- =============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT '🍽️',
    image VARCHAR(255) DEFAULT NULL,
    position INT DEFAULT 0,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Dữ liệu mẫu danh mục
INSERT INTO categories (name, slug, icon, position) VALUES
('Burger', 'burger', '🍔', 1),
('Pizza', 'pizza', '🍕', 2),
('Gà Rán', 'ga-ran', '🍗', 3),
('Mỳ Ý', 'my-y', '🍝', 4),
('Đồ Uống', 'do-uong', '🥤', 5),
('Tráng Miệng', 'trang-mieng', '🍰', 6),
('Cơm', 'com', '🍚', 7),
('Phở & Bún', 'pho-bun', '🍜', 8);
