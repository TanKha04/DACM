<?php
/**
 * Maps Helper - Các hàm hỗ trợ bản đồ và định vị
 */

/**
 * Tính khoảng cách giữa 2 điểm (Haversine formula)
 * @param float $lat1 Vĩ độ điểm 1
 * @param float $lon1 Kinh độ điểm 1
 * @param float $lat2 Vĩ độ điểm 2
 * @param float $lon2 Kinh độ điểm 2
 * @return float Khoảng cách (km)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Bán kính Trái Đất (km)
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

/**
 * Tính phí giao hàng dựa trên khoảng cách
 * @param float $distance Khoảng cách (km)
 * @param array $config Cấu hình phí ship
 * @param float $subtotal Tổng tiền đơn hàng
 * @return array ['fee' => phí ship, 'is_free' => miễn phí?, 'is_peak' => giờ cao điểm?]
 */
function calculateShippingFee($distance, $config, $subtotal = 0) {
    $baseFee = $config['base_fee'] ?? 15000;
    $perKm = $config['price_per_km'] ?? 5000;
    $perKmFar = $config['price_per_km_far'] ?? 7000;
    $peakHourRate = $config['peak_hour_rate'] ?? 20;
    $freeShipMin = $config['free_ship_min'] ?? 200000;
    
    // Miễn phí ship nếu đơn hàng đủ điều kiện
    if ($subtotal >= $freeShipMin) {
        return ['fee' => 0, 'is_free' => true, 'is_peak' => false, 'distance' => $distance];
    }
    
    $distanceKm = ceil($distance);
    
    // Trong phạm vi 3km: phí cố định 12.000đ
    if ($distance <= 3) {
        $shippingFee = 12000;
    } elseif ($distanceKm <= 5) {
        // 3-5km: base + km * giá/km
        $shippingFee = $baseFee + $distanceKm * $perKm;
    } else {
        // Trên 5km: base + 5km giá thường + km còn lại giá xa
        $shippingFee = $baseFee + (5 * $perKm) + (($distanceKm - 5) * $perKmFar);
    }
    
    // Kiểm tra giờ cao điểm (11h-13h, 18h-20h)
    $currentHour = (int)date('H');
    $isPeakHour = ($currentHour >= 11 && $currentHour < 13) || ($currentHour >= 18 && $currentHour < 20);
    
    if ($isPeakHour) {
        $shippingFee = $shippingFee * (100 + $peakHourRate) / 100;
    }
    
    return [
        'fee' => round($shippingFee), 
        'is_free' => false, 
        'is_peak' => $isPeakHour,
        'distance' => round($distance, 2)
    ];
}

/**
 * Lấy danh sách cửa hàng gần vị trí
 * @param PDO $pdo
 * @param float $lat Vĩ độ
 * @param float $lng Kinh độ
 * @param int $maxDistance Khoảng cách tối đa (km)
 * @return array
 */
function getNearbyShops($pdo, $lat, $lng, $maxDistance = 15) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance,
               (SELECT AVG(rating) FROM reviews WHERE shop_id = s.id) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE shop_id = s.id) as review_count
        FROM shops s 
        WHERE s.status = 'active' 
        AND s.latitude IS NOT NULL 
        AND s.longitude IS NOT NULL
        HAVING distance <= ?
        ORDER BY distance ASC
    ");
    $stmt->execute([$lat, $lng, $lat, $maxDistance]);
    return $stmt->fetchAll();
}

/**
 * Ước tính thời gian giao hàng
 * @param float $distance Khoảng cách (km)
 * @return array ['min' => phút tối thiểu, 'max' => phút tối đa]
 */
function estimateDeliveryTime($distance) {
    // Tốc độ trung bình: 20-30 km/h trong thành phố
    // Thời gian chuẩn bị: 10-15 phút
    $prepTime = 12; // phút
    $avgSpeed = 25; // km/h
    
    $travelTime = ($distance / $avgSpeed) * 60; // phút
    
    $minTime = round($prepTime + $travelTime * 0.8);
    $maxTime = round($prepTime + $travelTime * 1.5);
    
    return [
        'min' => max(15, $minTime),
        'max' => max(25, $maxTime)
    ];
}
