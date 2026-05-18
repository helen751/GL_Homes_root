<?php
require_once '../config.php';

/**
 * GET PENDING COMMANDS
 * Python Bridge polls this to fetch commands for Arduino
 */

validateApiKey();

$device_id = $_GET['device_id'] ?? DEFAULT_DEVICE_ID;

try {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, command_type, command_value, created_at 
        FROM device_commands 
        WHERE device_id = ? AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->execute([$device_id]);
    $commands = $stmt->fetchAll();

    // Mark as executed
    if (count($commands) > 0) {
        $ids = array_column($commands, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $db->prepare("
            UPDATE device_commands 
            SET status = 'executed', executed_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $stmt2->execute($ids);
    }

    echo json_encode([
        'success' => true,
        'commands' => $commands,
        'count' => count($commands)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}