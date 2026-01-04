<?php
// Proxy PHP cho Nominatim để tránh CORS và rate-limit trên client
if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lat/lon']);
    exit;
}
$lat = floatval($_GET['lat']);
$lon = floatval($_GET['lon']);
$url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lon}";
$opts = [
    "http" => [
        "header" => "User-Agent: FastFoodApp/1.0\r\n"
    ]
];
$context = stream_context_create($opts);
$result = file_get_contents($url, false, $context);
header('Content-Type: application/json');
echo $result;