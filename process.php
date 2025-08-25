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
$category       = $conn->real_escape_string($data["category"]);
$currency       = trim($data["currency"]);
$amount       = 0; // Default amount

if ($currency == "USD") {
    $amount = 10; // USD amount
} elseif ($currency == "NGN") {
    $amount = 5000; // NGN amount
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported currency $currency"]);
    exit;
}

// Insert into database (initial payment_status = 0)
$sql = "INSERT INTO masterclass_registrations_01 
(fullname, email, phone_full, country, state, city, gender, payment_amount, currency, category_group, payment_status)
VALUES 
('$fullname', '$email', '$phone_full', '$country', '$state', '$city', '$gender', $amount, '$currency','$category', 0)";

if (!$conn->query($sql)) {
    echo json_encode(["status" => "error", "message" => "Failed to insert data into database"]);
    exit;
}

$insert_id = $conn->insert_id; // Get inserted ID for redirect

// Prepare for Paystack payment
$reference = "GLHOMES_" . time() . "_" . rand(1000, 9999);
$callback_url = "https://glhomesltd.com?id=$insert_id"; // Redirect after payment

$paymentData = [
    "email" => $email,
    "amount" => $amount * 100, // Paystack uses kobo (multiply Naira by 100)
    "currency" => $currency,
    "reference" => $reference,
    "callback_url" => $callback_url,
    "metadata" => [
        "custom_fields" => [
            [
                "display_name" => "Customer Name",
                "variable_name" => "name",
                "value" => $fullname
            ],
            [
                "display_name" => "Phone Number",
                "variable_name" => "phone",
                "value" => $phone_full
            ]
        ]
    ]
];

// Paystack cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer sk_live_9c7dd14bedec1d3c18abc60e6bcdb5a269f8ca24", // Replace with your Paystack secret key
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
if (isset($result["data"]["authorization_url"])) {
    echo json_encode([
        "status" => "success",
        "payment_link" => $result["data"]["authorization_url"]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => isset($result["message"]) ? $result["message"] : "Payment link not generated"
    ]);
}
?>