-- Thêm cột location_updated_at vào bảng shipper_info
ALTER TABLE shipper_info ADD COLUMN IF NOT EXISTS location_updated_at DATETIME DEFAULT NULL;

-- Cập nhật giá trị mặc định cho các record hiện có
UPDATE shipper_info SET location_updated_at = NOW() WHERE location_updated_at IS NULL AND current_lat IS NOT NULL;
