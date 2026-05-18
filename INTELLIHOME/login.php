<?php
/**
 * HOME AUTOMATION IoT - Login Page
 * Adapted for home_automation database with MySQLi direct connection
 * All styles and JavaScript remain unchanged
 */

session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "home_automation");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($location) {
    header("Location: " . $location);
    exit;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            // Query user from home_automation database (supports username or email)
            $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, email, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last_login timestamp
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Handle "Remember Me" - extend session cookie lifetime to 30 days
                    if ($remember) {
                        ini_set('session.cookie_lifetime', 2592000); // 30 days
                        session_regenerate_id(true);
                    }
                    
                    redirect('pages/dashboard.php');
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Username or email not found.';
            }
            $stmt->close();
        }
    }
}

// Get flash messages (e.g., from logout)
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}

// System settings for Home Automation IoT (hardcoded, no database dependency)
$settings = [
    'company_name' => 'Home Automation IoT',
    'company_address' => 'Kigali, Rwanda',
    'company_phone' => '+250 788 123 456',
    'company_email' => 'info@homeauto.rw'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Home Automation IoT - Smart Home Control System">
    <title>Login - <?php echo htmlspecialchars($settings['company_name']); ?></title>
    
    <!-- Tailwind CSS (same as dashboard) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter (same as dashboard) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Bank of Kigali Colors / Danix Theme - matching dashboard */
        :root {
            --bk-primary: #002D5A;
            --bk-secondary: #E30613;
            --bk-accent: #F8FAFC;
            --bk-gold: #9E8B5F;
            --bk-success: #2E7D32;
            --bk-warning: #ED6C02;
            --bk-info: #0288D1;
            --bk-light-bg: #F0F4F8;
            --bk-card-bg: #FFFFFF;
            --bk-text-primary: #1E293B;
            --bk-text-secondary: #64748B;
        }
        
        body {
            background: linear-gradient(135deg, #F0F4F8 0%, #E2E8F0 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        /* Full-width container - NO CENTERING, covers entire page */
        .full-width-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
        }
        
        /* Glass morphism card - full height */
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* BK Gradient matching dashboard */
        .bk-gradient {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
        }
        
        .bk-red-gradient {
            background: linear-gradient(135deg, var(--bk-secondary) 0%, #B00510 100%);
        }
        
        /* Animated background pattern */
        .bg-pattern {
            background-image: radial-gradient(circle at 10px 10px, rgba(0,45,90,0.03) 2px, transparent 2px);
            background-size: 30px 30px;
        }
        
        /* Floating animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        /* Pulse animation for logo */
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(0,45,90,0.4); }
            70% { box-shadow: 0 0 0 20px rgba(0,45,90,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,45,90,0); }
        }
        
        .logo-glow {
            animation: pulse-glow 2s infinite;
        }
        
        /* Input styling matching dashboard */
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
            color: #1E293B;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--bk-primary);
            box-shadow: 0 0 0 3px rgba(0,45,90,0.1);
        }
        
        .form-input.error {
            border-color: var(--bk-secondary);
        }
        
        /* Button styling matching dashboard */
        .btn-login {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,45,90,0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Loading spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            display: inline-block;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Alert styling */
        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #FEE2E2;
            border-left: 4px solid var(--bk-secondary);
            color: #991B1B;
        }
        
        .alert-success {
            background: #DCFCE7;
            border-left: 4px solid var(--bk-success);
            color: #166534;
        }
        
        .alert-icon {
            font-size: 1.25rem;
        }
        
        /* Feature items */
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255,255,255,0.08);
            border-radius: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .feature-item:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .feature-item i {
            width: 1.75rem;
            font-size: 1.25rem;
        }
        
        /* Password toggle button */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748B;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: var(--bk-primary);
        }
        
        /* Input group */
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1rem;
            pointer-events: none;
        }
        
        /* Checkbox styling */
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .form-check-input {
            width: 1.125rem;
            height: 1.125rem;
            cursor: pointer;
            accent-color: var(--bk-primary);
        }
        
        .form-check-label {
            font-size: 0.875rem;
            color: #475569;
            cursor: pointer;
        }
        
        /* Links */
        .forgot-link {
            font-size: 0.875rem;
            color: var(--bk-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .forgot-link:hover {
            color: var(--bk-secondary);
        }
        
        /* Custom scrollbar (matching dashboard) */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #E2E8F0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--bk-primary); border-radius: 3px; }
        
        /* Left side - Branding panel - full height */
        .branding-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        /* Right side - Form panel - full height */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }
        
        /* Form wrapper - centered vertically within the right panel */
        .form-wrapper {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .full-width-container {
                flex-direction: column;
            }
            
            .branding-panel {
                min-height: 50vh;
            }
            
            .form-panel {
                min-height: 50vh;
            }
            
            .form-wrapper {
                padding: 2rem 1.5rem;
            }
            
            .feature-item span {
                font-size: 0.875rem;
            }
        }
        
        /* Animation for form appearance */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-wrapper {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-pattern">
    <!-- Full-width container - covers entire page, no centering -->
    <div class="full-width-container">
        
        <!-- Left Side - Branding Panel (Full height, covers left half) -->
        <div class="branding-panel bk-gradient relative overflow-hidden">
            <!-- Background pattern overlay -->
            <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2rem 2rem, white 2px, transparent 2px); background-size: 3rem 3rem;"></div>
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-80 h-80 bg-white/5 rounded-full blur-2xl"></div>
            
            <div class="relative z-10 flex flex-col h-full p-8 md:p-12 lg:p-16">
                <!-- Logo Area -->
                <div class="mb-12">
                    <div class="flex items-center gap-3">
                        <div class="w-16 h-16 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-sm logo-glow">
                            <i class="fas fa-microchip text-white text-2xl"></i>
                        </div>
                        <div>
                            <span class="text-white font-bold text-2xl tracking-wide">HomeAuto</span>
                            <span class="block text-white/60 text-sm">IoT Control Center</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex-1">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Smart<br>
                        Home<br>
                        <span class="text-white/90">Automation</span>
                    </h1>
                    <p class="text-white/80 text-base md:text-lg mb-10 leading-relaxed max-w-md">
                        Complete IoT platform for monitoring and controlling your home environment. 
                        Real-time sensor data, smart device management, and emergency alerts from one dashboard.
                    </p>
                    
                    <!-- Features Grid - Home Automation focused -->
                    <div class="grid grid-cols-2 gap-4 max-w-lg">
                        <div class="feature-item">
                            <i class="fas fa-thermometer-half text-white/70"></i>
                            <span class="text-white/90 text-sm">Temperature & Humidity</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-lightbulb text-white/70"></i>
                            <span class="text-white/90 text-sm">Smart Lighting Control</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-running text-white/70"></i>
                            <span class="text-white/90 text-sm">Motion Detection</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-bell text-white/70"></i>
                            <span class="text-white/90 text-sm">Emergency Alerts</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-charging-station text-white/70"></i>
                            <span class="text-white/90 text-sm">Energy Monitoring</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-wifi text-white/70"></i>
                            <span class="text-white/90 text-sm">Remote Access</span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="mt-12 pt-6 border-t border-white/10">
                    <p class="text-white/50 text-xs text-center">
                        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['company_name']); ?>
                    </p>
                    <p class="text-white/40 text-xs text-center mt-1">
                        <i class="fas fa-clock mr-1"></i> Kigali Time (CAT)
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Login Form Panel (Full height, covers right half) -->
        <div class="form-panel">
            <div class="form-wrapper">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-20 h-20 bk-gradient rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg">
                        <i class="fas fa-home text-white text-2xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-800">Welcome Back</h2>
                    <p class="text-gray-500 mt-2">Sign in to access your smart home dashboard</p>
                </div>
                
                <!-- Error Alert -->
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Success Alert -->
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Username Field -->
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">
                            Username or Email
                        </label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input" 
                                placeholder="Enter your username or email"
                                required
                                autocomplete="username"
                                autofocus
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            >
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">
                            Password
                        </label>
                        <div class="input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div class="flex items-center justify-between mb-8">
                        <label class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <span class="form-check-label">Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link">
                            Forgot password?
                        </a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-login w-full py-3 rounded-xl text-white font-semibold text-base flex items-center justify-center gap-2 transition-all" id="loginBtn">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                
                <!-- Register Link -->
                <div class="mt-8 pt-6 text-center border-t border-gray-100">
                    <p class="text-sm text-gray-500">
                        Don't have an account? 
                        <a href="register.php" class="text-[#002D5A] font-semibold hover:text-[#E30613] transition-colors">
                            Register here
                        </a>
                    </p>
                    <div class="flex items-center justify-center gap-4 mt-4 text-xs text-gray-400">
                        <span><i class="fas fa-shield-alt mr-1"></i> Secure connection</span>
                        <span>|</span>
                        <span><i class="fas fa-lock mr-1"></i> 256-bit SSL</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
        
        // Form submission loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        if (loginForm && loginBtn) {
            loginForm.addEventListener('submit', function() {
                const originalContent = loginBtn.innerHTML;
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="spinner"></span><span class="ml-2">Signing in...</span>';
                loginBtn.style.opacity = '0.7';
                return true;
            });
        }
        
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert && alert.parentElement) {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert && alert.parentElement) alert.remove();
                    }, 300);
                }
            }, 5000);
        });
        
        // Add floating animation to logo
        const logoContainer = document.querySelector('.w-16.h-16');
        if (logoContainer) {
            logoContainer.classList.add('float-animation');
        }
        
        // Input focus effects
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.input-icon i')?.classList.add('text-[#002D5A]');
            });
            input.addEventListener('blur', function() {
                this.parentElement.querySelector('.input-icon i')?.classList.remove('text-[#002D5A]');
            });
        });
    });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>