<?php
require_once '../config.php';

/**
 * CONTROL DEVICE
 * Receives manual commands from Dashboard
 * Blocks commands during emergency (except acknowledge)
 */

startSession();
requireAuth();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$device_id = $input['device_id'] ?? DEFAULT_DEVICE_ID;
$command_type = $input['command_type'] ?? '';
$command_value = $input['command_value'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $db = getDB();

    // Check if emergency is active
    $stmt = $db->prepare("
        SELECT emergency_flag FROM device_status WHERE device_id = ?
    ");
    $stmt->execute([$device_id]);
    $status = $stmt->fetch();

    $isEmergency = ($status && $status['emergency_flag'] == 1);

    // Block non-acknowledge commands during emergency
    if ($isEmergency && $command_type !== 'acknowledge' && $command_type !== 'reset_emergency') {
        echo json_encode([
            'success' => false,
            'error' => 'Emergency mode active. Only acknowledge command allowed.',
            'emergency' => true
        ]);
        exit();
    }

    // Insert command queue
    $stmt2 = $db->prepare("
        INSERT INTO device_commands (device_id, command_type, command_value)
        VALUES (?, ?, ?)
    ");
    $stmt2->execute([$device_id, $command_type, $command_value]);
    $command_id = $db->lastInsertId();

    // Handle acknowledge
    if ($command_type === 'acknowledge') {
        $stmt3 = $db->prepare("
            UPDATE emergency_events 
            SET is_acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = ?
            WHERE device_id = ? AND cleared_at IS NULL
        ");
        $stmt3->execute([$user_id, $device_id]);

        // Log
        $stmt4 = $db->prepare("
            INSERT INTO alerts_log (alert_type, severity, message, device_id)
            VALUES ('smoke', 'warning', 'Alarm acknowledged by user.', ?)
        ");
        $stmt4->execute([$device_id]);

        logEvent('info', "Emergency acknowledged by user $user_id", 'dashboard');
    }

    // Update device status immediately for responsive UI
    if (in_array($command_type, ['bulb1', 'bulb2', 'buzzer'])) {
        $field = match($command_type) {
            'bulb1' => 'bulb1_status',
            'bulb2' => 'bulb2_status',
            'buzzer' => 'buzzer_status',
            default => null
        };
        if ($field) {
            $val = ($command_value === 'ON' || $command_value == '1') ? 1 : 0;
            $stmt5 = $db->prepare("UPDATE device_status SET $field = ? WHERE device_id = ?");
            $stmt5->execute([$val, $device_id]);
        }
    }

    echo json_encode([
        'success' => true,
        'command_id' => $command_id,
        'command_type' => $command_type,
        'command_value' => $command_value,
        'emergency' => $isEmergency,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}