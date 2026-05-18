<?php
require_once '../config.php';

/**
 * ACKNOWLEDGE ALERT
 * Standalone endpoint for emergency acknowledge
 */

startSession();
requireAuth();

$device_id = $_POST['device_id'] ?? DEFAULT_DEVICE_ID;
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $db = getDB();

    $stmt = $db->prepare("
        UPDATE emergency_events 
        SET is_acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = ?
        WHERE device_id = ? AND cleared_at IS NULL
    ");
    $stmt->execute([$user_id, $device_id]);

    $stmt2 = $db->prepare("
        INSERT INTO alerts_log (alert_type, severity, message, device_id)
        VALUES ('smoke', 'warning', 'Emergency alarm acknowledged by user.', ?)
    ");
    $stmt2->execute([$device_id]);

    logEvent('info', "Emergency acknowledged by user $user_id", 'dashboard');

    echo json_encode(['success' => true, 'message' => 'Alarm acknowledged']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}