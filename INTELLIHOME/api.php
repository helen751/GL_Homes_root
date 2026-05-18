<?php
/**
 * HOME AUTOMATION IoT - API Endpoint
 * Handles AJAX requests for device control, threshold updates, and data fetching
 */

// Define constant to prevent direct access
define('STONE_SYSTEM', true);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection ---
$conn = new mysqli("localhost", "glhorgia_admin", "GLHOMES_DB_ADMIN06", "glhorgia_wms_home_automation");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// --- Helper functions ---
function sendResponse($success, $message = '', $data = []) {
    global $conn;
    $conn->close();
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function queueCommand($conn, $commandType, $commandValue) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("INSERT INTO device_commands (device_id, command_type, command_value, status) VALUES (?, ?, ?, 'pending')");
    if (!$stmt) return false;
    $stmt->bind_param("sss", $deviceId, $commandType, $commandValue);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function updateDeviceStatus($conn, $field, $value) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("UPDATE device_status SET $field = ? WHERE device_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("is", $value, $deviceId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function logAlert($conn, $alertType, $severity, $message, $sensorValue = null) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("INSERT INTO alerts_log (alert_type, severity, message, sensor_value, device_id) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("sssis", $alertType, $severity, $message, $sensorValue, $deviceId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    
    case 'get_latest':
        $result = $conn->query("SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $reading = $result->fetch_assoc();
            sendResponse(true, "Latest reading retrieved", ['reading' => $reading]);
        } else {
            sendResponse(true, "No readings yet", ['reading' => null]);
        }
        break;
    
    case 'get_stats':
        $todayStats = [];
        $statsResult = $conn->query("SELECT 
            COUNT(*) as total_readings,
            AVG(temperature) as avg_temp,
            MAX(temperature) as max_temp,
            MIN(temperature) as min_temp,
            AVG(humidity) as avg_humidity,
            MAX(smoke_level) as max_smoke,
            SUM(emergency_flag) as emergency_count
        FROM sensor_readings WHERE DATE(created_at) = CURDATE()");
        if ($statsResult && $statsResult->num_rows > 0) {
            $todayStats = $statsResult->fetch_assoc();
        }
        
        $pendingEmergencyCount = 0;
        $pendingCountResult = $conn->query("SELECT COUNT(*) as cnt FROM emergency_events WHERE is_acknowledged = 0");
        if ($pendingCountResult && $pendingCountResult->num_rows > 0) {
            $pendingEmergencyCount = $pendingCountResult->fetch_assoc()['cnt'];
        }
        
        $pendingCommands = 0;
        $cmdResult = $conn->query("SELECT COUNT(*) as cnt FROM device_commands WHERE status = 'pending'");
        if ($cmdResult && $cmdResult->num_rows > 0) {
            $pendingCommands = $cmdResult->fetch_assoc()['cnt'];
        }
        
        sendResponse(true, "Statistics retrieved", [
            'today_stats' => $todayStats,
            'pending_emergencies' => $pendingEmergencyCount,
            'pending_commands' => $pendingCommands
        ]);
        break;
    
    case 'get_history':
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $stmt = $conn->prepare("SELECT * FROM sensor_readings WHERE DATE(created_at) = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $readings = [];
        while ($row = $result->fetch_assoc()) {
            $readings[] = $row;
        }
        $stmt->close();
        sendResponse(true, "History retrieved", ['readings' => $readings]);
        break;
    
    case 'get_alerts':
        $result = $conn->query("SELECT * FROM alerts_log ORDER BY created_at DESC LIMIT 50");
        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
        sendResponse(true, "Alerts retrieved", ['alerts' => $alerts]);
        break;
    
    case 'bulb1':
        $command = isset($_POST['command']) ? $_POST['command'] : 'toggle';
        
        $result = $conn->query("SELECT bulb1_status FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentStatus = $row['bulb1_status'];
            
            if ($command === 'toggle') {
                $newStatus = $currentStatus ? 0 : 1;
            } elseif ($command === 'on') {
                $newStatus = 1;
            } elseif ($command === 'off') {
                $newStatus = 0;
            } else {
                sendResponse(false, 'Invalid command');
            }
            
            if (updateDeviceStatus($conn, 'bulb1_status', $newStatus)) {
                queueCommand($conn, 'bulb1', $newStatus ? 'on' : 'off');
                sendResponse(true, "Bulb 1 turned " . ($newStatus ? "ON" : "OFF"));
            } else {
                sendResponse(false, "Failed to update bulb 1 status");
            }
        } else {
            sendResponse(false, "Device not found");
        }
        break;
    
    case 'bulb2':
        $command = isset($_POST['command']) ? $_POST['command'] : 'toggle';
        
        $result = $conn->query("SELECT bulb2_status FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentStatus = $row['bulb2_status'];
            
            if ($command === 'toggle') {
                $newStatus = $currentStatus ? 0 : 1;
            } elseif ($command === 'on') {
                $newStatus = 1;
            } elseif ($command === 'off') {
                $newStatus = 0;
            } else {
                sendResponse(false, 'Invalid command');
            }
            
            if (updateDeviceStatus($conn, 'bulb2_status', $newStatus)) {
                queueCommand($conn, 'bulb2', $newStatus ? 'on' : 'off');
                sendResponse(true, "Bulb 2 turned " . ($newStatus ? "ON" : "OFF"));
            } else {
                sendResponse(false, "Failed to update bulb 2 status");
            }
        } else {
            sendResponse(false, "Device not found");
        }
        break;
    
    case 'buzzer':
        $command = isset($_POST['command']) ? $_POST['command'] : 'toggle';
        
        $result = $conn->query("SELECT buzzer_status FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentStatus = $row['buzzer_status'];
            
            if ($command === 'toggle') {
                $newStatus = $currentStatus ? 0 : 1;
            } elseif ($command === 'on') {
                $newStatus = 1;
            } elseif ($command === 'off') {
                $newStatus = 0;
            } else {
                sendResponse(false, 'Invalid command');
            }
            
            if (updateDeviceStatus($conn, 'buzzer_status', $newStatus)) {
                queueCommand($conn, 'buzzer', $newStatus ? 'on' : 'off');
                sendResponse(true, "Buzzer turned " . ($newStatus ? "ON" : "OFF"));
            } else {
                sendResponse(false, "Failed to update buzzer status");
            }
        } else {
            sendResponse(false, "Device not found");
        }
        break;
    
    case 'reset_emergency':
        if (updateDeviceStatus($conn, 'emergency_flag', 0)) {
            queueCommand($conn, 'reset_emergency', 'reset');
            
            $stmt = $conn->prepare("UPDATE emergency_events SET cleared_at = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, triggered_at, NOW()) WHERE is_acknowledged = 0 AND device_id = 'home_unit_01'");
            if ($stmt) $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE emergency_events SET is_acknowledged = 1, acknowledged_at = NOW() WHERE is_acknowledged = 0");
            if ($stmt) $stmt->execute();
            
            logAlert($conn, 'system_offline', 'info', 'Emergency flag reset by user', null);
            sendResponse(true, "Emergency flag reset successfully");
        } else {
            sendResponse(false, "Failed to reset emergency flag");
        }
        break;
    
    case 'thresholds':
        $smokeThreshold = isset($_POST['smoke']) ? intval($_POST['smoke']) : null;
        $lightThreshold = isset($_POST['light']) ? intval($_POST['light']) : null;
        $motionDistance = isset($_POST['motion']) ? intval($_POST['motion']) : null;
        $autoLight = isset($_POST['auto_light']) ? intval($_POST['auto_light']) : null;
        $autoMotion = isset($_POST['auto_motion']) ? intval($_POST['auto_motion']) : null;
        
        $updates = [];
        $params = [];
        $types = "";
        
        if ($smokeThreshold !== null) {
            $updates[] = "smoke_threshold = ?";
            $params[] = $smokeThreshold;
            $types .= "i";
        }
        if ($lightThreshold !== null) {
            $updates[] = "light_threshold = ?";
            $params[] = $lightThreshold;
            $types .= "i";
        }
        if ($motionDistance !== null) {
            $updates[] = "motion_distance = ?";
            $params[] = $motionDistance;
            $types .= "i";
        }
        if ($autoLight !== null) {
            $updates[] = "auto_light_enabled = ?";
            $params[] = $autoLight;
            $types .= "i";
        }
        if ($autoMotion !== null) {
            $updates[] = "auto_motion_enabled = ?";
            $params[] = $autoMotion;
            $types .= "i";
        }
        
        if (empty($updates)) {
            sendResponse(false, "No settings to update");
        }
        
        $params[] = 'home_unit_01';
        $types .= "s";
        
        $sql = "UPDATE device_status SET " . implode(", ", $updates) . " WHERE device_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                if ($smokeThreshold !== null) queueCommand($conn, 'update_threshold', 'smoke=' . $smokeThreshold);
                if ($lightThreshold !== null) queueCommand($conn, 'update_threshold', 'light=' . $lightThreshold);
                if ($motionDistance !== null) queueCommand($conn, 'update_threshold', 'motion=' . $motionDistance);
                sendResponse(true, "Threshold settings saved successfully");
            } else {
                sendResponse(false, "Failed to save settings: " . $stmt->error);
            }
            $stmt->close();
        } else {
            sendResponse(false, "Database error: " . $conn->error);
        }
        break;
    
    case 'get_commands':
        $result = $conn->query("SELECT * FROM device_commands WHERE status = 'pending' AND device_id = 'home_unit_01' ORDER BY created_at ASC");
        $commands = [];
        while ($row = $result->fetch_assoc()) {
            $commands[] = $row;
        }
        sendResponse(true, "Commands retrieved", ['commands' => $commands]);
        break;
    
    case 'command_executed':
        $commandId = isset($_POST['command_id']) ? intval($_POST['command_id']) : 0;
        if ($commandId > 0) {
            $stmt = $conn->prepare("UPDATE device_commands SET status = 'executed', executed_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $commandId);
                $stmt->execute();
                $stmt->close();
                sendResponse(true, "Command marked as executed");
            } else {
                sendResponse(false, "Database error");
            }
        } else {
            sendResponse(false, "Invalid command ID");
        }
        break;
    
    default:
        sendResponse(true, "API is running. Available actions: get_latest, get_stats, get_history, get_alerts, bulb1, bulb2, buzzer, reset_emergency, thresholds, get_commands, command_executed");
        break;
}

$conn->close();
?>