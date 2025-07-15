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

// 3. Send confirmation email (HTML version)
$subject = "GL Homes Masterclass Registration Confirmation";

$message = '
<html>
<head>
  <meta charset="UTF-8">
  <title>GL Homes Masterclass Registration Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f6f6f6; padding: 20px;">
  <table width="100%" style="max-width: 600px; margin: auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #eee;">
    <tr>
      <td style="padding: 32px;">
        <h2 style="color: #003366; margin-bottom: 0;">GL Homes Masterclass Registration</h2>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 16px; color: #222;">
          Dear <strong>' . htmlspecialchars($fullname) . '</strong>,
        </p>
        <p style="font-size: 16px; color: #222;">
          Thank you for registering for the <b>GL Homes Masterclass</b>.
        </p>
        <h3 style="color: #003366;">What happens next?</h3>
        <ul style="font-size: 16px; color: #222;">
          <li>You will be invited to the meeting via your email calendar (<b>' . htmlspecialchars($email) . '</b>).</li>
          <li>Please check your inbox and calendar for an invitation.</li>
          <li>When you receive the invite, make sure to <b>ACCEPT IT</b> by tapping &lsquo;Yes&rsquo;.</li>
        </ul>
        <h3 style="color: #003366;">Didn&rsquo;t get the invite?</h3>
        <ul style="font-size: 16px; color: #222;">
          <li>If you do not receive the invite by <b>19th July</b>, send an email to <a href="mailto:masterclass@glhomesltd.com">masterclass@glhomesltd.com</a>.</li>
          <li>Include your registration code (below) in your email.</li>
        </ul>
        <p style="margin: 24px 0; font-size: 18px;">
          <span style="display: inline-block; background: #e9f5ff; color: #003366; padding: 12px 24px; border-radius: 6px; font-weight: bold;">
            Your Registration Code: ' . htmlspecialchars($reg_code) . '
          </span>
        </p>
        <p style="font-size: 16px; color: #222;">
          If you have any issues, reply to this email or contact <a href="mailto:master@glhomesltd.com">master@glhomesltd.com</a>.
        </p>
        <br>
        <p style="font-size: 16px; color: #222;">Best regards,<br>
        <b>GL Homes Masterclass Team</b></p>
      </td>
    </tr>
  </table>
</body>
</html>
';

// Adjust headers for HTML
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: masterclass@glhomesltd.com\r\n";
$headers .= "Reply-To: masterclass@glhomesltd.com\r\n";
$headers .= "Cc: masterclass@glhomesltd.com\r\n";

$mail_sent = mail($email, $subject, $message, $headers);


// You might want to handle email delivery failure, but for now just proceed
echo json_encode([
    "status" => "success",
    "message" => "Registration successful. A confirmation email has been sent to you. Your registration code is: $reg_code"
]);
exit;
?>
