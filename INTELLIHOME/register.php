<?php
$conn = new mysqli("localhost", "root", "", "home_automation");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $username, $password_hash, $full_name, $email);

    if ($stmt->execute()) {
        $message = "success";
    } else {
        $message = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — IntelliHome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --dark: #0f172a;
            --text: #334155;
            --text-light: #64748b;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 20px 60px rgba(15, 23, 42, 0.12);
            --radius: 1.5rem;
        }
        * { font-family: 'Inter', sans-serif; }
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 50%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .auth-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), #06b6d4);
        }
        .auth-brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-brand-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 1.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 12px 30px rgba(30, 64, 175, 0.25);
        }
        .auth-brand h3 {
            font-weight: 800;
            color: var(--dark);
            margin: 0;
            font-size: 1.5rem;
        }
        .auth-brand p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin: 0.25rem 0 0;
        }
        .form-label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            padding: 0.875rem 1.25rem;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: all 0.25s ease;
            background: #fafbfc;
        }
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: white;
            outline: none;
        }
        .input-group-text {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-left: none;
            border-radius: 0 1rem 1rem 0;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.875rem 1rem;
        }
        .form-control.is-password {
            border-radius: 1rem 0 0 1rem;
            border-right: none;
        }
        .theme-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.75rem;
            font-weight: 600;
            font-size: 0.9375rem;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            line-height: 1;
            width: 100%;
        }
        .theme-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .theme-btn:active { transform: translateY(0); }
        .theme-btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.25);
            padding: 1rem 2rem;
            font-size: 1rem;
        }
        .theme-btn-primary:hover { background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); color: white; }
        .theme-btn-success {
            background: linear-gradient(135deg, #059669 0%, var(--success) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
            padding: 1rem 2rem;
            font-size: 1rem;
        }
        .theme-btn-success:hover { color: white; }
        .alert-template {
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
        }
        .alert-danger-tmpl {
            background: #fef2f2;
            border-left: 4px solid var(--danger);
            color: #991b1b;
        }
        .alert-success-tmpl {
            background: #f0fdf4;
            border-left: 4px solid var(--success);
            color: #065f46;
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .auth-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .auth-footer a:hover { color: var(--primary-dark); text-decoration: underline; }
        .auth-footer p {
            color: var(--text-light);
            font-size: 0.875rem;
            margin: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .bg-decoration {
            position: fixed;
            top: -10%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: -1;
        }
        .bg-decoration-2 {
            position: fixed;
            bottom: -10%; left: -10%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(6,182,212,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-decoration-2"></div>

    <div class="auth-card">
        <div class="auth-brand">
            <div class="auth-brand-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3>Create Account</h3>
            <p>Join IntelliHome smart living</p>
        </div>

        <?php if($message === "success"): ?>
            <div class="alert-template alert-success-tmpl">
                <i class="fas fa-check-circle"></i>
                Registration successful! <a href="login.php" style="color: #065f46; text-decoration: underline; font-weight: 700;">Login here</a>
            </div>
        <?php elseif($message === "error"): ?>
            <div class="alert-template alert-danger-tmpl">
                <i class="fas fa-exclamation-circle"></i>
                Error: Username might already exist.
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control is-password" placeholder="Create a password" required>
                    <span class="input-group-text" onclick="togglePassword()">
                        <i class="far fa-eye" id="eyeIcon"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="theme-btn <?php echo $message === 'success' ? 'theme-btn-success' : 'theme-btn-primary'; ?>">
                <i class="fas fa-user-check"></i> 
                <?php echo $message === 'success' ? 'Account Created' : 'Create Account'; ?>
            </button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Sign in</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>