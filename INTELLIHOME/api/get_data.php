<?php
require_once '../config.php';

/**
 * GET SENSOR DATA
 * Returns latest reading + historical data for charts
 * No API key needed for dashboard (session auth)
 */

startSession();

$device_id = $_GET['device_id'] ?? DEFAULT_DEVICE_ID;
$range = $_GET['range'] ?? '24h'; // 24h, 7d, 30d
$limit = intval($_GET['limit'] ?? 1);

try {
    $db = getDB();

    // Latest reading
    $stmt = $db->prepare("
        SELECT * FROM sensor_readings 
        WHERE device_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $latest = $stmt->fetch();

    // Device status
    $stmt2 = $db->prepare("SELECT * FROM device_status WHERE device_id = ?");
    $stmt2->execute([$device_id]);
    $status = $stmt2->fetch();

    // Historical data for charts
    $interval = match($range) {
        '24h' => '1 HOUR',
        '7d' => '6 HOUR', 
        '30d' => '1 DAY',
        default => '1 HOUR'
    };
    $hours = match($range) {
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
        default => 24
    };

    $stmt3 = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as time_label,
            AVG(temperature) as avg_temp,
            AVG(humidity) as avg_humidity,
            AVG(smoke_level) as avg_smoke,
            AVG(light_level) as avg_light,
            MAX(emergency_flag) as has_emergency
        FROM sensor_readings 
        WHERE device_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
        GROUP BY time_label
        ORDER BY time_label ASC
    ");
    $stmt3->execute([$device_id]);
    $history = $stmt3->fetchAll();

    // Recent alerts
    $stmt4 = $db->prepare("
        SELECT * FROM alerts_log 
        WHERE device_id = ? 
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt4->execute([$device_id]);
    $alerts = $stmt4->fetchAll();

    // Active emergency
    $stmt5 = $db->prepare("
        SELECT * FROM emergency_events 
        WHERE device_id = ? AND cleared_at IS NULL 
        ORDER BY triggered_at DESC LIMIT 1
    ");
    $stmt5->execute([$device_id]);
    $activeEmergency = $stmt5->fetch();

    // Stats
    $stmt6 = $db->prepare("
        SELECT 
            COUNT(*) as total_readings,
            AVG(temperature) as avg_temp,
            MAX(temperature) as max_temp,
            MIN(temperature) as min_temp,
            AVG(humidity) as avg_humidity,
            MAX(smoke_level) as max_smoke,
            SUM(emergency_flag) as emergency_count
        FROM sensor_readings 
        WHERE device_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt6->execute([$device_id]);
    $stats = $stmt6->fetch();

    echo json_encode([
        'success' => true,
        'latest' => $latest,
        'status' => $status,
        'history' => $history,
        'alerts' => $alerts,
        'active_emergency' => $activeEmergency,
        'stats' => $stats,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}