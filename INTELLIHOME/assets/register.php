<?php
$conn = new mysqli("sql202.infinityfree.com", "if0_41947034", "F3gUneCLbe928", "if0_41947034_home_automation");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];

    // Securely hash the password just like the database schema expects
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Simple insert query matching your database columns
    $sql = "INSERT INTO users (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $password_hash, $full_name, $email);

    if ($stmt->execute()) {
        $message = "<p style='color: green;'>Registration successful! <a href='login.php'>Login here</a></p>";
    } else {
        $message = "<p style='color: red;'>Error: Username might already exist.</p>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Home Automation</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 50px; text-align: center; }
        .box { background: white; padding: 20px; display: inline-block; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; text-align: left; }
        input { display: block; margin: 10px 0; padding: 8px; width: 250px; }
        button { background: #007BFF; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; }
    </style>
</head>
<body>

<div class="box">
    <h2>Create Account</h2>
    <?php echo $message; ?>
    <form method="POST" action="register.php">
        <label>Username:</label>
        <input type="text" name="username" required>
        
        <label>Full Name:</label>
        <input type="text" name="full_name" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <button type="submit">Register</button>
    </form>
    <p><a href="login.php">Already have an account? Login</a></p>
</div>

</body>
</html>