<?php
header("Content-Type: application/json");

// Database Configuration
$db_host = "localhost";
$db_user = "glhorgia_admin";
$db_pass = "GLHOMES_DB_ADMIN06";
$db_name = "glhorgia_wms_home_automation";

// Connect to MySQL
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

// Read JSON from ESP8266
$json_raw = file_get_contents("php://input");
$data = json_decode($json_raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Empty or invalid JSON received",
        "raw_data" => $json_raw
    ]);
    exit();
}

// Extract values
$temperature    = isset($data['temperature']) ? floatval($data['temperature']) : 0;
$humidity       = isset($data['humidity']) ? floatval($data['humidity']) : 0;
$distance       = isset($data['distance']) ? intval($data['distance']) : 0;
$light_level    = isset($data['light_level']) ? intval($data['light_level']) : 0;
$smoke_level    = isset($data['smoke_status']) ? intval($data['smoke_status']) : 0;
$bulb1_status   = isset($data['bulb1_status']) ? intval($data['bulb1_status']) : 0;
$bulb2_status   = isset($data['bulb2_status']) ? intval($data['bulb2_status']) : 0;
$buzzer_status  = isset($data['buzzer_status']) ? intval($data['buzzer_status']) : 0;
$emergency_flag = isset($data['emergency_flag']) ? intval($data['emergency_flag']) : 0;
$device_id      = isset($data['device_id']) ? $conn->real_escape_string($data['device_id']) : "home_unit_01";

// Insert into database
$sql = "INSERT INTO sensor_readings (
            temperature,
            humidity,
            distance,
            light_level,
            smoke_level,
            bulb1_status,
            bulb2_status,
            buzzer_status,
            emergency_flag,
            device_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "SQL prepare failed: " . $conn->error
    ]);
    exit();
}

$stmt->bind_param(
    "ddiiiiiiis",
    $temperature,
    $humidity,
    $distance,
    $light_level,
    $smoke_level,
    $bulb1_status,
    $bulb2_status,
    $buzzer_status,
    $emergency_flag,
    $device_id
);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Data inserted successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Insert failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>