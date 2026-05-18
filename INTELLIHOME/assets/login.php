<?php
session_start();
$conn = new mysqli("sql202.infinityfree.com", "if0_41947034", "F3gUneCLbe928", "if0_41947034_home_automation");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Look for the user in the database
    $sql = "SELECT id, password_hash FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify if the submitted password matches the hashed password in DB
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            
            // Redirect to your dashboard page once logged in
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Home Automation</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 50px; text-align: center; }
        .box { background: white; padding: 20px; display: inline-block; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; text-align: left; }
        input { display: block; margin: 10px 0; padding: 8px; width: 250px; }
        button { background: #28a745; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; }
    </style>
</head>
<body>

<div class="box">
    <h2>System Login</h2>
    <?php if($error) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST" action="login.php">
        <label>Username:</label>
        <input type="text" name="username" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>
    <p><a href="register.php">Don't have an account? Register</a></p>
    <p style="font-size:12px; color:#777;">Default access: admin / admin123</p>
</div>

</body>
</html>