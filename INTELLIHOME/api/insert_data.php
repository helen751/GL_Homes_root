<?php
// Set response header to JSON format
header("Content-Type: application/json");

// Database Configuration - Matches your database.sql schema
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "home_automation"; // From your schema: CREATE DATABASE IF NOT EXISTS home_automation

// 1. Connect to MySQL Database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection stability
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// 2. Security Key Protection - Must match your Python bridge configuration
$expected_api_key = "HA_IOT_SECRET_KEY_2026_CHANGE_THIS";

// Grab headers or background raw JSON string payload sent by requests.post()
$headers = getallheaders();
$api_key_from_header = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';

$json_raw = file_get_contents('php://input');
$data = json_decode($json_raw, true);

// Verify API keys from header or data object injection payload
$received_api_key = !empty($api_key_from_header) ? $api_key_from_header : (isset($data['api_key']) ? $data['api_key'] : '');

if ($received_api_key !== $expected_api_key) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access: API Key invalid."]);
    exit();
}

// 3. Extract data attributes coming from your NodeMCU's sendJsonData() payload loop
if ($data) {
    // Exact mapping matches the layout strings generated in home_automation.ino:
    // json += "\"temperature\":" + String(sensors.temperature, 1) + ",";
    // json += "\"humidity\":" + String(sensors.humidity, 1) + ","; etc.
    $temperature    = isset($data['temperature'])    ? floatval($data['temperature']) : null;
    $humidity       = isset($data['humidity'])       ? floatval($data['humidity'])    : null;
    $distance       = isset($data['distance'])       ? intval($data['distance'])      : null;
    $light_level    = isset($data['light_level'])    ? intval($data['light_level'])   : null;
    $smoke_level    = isset($data['smoke_status'])   ? intval($data['smoke_status'])  : null; // parsed from Arduino smoke_status
    $bulb1_status   = isset($data['bulb1_status'])   ? intval($data['bulb1_status'])  : 0;
    $bulb2_status   = isset($data['bulb2_status'])   ? intval($data['bulb2_status'])  : 0;
    $buzzer_status  = isset($data['buzzer_status'])  ? intval($data['buzzer_status']) : 0;
    $emergency_flag = isset($data['emergency_flag']) ? intval($data['emergency_flag']) : 0;
    $device_id      = isset($data['device_id'])      ? $conn->real_escape_string($data['device_id']) : 'home_unit_01';

    // 4. Prepare SQL insert statement into sensor_readings table
    $sql = "INSERT INTO sensor_readings (
                temperature, humidity, distance, light_level, smoke_level, 
                bulb1_status, bulb2_status, buzzer_status, emergency_flag, device_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // "ddiiiiiiis" stands for data types: double, double, integer, integer... string
        $stmt->bind_param(
            "ddiiiiiiis", 
            $temperature, $humidity, $distance, $light_level, $smoke_level, 
            $bulb1_status, $bulb2_status, $buzzer_status, $emergency_flag, $device_id
        );

        if ($stmt->execute()) {
            // Successfully logged payload metrics to MySQL database storage
            http_response_code(200);
            echo json_encode([
                "status" => "success", 
                "message" => "Metrics saved successfully into database table sensor_readings!"
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Execution failed: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "SQL Preparation Failed: " . $conn->error]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Empty or invalid JSON payload data packages received."]);
}

$conn->close();
?>