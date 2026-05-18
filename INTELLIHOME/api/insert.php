<?php
require_once '../config.php';

/**
 * INSERT SENSOR DATA
 * Accepts: POST JSON from Python Bridge or ESP8266
 * Security: API Key required
 */

validateApiKey();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Required fields
$device_id = $input['device_id'] ?? DEFAULT_DEVICE_ID;
$temperature = isset($input['temperature']) ? floatval($input['temperature']) : null;
$humidity = isset($input['humidity']) ? floatval($input['humidity']) : null;
$distance = isset($input['distance']) ? intval($input['distance']) : null;
$light_level = isset($input['light_level']) ? intval($input['light_level']) : null;
$smoke_level = isset($input['smoke_level']) ? intval($input['smoke_level']) : null;
$bulb1 = isset($input['bulb1_status']) ? intval($input['bulb1_status']) : 0;
$bulb2 = isset($input['bulb2_status']) ? intval($input['bulb2_status']) : 0;
$buzzer = isset($input['buzzer_status']) ? intval($input['buzzer_status']) : 0;
$emergency = isset($input['emergency_flag']) ? intval($input['emergency_flag']) : 0;

try {
    $db = getDB();

    // Insert sensor reading
    $stmt = $db->prepare("
        INSERT INTO sensor_readings 
        (temperature, humidity, distance, light_level, smoke_level, 
         bulb1_status, bulb2_status, buzzer_status, emergency_flag, device_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $temperature, $humidity, $distance, $light_level, $smoke_level,
        $bulb1, $bulb2, $buzzer, $emergency, $device_id
    ]);

    // Update device status snapshot
    $stmt2 = $db->prepare("
        INSERT INTO device_status 
        (device_id, bulb1_status, bulb2_status, buzzer_status, emergency_flag, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        bulb1_status = VALUES(bulb1_status),
        bulb2_status = VALUES(bulb2_status),
        buzzer_status = VALUES(buzzer_status),
        emergency_flag = VALUES(emergency_flag),
        updated_at = NOW()
    ");
    $stmt2->execute([$device_id, $bulb1, $bulb2, $buzzer, $emergency]);

    // Handle emergency event logging
    if ($emergency == 1) {
        $check = $db->prepare("
            SELECT id FROM emergency_events 
            WHERE device_id = ? AND cleared_at IS NULL 
            ORDER BY triggered_at DESC LIMIT 1
        ");
        $check->execute([$device_id]);
        $active = $check->fetch();

        if (!$active) {
            $stmt3 = $db->prepare("
                INSERT INTO emergency_events (device_id, smoke_value_at_trigger)
                VALUES (?, ?)
            ");
            $stmt3->execute([$device_id, $smoke_level]);

            $stmt4 = $db->prepare("
                INSERT INTO alerts_log (alert_type, severity, message, sensor_value, device_id)
                VALUES ('smoke', 'critical', 'Smoke detected! Emergency shutdown activated.', ?, ?)
            ");
            $stmt4->execute([$smoke_level, $device_id]);

            logEvent('warning', "Emergency triggered for device $device_id - Smoke: $smoke_level", 'arduino');
        }
    } else {
        $check = $db->prepare("
            SELECT id, triggered_at FROM emergency_events 
            WHERE device_id = ? AND cleared_at IS NULL 
            ORDER BY triggered_at DESC LIMIT 1
        ");
        $check->execute([$device_id]);
        $active = $check->fetch();

        if ($active) {
            $triggered = strtotime($active['triggered_at']);
            $now = time();
            $duration = $now - $triggered;

            $stmt3 = $db->prepare("
                UPDATE emergency_events 
                SET cleared_at = NOW(), duration_seconds = ?
                WHERE id = ?
            ");
            $stmt3->execute([$duration, $active['id']]);

            logEvent('info', "Emergency cleared for device $device_id after $duration seconds", 'arduino');
        }
    }

    // Log motion alert if distance is very close
    if ($distance !== null && $distance < 30 && $distance > 0) {
        $stmt5 = $db->prepare("
            INSERT INTO alerts_log (alert_type, severity, message, sensor_value, device_id)
            SELECT 'motion', 'info', 'Motion detected near sensor.', ?, ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM alerts_log 
                WHERE alert_type = 'motion' AND device_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            )
        ");
        $stmt5->execute([$distance, $device_id, $device_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Data inserted successfully',
        'emergency' => $emergency,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    logEvent('error', 'Insert data failed: ' . $e->getMessage(), 'api');
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}