<?php
// Enable CORS & return JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Database configuration
$host = "localhost";
$db = "glhorgia_users";        // Change to your DB name
$user = "glhorgia_admin";          // Change to your DB user
$pass = "GLHOMES_DB_ADMIN06";      // Change to your DB password

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Get and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Sanitize and assign
$fullname     = $conn->real_escape_string($data["fullname"]);
$email        = $conn->real_escape_string($data["email"]);
$phone        = $conn->real_escape_string($data["phone"]);
$phone_full   = $conn->real_escape_string($data["phone_full"]);
$country      = $conn->real_escape_string($data["country"]);
$state        = $conn->real_escape_string($data["state"]);
$city         = $conn->real_escape_string($data["city"]);
$gender       = $conn->real_escape_string($data["gender"]);
$currency       = trim($data["currency"]);
$amount       = 0; // Default amount

if ($currency == "USD") {
    $amount = 25; // USD amount
} elseif ($currency == "NGN") {
    $amount = 5000; // NGN amount
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported currency $currency"]);
    exit;
}

// Insert into database (initial payment_status = 0)
$sql = "INSERT INTO masterclass_registrations_01 
(fullname, email, phone, phone_full, country, state, city, gender, payment_amount, currency, payment_status)
VALUES 
('$fullname', '$email', '$phone', '$phone_full', '$country', '$state', '$city', '$gender', $amount, '$currency', 0)";

if (!$conn->query($sql)) {
    echo json_encode(["status" => "error", "message" => "Failed to insert data into database"]);
    exit;
}


$insert_id = $conn->insert_id; // Get inserted ID for redirect

// Prepare for Flutterwave payment
$tx_ref = "GLHOMES_" . time() . "_" . rand(1000, 9999);
$redirect_url = "https://glhomesltd.com?id=$insert_id"; // Update to your real redirect

$paymentData = [
    "tx_ref" => $tx_ref,
    "amount" => $amount,
    "currency" => $currency,
    "redirect_url" => $redirect_url,
    "payment_options" => "card,banktransfer",
    "customer" => [
        "email" => $email,
        "phonenumber" => $phone_full,
        "name" => $fullname
    ],
    "customizations" => [
        "title" => "GL Homes Business Masterclass",
        "description" => "Payment for masterclass registration"
    ]
];

// Flutterwave cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.flutterwave.com/v3/payments",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer FLWSECK-ed3fa365dbdbbe4554832ea097659f65-197ba0c5ed0vt-X", // Replace with your key
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($paymentData)
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(["status" => "error", "message" => "Curl error: $err"]);
    exit;
}

$result = json_decode($response, true);
if (isset($result["data"]["link"])) {
    echo json_encode([
        "status" => "success",
        "payment_link" => $result["data"]["link"]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Payment link not generated"]);
}
?>