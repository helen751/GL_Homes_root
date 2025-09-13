<?php
// Set up MySQL database credentials
$servername = "localhost";
$username = "glhorgia_admin";
$password = "GLHOMES_DB_ADMIN06";
$dbname = "glhorgia_users";

// Create connection to MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure that the request is a POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get the raw POST data (JSON)
    $input_data = file_get_contents("php://input");

    // Decode JSON data
    $data = json_decode($input_data, true);

    // Check if the data is valid
    if ($data) {

        // Retrieve the values from the JSON
        $device_id = $data['device_id'] ?? null;
        $timestamp_ms = $data['timestamp_ms'] ?? null;
        $temperature_c = $data['temperature_c'] ?? null;
        $humidity_pct = $data['humidity_pct'] ?? null;
        $soil_moisture_pct = $data['soil_moisture_pct'] ?? null;
        $soil_adc_raw = $data['soil_adc_raw'] ?? null;
        $water_level_ok = $data['water_level_ok'] ?? null;
        $needs_water = $data['needs_water'] ?? null;
        $nitrogen_ppm = $data['nitrogen_ppm'] ?? null;
        $crop_nutrient_index = $data['crop_nutrient_index'] ?? null;

        // SQL query to insert data into the database
        $sql = "INSERT INTO crop_data (device_id, timestamp_ms, temperature_c, humidity_pct, 
                soil_moisture_pct, soil_adc_raw, water_level_ok, needs_water, nitrogen_ppm, crop_nutrient_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Prepare and bind the statement
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sdddiiiidd", $device_id, $timestamp_ms, $temperature_c, $humidity_pct, 
                             $soil_moisture_pct, $soil_adc_raw, $water_level_ok, $needs_water, $nitrogen_ppm, $crop_nutrient_index);

            // Execute the query
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Data successfully inserted"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to insert data"]);
            }

            // Close the prepared statement
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to prepare statement"]);
        }

    } else {
        // Invalid JSON
        echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
    }
} else {
    // Request method is not POST
    echo json_encode(["status" => "error", "message" => "Only POST requests are allowed"]);
}

// Close the database connection
$conn->close();
?>
