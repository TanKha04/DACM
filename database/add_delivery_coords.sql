-- Thêm cột tọa độ giao hàng vào bảng orders
ALTER TABLE orders 
ADD COLUMN delivery_lat DECIMAL(10, 8) DEFAULT NULL AFTER distance_km,
ADD COLUMN delivery_lng DECIMAL(11, 8) DEFAULT NULL AFTER delivery_lat;

-- Cập nhật tọa độ giao hàng từ tọa độ user cho các đơn cũ
UPDATE orders o
JOIN users u ON o.customer_id = u.id
SET o.delivery_lat = u.lat, o.delivery_lng = u.lng
WHERE o.delivery_lat IS NULL AND u.lat IS NOT NULL;
