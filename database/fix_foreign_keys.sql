-- =============================================
-- SỬA TẤT CẢ FOREIGN KEY ĐỂ XÓA USER ĐƯỢC
-- Chạy từng lệnh một nếu gặp lỗi
-- =============================================

-- Tắt kiểm tra foreign key tạm thời
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Sửa bảng orders
ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_1;
ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_2;
ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_3;

ALTER TABLE orders 
    ADD CONSTRAINT orders_ibfk_1 FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT orders_ibfk_2 FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    ADD CONSTRAINT orders_ibfk_3 FOREIGN KEY (shipper_id) REFERENCES users(id) ON DELETE SET NULL;

-- 2. Sửa bảng order_messages
ALTER TABLE order_messages DROP FOREIGN KEY order_messages_ibfk_2;
ALTER TABLE order_messages DROP FOREIGN KEY order_messages_ibfk_3;

ALTER TABLE order_messages 
    ADD CONSTRAINT order_messages_ibfk_2 FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT order_messages_ibfk_3 FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE;

-- 3. Sửa bảng reviews
ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_1;
ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_2;
ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_3;
ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_4;
ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_5;

ALTER TABLE reviews 
    ADD CONSTRAINT reviews_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT reviews_ibfk_2 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    ADD CONSTRAINT reviews_ibfk_3 FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
    ADD CONSTRAINT reviews_ibfk_4 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    ADD CONSTRAINT reviews_ibfk_5 FOREIGN KEY (shipper_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4. Sửa bảng payments
ALTER TABLE payments DROP FOREIGN KEY payments_ibfk_1;
ALTER TABLE payments DROP FOREIGN KEY payments_ibfk_2;

ALTER TABLE payments 
    ADD CONSTRAINT payments_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    ADD CONSTRAINT payments_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- 5. Sửa bảng order_items
ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2;

ALTER TABLE order_items 
    ADD CONSTRAINT order_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

-- 6. Sửa bảng promotion_usage
ALTER TABLE promotion_usage DROP FOREIGN KEY promotion_usage_ibfk_1;
ALTER TABLE promotion_usage DROP FOREIGN KEY promotion_usage_ibfk_2;
ALTER TABLE promotion_usage DROP FOREIGN KEY promotion_usage_ibfk_3;

ALTER TABLE promotion_usage 
    ADD CONSTRAINT promotion_usage_ibfk_1 FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    ADD CONSTRAINT promotion_usage_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT promotion_usage_ibfk_3 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;

-- 7. Sửa bảng voucher_usage
ALTER TABLE voucher_usage DROP FOREIGN KEY voucher_usage_ibfk_1;
ALTER TABLE voucher_usage DROP FOREIGN KEY voucher_usage_ibfk_2;
ALTER TABLE voucher_usage DROP FOREIGN KEY voucher_usage_ibfk_3;

ALTER TABLE voucher_usage 
    ADD CONSTRAINT voucher_usage_ibfk_1 FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    ADD CONSTRAINT voucher_usage_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT voucher_usage_ibfk_3 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;

-- Bật lại kiểm tra foreign key
SET FOREIGN_KEY_CHECKS = 1;
