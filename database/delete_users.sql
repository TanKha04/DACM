-- =============================================
-- XÓA USER AN TOÀN (thay ID cần xóa)
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Thay các ID user cần xóa vào đây (ví dụ: 6, 9, 11)
SET @user_ids = '6,9,11';

-- Xóa tất cả dữ liệu liên quan
DELETE FROM order_messages WHERE sender_id IN (6,9,11) OR receiver_id IN (6,9,11);
DELETE FROM voucher_usage WHERE user_id IN (6,9,11);
DELETE FROM promotion_usage WHERE user_id IN (6,9,11);
DELETE FROM payments WHERE user_id IN (6,9,11);
DELETE FROM reviews WHERE user_id IN (6,9,11) OR shipper_id IN (6,9,11);
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_id IN (6,9,11));
DELETE FROM orders WHERE customer_id IN (6,9,11);
DELETE FROM orders WHERE shipper_id IN (6,9,11);
DELETE FROM cart WHERE user_id IN (6,9,11);
DELETE FROM cart_combos WHERE user_id IN (6,9,11);
DELETE FROM user_addresses WHERE user_id IN (6,9,11);
DELETE FROM notifications WHERE user_id IN (6,9,11);
DELETE FROM support_tickets WHERE user_id IN (6,9,11);
DELETE FROM shipper_info WHERE user_id IN (6,9,11);

-- Xóa shop của user (sẽ cascade xóa products, combos, promotions)
DELETE FROM shops WHERE user_id IN (6,9,11);

-- Cuối cùng xóa user
DELETE FROM users WHERE id IN (6,9,11);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Đã xóa thành công!' AS result;
