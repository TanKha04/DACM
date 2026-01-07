<?php
/**
 * Cấu hình vị trí mặc định cho hệ thống
 * Thay đổi tọa độ này để phù hợp với khu vực hoạt động của bạn
 */

// Vị trí mặc định (Trà Vinh)
define('DEFAULT_LAT', 9.934739);
define('DEFAULT_LNG', 106.345333);

// Tên địa điểm mặc định
define('DEFAULT_LOCATION_NAME', 'Trà Vinh');

// Bán kính tìm kiếm shipper (km)
define('SHIPPER_SEARCH_RADIUS', 10);

// Bán kính giao hàng tối đa (km)
define('MAX_DELIVERY_RADIUS', 10);

// Hàm lấy vị trí mặc định dạng array
function getDefaultLocation() {
    return [
        'lat' => DEFAULT_LAT,
        'lng' => DEFAULT_LNG,
        'name' => DEFAULT_LOCATION_NAME
    ];
}
