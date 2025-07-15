<?php
// Enable CORS & return JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Database configuration
$host = "localhost";
$db   = "glhorgia_users";
$user = "glhorgia_admin";
$pass = "GLHOMES_DB_ADMIN06";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Get and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Sanitize and assign
$fullname = $conn->real_escape_string($data["fullname"]);
$email    = $conn->real_escape_string($data["email"]);
$phone    = $conn->real_escape_string($data["phone"]);
$gender   = $conn->real_escape_string($data["gender"]);
$crole    = $conn->real_escape_string($data["crole"]);

$reg_code = "GLHOMES_EMP" . time() . "_" . rand(100, 999);

// 1. Check if user already registered
$sql_check = "SELECT id FROM employees_masterclass_registrations_01 WHERE email = '$email' LIMIT 1";
$result = $conn->query($sql_check);

if ($result && $result->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "This email has already been registered for the masterclass."
    ]);
    exit;
}

// 2. Insert new registration
$sql = "INSERT INTO employees_masterclass_registrations_01 
(fullname, email, phone_number, gender, company_role, registration_code)
VALUES 
('$fullname', '$email', '$phone', '$gender', '$crole', '$reg_code')";

if (!$conn->query($sql)) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to insert data into database"
    ]);
    exit;
}

$insert_id = $conn->insert_id;

// 3. Send confirmation email
$subject = "GL Homes Masterclass Registration Confirmation";
$message = "
Dear $fullname,

Thank you for registering for the GL Homes Masterclass.

**What happens next?**
- You will be invited to the meeting via your email calendar ($email).
- Please check your inbox and calendar for an invitation.
- When you receive the invite, make sure to ACCEPT IT by tapping 'Yes'.

**Didn’t get the invite?**
- If you do not receive the invite by 19th July, send an email to tech@glhomesltd.com.
- Make sure to include your registration code below in your email.

Your Registration Code: $reg_code

If you have any issues, reply to this email or contact tech@glhomesltd.com.

Best regards,  
GL Homes Team
";

// Adjust headers as necessary (best practice: use a real, monitored email for 'From')
$headers = "From: masterclass@glhomesltd.com\r\n";
$headers .= "Reply-To: tech@glhomesltd.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$mail_sent = mail($email, $subject, $message, $headers);

// You might want to handle email delivery failure, but for now just proceed
echo json_encode([
    "status" => "success",
    "message" => "Registration successful. A confirmation email has been sent to you. Your registration code is: $reg_code"
]);
exit;
?>
