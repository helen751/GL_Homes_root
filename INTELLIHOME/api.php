<?php
/**
 * HOME AUTOMATION IoT - API Endpoint
 * Handles AJAX requests for device control, threshold updates, and command queueing.
 */

// Define constant to prevent direct access
define('STONE_SYSTEM', true);

// Start session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Connection ---
$conn = new mysqli("localhost", "root", "", "home_automation");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// --- Helper: Check if user is logged in (simplified) ---
function isAuthenticated() {
    return isset($_SESSION['user_id']) || true; // Allow for demo
}

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// --- Helper: Send response and close connection ---
function sendResponse($success, $message = '', $data = []) {
    global $conn;
    $conn->close();
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// --- Helper: Queue a command for the Arduino ---
function queueCommand($conn, $commandType, $commandValue) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("INSERT INTO device_commands (device_id, command_type, command_value, status) VALUES (?, ?, ?, 'pending')");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("sss", $deviceId, $commandType, $commandValue);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// --- Helper: Update device status directly (for immediate sync) ---
function updateDeviceStatus($conn, $field, $value) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("UPDATE device_status SET $field = ? WHERE device_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("is", $value, $deviceId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// --- Helper: Log alert ---
function logAlert($conn, $alertType, $severity, $message, $sensorValue = null) {
    $deviceId = 'home_unit_01';
    $stmt = $conn->prepare("INSERT INTO alerts_log (alert_type, severity, message, sensor_value, device_id) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("sssis", $alertType, $severity, $message, $sensorValue, $deviceId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// --- Get the request action ---
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// --- Route the request ---
switch ($action) {
    
    // -------------------- DEVICE CONTROL --------------------
    case 'bulb1':
        $command = isset($_POST['command']) ? $_POST['command'] : 'toggle';
        
        // Get current status
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
            
            // Update database
            if (updateDeviceStatus($conn, 'bulb1_status', $newStatus)) {
                // Queue command for Arduino
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
                
                // Log alert if buzzer is turned on manually
                if ($newStatus == 1) {
                    logAlert($conn, 'system_offline', 'warning', 'Buzzer activated manually by user', null);
                }
                
                sendResponse(true, "Buzzer turned " . ($newStatus ? "ON" : "OFF"));
            } else {
                sendResponse(false, "Failed to update buzzer status");
            }
        } else {
            sendResponse(false, "Device not found");
        }
        break;
        
    case 'reset_emergency':
        // Reset emergency flag
        if (updateDeviceStatus($conn, 'emergency_flag', 0)) {
            queueCommand($conn, 'reset_emergency', 'reset');
            
            // Update emergency_events table
            $stmt = $conn->prepare("UPDATE emergency_events SET cleared_at = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, triggered_at, NOW()) WHERE is_acknowledged = 0 AND device_id = 'home_unit_01'");
            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }
            
            // Acknowledge all pending emergencies
            $stmt = $conn->prepare("UPDATE emergency_events SET is_acknowledged = 1, acknowledged_at = NOW() WHERE is_acknowledged = 0");
            if ($stmt) {
                $stmt->execute();
                $stmt->close();
            }
            
            // Log the reset
            logAlert($conn, 'system_offline', 'info', 'Emergency flag reset by user', null);
            
            sendResponse(true, "Emergency flag reset successfully");
        } else {
            sendResponse(false, "Failed to reset emergency flag");
        }
        break;
        
    // -------------------- THRESHOLD SETTINGS --------------------
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
        
        $deviceId = 'home_unit_01';
        $params[] = $deviceId;
        $types .= "s";
        
        $sql = "UPDATE device_status SET " . implode(", ", $updates) . " WHERE device_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                // Queue threshold update command for Arduino
                if ($smokeThreshold !== null) {
                    queueCommand($conn, 'update_threshold', 'smoke=' . $smokeThreshold);
                }
                if ($lightThreshold !== null) {
                    queueCommand($conn, 'update_threshold', 'light=' . $lightThreshold);
                }
                if ($motionDistance !== null) {
                    queueCommand($conn, 'update_threshold', 'motion=' . $motionDistance);
                }
                
                sendResponse(true, "Threshold settings saved successfully");
            } else {
                sendResponse(false, "Failed to save settings: " . $stmt->error);
            }
            $stmt->close();
        } else {
            sendResponse(false, "Database error: " . $conn->error);
        }
        break;
        
    // -------------------- GET DEVICE STATUS --------------------
    case 'get_status':
        $result = $conn->query("SELECT * FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $status = $result->fetch_assoc();
            sendResponse(true, "Status retrieved", ['status' => $status]);
        } else {
            sendResponse(false, "Device not found");
        }
        break;
        
    // -------------------- GET LATEST READING --------------------
    case 'get_latest':
        $result = $conn->query("SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $reading = $result->fetch_assoc();
            sendResponse(true, "Latest reading retrieved", ['reading' => $reading]);
        } else {
            sendResponse(true, "No readings yet", ['reading' => null]);
        }
        break;
        
    // -------------------- GET PENDING COMMANDS --------------------
    case 'get_commands':
        $result = $conn->query("SELECT * FROM device_commands WHERE status = 'pending' AND device_id = 'home_unit_01' ORDER BY created_at ASC");
        $commands = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $commands[] = $row;
            }
        }
        sendResponse(true, "Commands retrieved", ['commands' => $commands]);
        break;
        
    // -------------------- MARK COMMAND AS EXECUTED (for Arduino) --------------------
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
        
    // -------------------- ACKNOWLEDGE ALERT --------------------
    case 'acknowledge_alert':
        $alertId = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : 0;
        if ($alertId > 0) {
            $stmt = $conn->prepare("UPDATE alerts_log SET is_acknowledged = 1, acknowledged_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $alertId);
                $stmt->execute();
                $stmt->close();
                sendResponse(true, "Alert acknowledged");
            } else {
                sendResponse(false, "Database error");
            }
        } else {
            sendResponse(false, "Invalid alert ID");
        }
        break;
        
    // -------------------- GET SYSTEM STATISTICS --------------------
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
        
    // -------------------- SIMULATE SENSOR DATA (for testing) --------------------
    case 'simulate_sensor':
        $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : rand(180, 300) / 10;
        $humidity = isset($_POST['humidity']) ? floatval($_POST['humidity']) : rand(300, 800) / 10;
        $smokeLevel = isset($_POST['smoke_level']) ? intval($_POST['smoke_level']) : rand(0, 600);
        $lightLevel = isset($_POST['light_level']) ? intval($_POST['light_level']) : rand(50, 800);
        $distance = isset($_POST['distance']) ? intval($_POST['distance']) : rand(10, 150);
        
        // Get current device status for auto rules
        $deviceResult = $conn->query("SELECT * FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
        $device = $deviceResult ? $deviceResult->fetch_assoc() : null;
        
        $emergencyFlag = 0;
        $buzzerStatus = $device ? $device['buzzer_status'] : 0;
        $bulb1Status = $device ? $device['bulb1_status'] : 0;
        $bulb2Status = $device ? $device['bulb2_status'] : 0;
        
        // Check smoke threshold for emergency
        $smokeThreshold = $device ? ($device['smoke_threshold'] ?? 400) : 400;
        if ($smokeLevel >= $smokeThreshold) {
            $emergencyFlag = 1;
            $buzzerStatus = 1; // Auto-activate buzzer on smoke
            
            // Log emergency event if not already active
            $checkEmergency = $conn->query("SELECT id FROM emergency_events WHERE is_acknowledged = 0 AND device_id = 'home_unit_01'");
            if (!$checkEmergency || $checkEmergency->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO emergency_events (device_id, smoke_value_at_trigger) VALUES ('home_unit_01', ?)");
                if ($stmt) {
                    $stmt->bind_param("i", $smokeLevel);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Log critical alert
                logAlert($conn, 'smoke', 'critical', "Smoke level exceeded threshold! Current: {$smokeLevel} ppm (Threshold: {$smokeThreshold} ppm)", $smokeLevel);
            }
        }
        
        // Auto light control based on light level
        $lightThreshold = $device ? ($device['light_threshold'] ?? 300) : 300;
        $autoLightEnabled = $device ? ($device['auto_light_enabled'] ?? 1) : 1;
        if ($autoLightEnabled && $emergencyFlag == 0) {
            if ($lightLevel < $lightThreshold) {
                $bulb1Status = 1;
                $bulb2Status = 1;
            } else {
                // Don't auto-turn off if manually turned on? For simplicity, we auto-off
                if ($bulb1Status == 1 && $device && $device['bulb1_status'] == 0) {
                    // If it was manually off, don't override
                } else {
                    $bulb1Status = 0;
                    $bulb2Status = 0;
                }
            }
        }
        
        // Insert sensor reading
        $stmt = $conn->prepare("INSERT INTO sensor_readings (temperature, humidity, distance, light_level, smoke_level, bulb1_status, bulb2_status, buzzer_status, emergency_flag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ddiiiiiii", $temperature, $humidity, $distance, $lightLevel, $smokeLevel, $bulb1Status, $bulb2Status, $buzzerStatus, $emergencyFlag);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update device status
        if ($device) {
            updateDeviceStatus($conn, 'bulb1_status', $bulb1Status);
            updateDeviceStatus($conn, 'bulb2_status', $bulb2Status);
            updateDeviceStatus($conn, 'buzzer_status', $buzzerStatus);
            updateDeviceStatus($conn, 'emergency_flag', $emergencyFlag);
        }
        
        sendResponse(true, "Sensor data simulated", [
            'temperature' => $temperature,
            'humidity' => $humidity,
            'smoke_level' => $smokeLevel,
            'light_level' => $lightLevel,
            'emergency_flag' => $emergencyFlag
        ]);
        break;
        
        
    // -------------------- DEFAULT / HEALTH CHECK --------------------
    default:
        sendResponse(true, "API is running. Available actions: bulb1, bulb2, buzzer, reset_emergency, thresholds, get_status, get_latest, get_commands, command_executed, acknowledge_alert, get_stats, simulate_sensor");
        break;
}

$conn->close();
?>