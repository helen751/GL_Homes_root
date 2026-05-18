<?php
/**
 * HOME AUTOMATION IoT - Dashboard
 * Adapted to use existing database schema correctly
 */

// Define constant to prevent direct access
define('STONE_SYSTEM', true);

// --- Direct Database Connection ---
$conn = new mysqli("localhost", "root", "", "home_automation");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Session Handling ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Auth handling ---
if (!isset($_SESSION['user_id'])) {
    $userCheck = $conn->query("SELECT id, username, role FROM users LIMIT 1");
    if ($userCheck && $userCheck->num_rows > 0) {
        $defaultUser = $userCheck->fetch_assoc();
        $_SESSION['user_id'] = $defaultUser['id'];
        $_SESSION['user_role'] = $defaultUser['role'];
        $_SESSION['full_name'] = $defaultUser['username'];
    } else {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['full_name'] = 'Home User';
    }
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'admin';
$isAdmin = ($userRole === 'admin');

// --- Helper: format values ---
function formatValue($value, $unit = '') {
    if ($value === null || $value === '') return '--';
    if ($unit === 'temp') return number_format($value, 1) . '°C';
    if ($unit === 'hum') return number_format($value, 1) . '%';
    if ($unit === 'dist') return $value . ' cm';
    if ($unit === 'light') return $value . ' lx';
    if ($unit === 'smoke') return $value . ' ppm';
    if ($unit === 'bool') return $value ? 'ON' : 'OFF';
    return $value;
}

// --- Fetch latest sensor reading (includes all device status) ---
$latestReading = null;
$result = $conn->query("SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $latestReading = $result->fetch_assoc();
}

// --- Fetch current device status from latest reading (or create default) ---
$deviceStatus = $latestReading;
if (!$deviceStatus) {
    // Default values if no readings exist
    $deviceStatus = [
        'bulb1_status' => 0,
        'bulb2_status' => 0,
        'buzzer_status' => 0,
        'emergency_flag' => 0,
        'auto_light_enabled' => 1,
        'auto_motion_enabled' => 1,
        'smoke_threshold' => 400,
        'light_threshold' => 300,
        'motion_distance' => 50
    ];
}

// --- Get threshold settings from device_status table ---
$thresholdSettings = $deviceStatus;
$settingsResult = $conn->query("SELECT * FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $settingsRow = $settingsResult->fetch_assoc();
    $thresholdSettings = array_merge($thresholdSettings, $settingsRow);
} else {
    // Insert default settings if not exists
    $conn->query("INSERT INTO device_status (device_id) VALUES ('home_unit_01') ON DUPLICATE KEY UPDATE device_id=device_id");
}

// --- Fetch today's statistics ---
$todayStats = ['total_readings' => 0, 'avg_temp' => 0, 'max_temp' => 0, 'min_temp' => 0, 'avg_humidity' => 0, 'max_smoke' => 0, 'emergency_count' => 0];
$statsResult = $conn->query("SELECT 
    COUNT(*) as total_readings,
    AVG(temperature) as avg_temp,
    MAX(temperature) as max_temp,
    MIN(temperature) as min_temp,
    AVG(humidity) as avg_humidity,
    MAX(smoke_level) as max_smoke,
    SUM(emergency_flag) as emergency_count
FROM sensor_readings WHERE DATE(created_at) = CURDATE()");
if ($statsResult && $statsResult->num_rows > 0) {
    $todayStats = $statsResult->fetch_assoc();
}

// --- Fetch recent alerts (last 5) ---
$recentAlerts = [];
$alertsResult = $conn->query("SELECT * FROM alerts_log ORDER BY created_at DESC LIMIT 5");
if ($alertsResult && $alertsResult->num_rows > 0) {
    while ($row = $alertsResult->fetch_assoc()) {
        $recentAlerts[] = $row;
    }
}

// --- Fetch emergency events count pending ---
$pendingEmergencyCount = 0;
$pendingCountResult = $conn->query("SELECT COUNT(*) as cnt FROM emergency_events WHERE is_acknowledged = 0");
if ($pendingCountResult && $pendingCountResult->num_rows > 0) {
    $pendingEmergencyCount = $pendingCountResult->fetch_assoc()['cnt'];
}

// --- Fetch system offline events count (last 24h) ---
$offlineEvents = 0;
$offlineResult = $conn->query("SELECT COUNT(*) as cnt FROM system_log WHERE log_type = 'error' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
if ($offlineResult && $offlineResult->num_rows > 0) {
    $offlineEvents = $offlineResult->fetch_assoc()['cnt'];
}

// --- Chart Data: Last 7 Days Average Temperature ---
$chartLabels = [];
$chartTempValues = [];
$chartHumValues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    $dayData = $conn->query("SELECT AVG(temperature) as avg_temp, AVG(humidity) as avg_hum FROM sensor_readings WHERE DATE(created_at) = '$date'");
    $avgTemp = 0;
    $avgHum = 0;
    if ($dayData && $dayData->num_rows > 0) {
        $row = $dayData->fetch_assoc();
        $avgTemp = round($row['avg_temp'] ?? 0, 1);
        $avgHum = round($row['avg_hum'] ?? 0, 1);
    }
    $chartTempValues[] = $avgTemp;
    $chartHumValues[] = $avgHum;
}

// --- Pending commands count ---
$pendingCommands = 0;
$cmdResult = $conn->query("SELECT COUNT(*) as cnt FROM device_commands WHERE status = 'pending'");
if ($cmdResult && $cmdResult->num_rows > 0) {
    $pendingCommands = $cmdResult->fetch_assoc()['cnt'];
}

// --- System settings ---
$settings = [
    'theme' => 'light',
    'time_format' => '12h',
    'company_name' => 'Home Automation IoT',
    'company_address' => 'Your Smart Home',
    'company_phone' => '+250 788 123 456',
    'company_email' => 'info@homeauto.rw'
];

$currentTheme = $_COOKIE['theme'] ?? $settings['theme'];
$currentLanguage = $_SESSION['language'] ?? 'en';

// Time-based greeting
$hour = (int)date('H');
if ($hour < 12) $greeting = "Good morning";
elseif ($hour < 17) $greeting = "Good afternoon";
else $greeting = "Good evening";

// --- Fetch data for selected date from calendar ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedDateReadings = [];
$selectedDateStats = ['total_readings' => 0, 'avg_temp' => 0, 'max_temp' => 0, 'min_temp' => 0, 'avg_humidity' => 0, 'max_smoke' => 0, 'emergency_count' => 0];
if ($selectedDate) {
    $statsResult = $conn->query("SELECT 
        COUNT(*) as total_readings,
        AVG(temperature) as avg_temp,
        MAX(temperature) as max_temp,
        MIN(temperature) as min_temp,
        AVG(humidity) as avg_humidity,
        MAX(smoke_level) as max_smoke,
        SUM(emergency_flag) as emergency_count
    FROM sensor_readings WHERE DATE(created_at) = '$selectedDate'");
    if ($statsResult && $statsResult->num_rows > 0) {
        $selectedDateStats = $statsResult->fetch_assoc();
    }
    
    $readingsResult = $conn->query("SELECT * FROM sensor_readings WHERE DATE(created_at) = '$selectedDate' ORDER BY created_at DESC LIMIT 20");
    if ($readingsResult && $readingsResult->num_rows > 0) {
        while ($row = $readingsResult->fetch_assoc()) {
            $selectedDateReadings[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['company_name']); ?> - Smart Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Flatpickr for Calendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
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
            --bg-primary: #F0F4F8;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --hover-bg: #F1F5F9;
            --card-shadow: 0 20px 25px -12px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.02);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --hover-bg: #2D3A4F;
            --card-shadow: 0 20px 25px -12px rgba(0,0,0,0.3);
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #E2E8F0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--bk-primary); border-radius: 3px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .slide-in { animation: slideIn 0.3s ease-out forwards; }
        
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #E2E8F0;
            border-top-color: var(--bk-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--hover-bg) 100%);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(135deg, var(--bk-primary), var(--bk-secondary));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .stat-card:hover::after {
            opacity: 1;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 36px -12px rgba(0,45,90,0.2);
        }
        
        .bk-gradient {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
        }
        .bk-red-gradient {
            background: linear-gradient(135deg, var(--bk-secondary) 0%, #B00510 100%);
        }
        
        .table-header {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Preloader Styles */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .preloader-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border-color);
            border-top-color: var(--bk-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        .preloader-progress {
            width: 200px;
            height: 4px;
            background: var(--border-color);
            border-radius: 4px;
            margin: 15px auto 0;
            overflow: hidden;
        }
        .preloader-progress-bar {
            width: 0%;
            height: 100%;
            background: var(--bk-primary);
            animation: progress 3s ease-out forwards;
        }
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        /* Auto-refresh indicator */
        .auto-refresh-badge {
            background: var(--bk-primary);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .blinking-dot {
            width: 8px;
            height: 8px;
            background-color: #4ade80;
            border-radius: 50%;
            display: inline-block;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        /* Tab styles */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }
        
        .tab-nav-btn.active {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
            color: white;
        }
    </style>
</head>
<body data-theme="<?php echo $currentTheme; ?>">

<!-- Preloader -->
<div id="preloader">
    <div class="text-center">
        <div class="preloader-spinner"></div>
        <div class="text-[var(--text-primary)] font-medium">Loading Smart Home Dashboard...</div>
        <div class="preloader-progress">
            <div class="preloader-progress-bar"></div>
        </div>
    </div>
</div>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="fixed lg:static w-[280px] h-screen bg-[var(--bg-card)] shadow-xl flex flex-col z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 border-r border-[var(--border-color)]" id="sidebar">
        <!-- Sidebar Header -->
        <div class="bk-gradient p-6">
            <a href="dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                    <i class="fas fa-microchip text-white text-xl"></i>
                </div>
                <div>
                    <span class="text-white font-bold text-lg tracking-wide">SMART HOME</span>
                    <span class="block text-white/60 text-xs">IoT Control Panel</span>
                </div>
            </a>
        </div>
        
        <!-- User Profile -->
        <div class="p-5 border-b border-[var(--border-color)]">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bk-gradient flex items-center justify-center text-white font-bold text-lg shadow-md">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="flex-1">
                    <div class="font-semibold text-[var(--text-primary)] truncate"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-xs text-[var(--text-secondary)] capitalize"><?php echo $userRole; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-5 px-4">
            <div class="mb-4">
                <button onclick="switchTab('dashboard')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1 active" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span class="flex-1 text-left">Dashboard</span>
                </button>
                <button onclick="switchTab('sensor-history')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="sensor-history">
                    <i class="fas fa-chart-line w-5"></i>
                    <span class="flex-1 text-left">Sensor History</span>
                </button>
                <button onclick="switchTab('alerts-log')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="alerts-log">
                    <i class="fas fa-exclamation-triangle w-5"></i>
                    <span class="flex-1 text-left">Alerts Log</span>
                </button>
                <button onclick="switchTab('device-status')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="device-status">
                    <i class="fas fa-microchip w-5"></i>
                    <span class="flex-1 text-left">Device Status</span>
                </button>
                <button onclick="switchTab('light-control')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="light-control">
                    <i class="fas fa-lightbulb w-5"></i>
                    <span class="flex-1 text-left">Light Control</span>
                </button>
                <button onclick="switchTab('buzzer-control')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="buzzer-control">
                    <i class="fas fa-bell w-5"></i>
                    <span class="flex-1 text-left">Buzzer Control</span>
                </button>
                <button onclick="switchTab('threshold-settings')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="threshold-settings">
                    <i class="fas fa-sliders-h w-5"></i>
                    <span class="flex-1 text-left">Threshold Settings</span>
                </button>
                <button onclick="switchTab('settings')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all mb-1" data-tab="settings">
                    <i class="fas fa-cog w-5"></i>
                    <span class="flex-1 text-left">Settings</span>
                </button>
            </div>
        </nav>
        
        <div class="p-4 border-t border-[var(--border-color)]">
            <div class="text-xs text-center text-[var(--text-secondary)] mb-3 font-mono" id="timeDate"></div>
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] hover:bg-[var(--hover-bg)] hover:text-[var(--bk-secondary)] rounded-lg">
                <i class="fas fa-sign-out-alt"></i><span class="text-sm font-medium">Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <!-- Top Bar -->
        <header class="bg-[var(--bg-card)] border-b border-[var(--border-color)] px-6 py-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden w-10 h-10 flex items-center justify-center text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold gradient-text"><?php echo htmlspecialchars($settings['company_name']); ?></h1>
                        <p class="text-xs text-[var(--text-secondary)]">Real-time Monitoring & Control</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="auto-refresh-badge">
                        <span class="blinking-dot"></span>
                        <span>Auto-refresh: 3s</span>
                    </div>
                    <button class="w-10 h-10 flex items-center justify-center text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all" onclick="refreshData(true)" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6">
            
            <!-- DASHBOARD TAB -->
            <div id="dashboard-tab" class="tab-content active">
                <!-- Welcome Banner -->
                <div class="bk-gradient rounded-2xl p-8 mb-8 text-white relative overflow-hidden shadow-xl">
                    <div class="relative z-10">
                        <h2 class="text-3xl font-light mb-2">
                            <span class="font-semibold"><?php echo $greeting; ?>,</span> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                        </h2>
                        <p class="text-white/80 max-w-2xl text-sm">Monitor your home environment: temperature, humidity, smoke detection, light levels, and control devices remotely.</p>
                        <div class="mt-4 flex items-center gap-2 text-white/90 text-sm">
                            <i class="fas fa-clock"></i>
                            <span>Last update: <strong id="lastUpdateTime"><?php echo date('H:i:s'); ?></strong></span>
                        </div>
                    </div>
                </div>
                
                <!-- Current Sensor Readings -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-thermometer-half text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1" id="tempValue"><?php echo formatValue($latestReading['temperature'] ?? null, 'temp'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Temperature</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-tint text-cyan-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1" id="humValue"><?php echo formatValue($latestReading['humidity'] ?? null, 'hum'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Humidity</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-smog text-amber-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1" id="smokeValue"><?php echo formatValue($latestReading['smoke_level'] ?? null, 'smoke'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Smoke Level</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-sun text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1" id="lightValue"><?php echo formatValue($latestReading['light_level'] ?? null, 'light'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Light Level</div>
                    </div>
                </div>
                
                <!-- Device Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lightbulb text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-[var(--text-primary)] mb-1" id="bulb1Status"><?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Bulb 1</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lightbulb text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-[var(--text-primary)] mb-1" id="bulb2Status"><?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Bulb 2</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-bell text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-[var(--text-primary)] mb-1" id="buzzerStatus"><?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Buzzer</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-[var(--text-primary)] mb-1" id="emergencyFlag"><?php echo ($deviceStatus['emergency_flag'] ?? 0) ? 'ACTIVE' : 'CLEAR'; ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Emergency</div>
                    </div>
                </div>
                
                <!-- Today's Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1"><?php echo number_format($todayStats['total_readings'] ?? 0); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Total Readings Today</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1"><?php echo formatValue($todayStats['avg_temp'] ?? 0, 'temp'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Avg Temp Today</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1"><?php echo formatValue($todayStats['avg_humidity'] ?? 0, 'hum'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)]">Avg Humidity Today</div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="text-3xl font-bold text-[var(--text-primary)] mb-1"><?php echo number_format($todayStats['max_smoke'] ?? 0); ?> ppm</div>
                        <div class="text-sm text-[var(--text-secondary)]">Peak Smoke Today</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="glass-card rounded-xl p-6 fade-in">
                        <h3 class="font-semibold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-line text-[var(--bk-primary)] mr-2"></i> Temperature Trend (Last 7 Days)</h3>
                        <canvas id="tempChart" height="200"></canvas>
                    </div>
                    <div class="glass-card rounded-xl p-6 fade-in">
                        <h3 class="font-semibold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-line text-[var(--bk-primary)] mr-2"></i> Humidity Trend (Last 7 Days)</h3>
                        <canvas id="humChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Recent Alerts -->
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden fade-in mb-8">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-bell text-white/80"></i>
                            <h3 class="font-medium text-white">Recent Alerts</h3>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-[var(--bg-card)]/50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)] uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)] uppercase">Severity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)] uppercase">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)] uppercase">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php if (!empty($recentAlerts)): foreach ($recentAlerts as $alert): ?>
                                <tr class="hover:bg-[var(--hover-bg)] transition">
                                    <td class="px-6 py-3 text-sm capitalize"><?php echo htmlspecialchars($alert['alert_type']); ?></td>
                                    <td class="px-6 py-3 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $alert['severity'] == 'critical' ? 'bg-red-100 text-red-800' : ($alert['severity'] == 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm"><?php echo htmlspecialchars($alert['message']); ?></td>
                                    <td class="px-6 py-3 text-sm"><?php echo date('H:i:s', strtotime($alert['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-[var(--text-secondary)]">
                                        <i class="fas fa-check-circle text-4xl mb-3 opacity-30"></i>
                                        <p>No recent alerts</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- System Health -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-hourglass-half text-amber-500"></i>
                            <h3 class="font-medium">Pending Commands</h3>
                        </div>
                        <div class="text-3xl font-bold"><?php echo $pendingCommands; ?></div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                            <h3 class="font-medium">Unacknowledged Emergencies</h3>
                        </div>
                        <div class="text-3xl font-bold"><?php echo $pendingEmergencyCount; ?></div>
                    </div>
                    <div class="glass-card rounded-xl p-6 stat-card fade-in">
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-wifi text-red-500"></i>
                            <h3 class="font-medium">System Errors (24h)</h3>
                        </div>
                        <div class="text-3xl font-bold"><?php echo $offlineEvents; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- SENSOR HISTORY TAB -->
            <div id="sensor-history-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-alt text-white/80"></i>
                                <h3 class="font-medium text-white">Sensor History by Date</h3>
                            </div>
                            <div>
                                <input type="text" id="datePicker" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-white" placeholder="Select Date" value="<?php echo $selectedDate; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <h4 class="font-semibold text-[var(--text-primary)] mb-4">Statistics for <?php echo date('F j, Y', strtotime($selectedDate)); ?></h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo number_format($selectedDateStats['total_readings'] ?? 0); ?></div>
                                <div class="text-xs text-[var(--text-secondary)]">Total Readings</div>
                            </div>
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($selectedDateStats['avg_temp'] ?? 0, 'temp'); ?></div>
                                <div class="text-xs text-[var(--text-secondary)]">Avg Temp</div>
                            </div>
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($selectedDateStats['max_temp'] ?? 0, 'temp'); ?></div>
                                <div class="text-xs text-[var(--text-secondary)]">Max Temp</div>
                            </div>
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($selectedDateStats['min_temp'] ?? 0, 'temp'); ?></div>
                                <div class="text-xs text-[var(--text-secondary)]">Min Temp</div>
                            </div>
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($selectedDateStats['avg_humidity'] ?? 0, 'hum'); ?></div>
                                <div class="text-xs text-[var(--text-secondary)]">Avg Humidity</div>
                            </div>
                            <div class="bg-[var(--hover-bg)] rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-[var(--bk-primary)]"><?php echo number_format($selectedDateStats['max_smoke'] ?? 0); ?> ppm</div>
                                <div class="text-xs text-[var(--text-secondary)]">Max Smoke</div>
                            </div>
                        </div>
                        
                        <h4 class="font-semibold text-[var(--text-primary)] mb-4">Recent Readings</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-[var(--bg-card)]/50">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Temp (°C)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Humidity (%)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Smoke (ppm)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Light (lx)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Emergency</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <?php if (!empty($selectedDateReadings)): foreach ($selectedDateReadings as $reading): ?>
                                    <tr class="hover:bg-[var(--hover-bg)]">
                                        <td class="px-4 py-3 text-sm"><?php echo date('H:i:s', strtotime($reading['created_at'])); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo formatValue($reading['temperature'], 'temp'); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo formatValue($reading['humidity'], 'hum'); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo formatValue($reading['smoke_level'], 'smoke'); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo formatValue($reading['light_level'], 'light'); ?></td>
                                        <td class="px-4 py-3 text-sm"><?php echo $reading['emergency_flag'] ? '<span class="text-red-600 font-bold">ACTIVE</span>' : 'CLEAR'; ?></td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-[var(--text-secondary)]">
                                            <i class="fas fa-database text-4xl mb-3 opacity-30"></i>
                                            <p>No readings found for this date</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ALERTS LOG TAB -->
            <div id="alerts-log-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-white/80"></i>
                            <h3 class="font-medium text-white">Complete Alerts Log</h3>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-[var(--bg-card)]/50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Severity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Acknowledged</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php
                                $allAlerts = [];
                                $allAlertsResult = $conn->query("SELECT * FROM alerts_log ORDER BY created_at DESC LIMIT 50");
                                if ($allAlertsResult && $allAlertsResult->num_rows > 0) {
                                    while ($row = $allAlertsResult->fetch_assoc()) {
                                        $allAlerts[] = $row;
                                    }
                                }
                                ?>
                                <?php if (!empty($allAlerts)): foreach ($allAlerts as $alert): ?>
                                <tr class="hover:bg-[var(--hover-bg)] transition">
                                    <td class="px-6 py-3 text-sm capitalize"><?php echo htmlspecialchars($alert['alert_type']); ?></td>
                                    <td class="px-6 py-3 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $alert['severity'] == 'critical' ? 'bg-red-100 text-red-800' : ($alert['severity'] == 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm"><?php echo htmlspecialchars($alert['message']); ?></td>
                                    <td class="px-6 py-3 text-sm"><?php echo date('Y-m-d H:i:s', strtotime($alert['created_at'])); ?></td>
                                    <td class="px-6 py-3 text-sm"><?php echo $alert['is_acknowledged'] ? '<span class="text-green-600">Yes</span>' : '<span class="text-yellow-600">No</span>'; ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-[var(--text-secondary)]">
                                        <i class="fas fa-check-circle text-4xl mb-3 opacity-30"></i>
                                        <p>No alerts logged</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- DEVICE STATUS TAB -->
            <div id="device-status-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-microchip text-white/80"></i>
                            <h3 class="font-medium text-white">Device Status & Configuration</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-[var(--text-primary)] mb-4">Current State</h4>
                                <table class="w-full">
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Bulb 1</td>
                                        <td class="py-3"><?php echo ($deviceStatus['bulb1_status'] ?? 0) ? '<span class="text-green-600 font-bold">ON</span>' : '<span class="text-gray-500">OFF</span>'; ?></td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Bulb 2</td>
                                        <td class="py-3"><?php echo ($deviceStatus['bulb2_status'] ?? 0) ? '<span class="text-green-600 font-bold">ON</span>' : '<span class="text-gray-500">OFF</span>'; ?></td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Buzzer</td>
                                        <td class="py-3"><?php echo ($deviceStatus['buzzer_status'] ?? 0) ? '<span class="text-green-600 font-bold">ON</span>' : '<span class="text-gray-500">OFF</span>'; ?></td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Emergency Flag</td>
                                        <td class="py-3"><?php echo ($deviceStatus['emergency_flag'] ?? 0) ? '<span class="text-red-600 font-bold">ACTIVE</span>' : '<span class="text-gray-500">CLEAR</span>'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div>
                                <h4 class="font-semibold text-[var(--text-primary)] mb-4">Threshold Settings</h4>
                                <table class="w-full">
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Smoke Threshold</td>
                                        <td class="py-3"><?php echo $thresholdSettings['smoke_threshold'] ?? 400; ?> ppm</td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Light Threshold</td>
                                        <td class="py-3"><?php echo $thresholdSettings['light_threshold'] ?? 300; ?> lx</td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Motion Distance</td>
                                        <td class="py-3"><?php echo $thresholdSettings['motion_distance'] ?? 50; ?> cm</td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Auto Light Mode</td>
                                        <td class="py-3"><?php echo ($thresholdSettings['auto_light_enabled'] ?? 1) ? 'Enabled' : 'Disabled'; ?></td>
                                    </tr>
                                    <tr class="border-b border-[var(--border-color)]">
                                        <td class="py-3 font-medium">Auto Motion Mode</td>
                                        <td class="py-3"><?php echo ($thresholdSettings['auto_motion_enabled'] ?? 1) ? 'Enabled' : 'Disabled'; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- LIGHT CONTROL TAB -->
            <div id="light-control-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-lightbulb text-white/80"></i>
                            <h3 class="font-medium text-white">Light Control</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="text-center p-6 bg-[var(--hover-bg)] rounded-xl">
                                <i class="fas fa-lightbulb text-6xl text-yellow-500 mb-4"></i>
                                <h3 class="text-xl font-bold mb-2">Bulb 1</h3>
                                <p class="text-[var(--text-secondary)] mb-4">Current: <span id="bulb1StatusControl" class="font-bold"><?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                                <button onclick="sendCommand('bulb1', 'toggle')" class="px-6 py-2 bk-gradient text-white rounded-lg hover:opacity-90 transition">Toggle Bulb 1</button>
                            </div>
                            <div class="text-center p-6 bg-[var(--hover-bg)] rounded-xl">
                                <i class="fas fa-lightbulb text-6xl text-green-500 mb-4"></i>
                                <h3 class="text-xl font-bold mb-2">Bulb 2</h3>
                                <p class="text-[var(--text-secondary)] mb-4">Current: <span id="bulb2StatusControl" class="font-bold"><?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                                <button onclick="sendCommand('bulb2', 'toggle')" class="px-6 py-2 bk-gradient text-white rounded-lg hover:opacity-90 transition">Toggle Bulb 2</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- BUZZER CONTROL TAB -->
            <div id="buzzer-control-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-bell text-white/80"></i>
                            <h3 class="font-medium text-white">Buzzer Control</h3>
                        </div>
                    </div>
                    <div class="p-6 text-center">
                        <i class="fas fa-bell text-6xl text-red-500 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Buzzer</h3>
                        <p class="text-[var(--text-secondary)] mb-4">Current: <span id="buzzerStatusControl" class="font-bold"><?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                        <button onclick="sendCommand('buzzer', 'toggle')" class="px-6 py-2 bk-gradient text-white rounded-lg hover:opacity-90 transition">Toggle Buzzer</button>
                        <button onclick="sendCommand('buzzer', 'off')" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:opacity-90 transition ml-3">Turn OFF</button>
                    </div>
                </div>
            </div>
            
            <!-- THRESHOLD SETTINGS TAB -->
            <div id="threshold-settings-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-sliders-h text-white/80"></i>
                            <h3 class="font-medium text-white">Threshold Settings</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <form id="thresholdForm" class="space-y-4 max-w-md">
                            <div>
                                <label class="block text-sm font-medium mb-1">Smoke Threshold (ppm)</label>
                                <input type="number" id="smokeThreshold" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $thresholdSettings['smoke_threshold'] ?? 400; ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Light Threshold (lx)</label>
                                <input type="number" id="lightThreshold" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $thresholdSettings['light_threshold'] ?? 300; ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Motion Distance (cm)</label>
                                <input type="number" id="motionDistance" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $thresholdSettings['motion_distance'] ?? 50; ?>">
                            </div>
                            <div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="autoLight" <?php echo ($thresholdSettings['auto_light_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="text-sm">Enable Auto Light Mode</span>
                                </label>
                            </div>
                            <div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="autoMotion" <?php echo ($thresholdSettings['auto_motion_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="text-sm">Enable Auto Motion Mode</span>
                                </label>
                            </div>
                            <button type="submit" class="px-6 py-2 bk-gradient text-white rounded-lg hover:opacity-90 transition">Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- SETTINGS TAB -->
            <div id="settings-tab" class="tab-content">
                <div class="glass-card rounded-xl shadow-sm border border-[var(--border-color)] overflow-hidden">
                    <div class="table-header px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-cog text-white/80"></i>
                            <h3 class="font-medium text-white">System Settings</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4 max-w-md">
                            <div>
                                <label class="block text-sm font-medium mb-1">Theme</label>
                                <select id="themeSelect" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]">
                                    <option value="light" <?php echo $currentTheme == 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo $currentTheme == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                </select>
                            </div>
                            <button onclick="saveSettings()" class="px-6 py-2 bk-gradient text-white rounded-lg hover:opacity-90 transition">Save Settings</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <footer class="mt-8 text-center">
                <p class="text-xs text-[var(--text-secondary)]">
                    &copy; <?php echo date('Y'); ?> <?php echo $settings['company_name']; ?> 
                    <span class="mx-2">|</span> Real-time IoT Dashboard 
                    <span class="mx-2">|</span>
                    <span class="text-[var(--bk-primary)] font-medium">Auto-refresh every 3 seconds</span>
                </p>
            </footer>
        </div>
    </main>
</div>

<script>
    // Preloader: fade out after 3 seconds
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            setTimeout(function() {
                preloader.style.opacity = '0';
                setTimeout(function() {
                    preloader.style.display = 'none';
                }, 500);
            }, 3000);
        }
    });
    
    // Sidebar functionality
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('-translate-x-full');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 1024) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
            }
        }
    });
    
    // Tab switching function
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        // Show selected tab
        const selectedTab = document.getElementById(tabName + '-tab');
        if (selectedTab) {
            selectedTab.classList.add('active');
        }
        // Update active state on sidebar buttons
        document.querySelectorAll('.tab-nav-btn').forEach(btn => {
            btn.classList.remove('bk-gradient', 'text-white');
            btn.classList.add('text-[var(--text-primary)]');
        });
        const activeBtn = document.querySelector(`.tab-nav-btn[data-tab="${tabName}"]`);
        if (activeBtn) {
            activeBtn.classList.add('bk-gradient', 'text-white');
            activeBtn.classList.remove('text-[var(--text-primary)]');
        }
    }
    
    // Initialize date picker
    if (typeof flatpickr !== 'undefined') {
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            onChange: function(selectedDates, dateStr, instance) {
                window.location.href = window.location.pathname + '?date=' + dateStr;
            }
        });
    }
    
    // Auto-refresh data
    let refreshInterval;
    let isRefreshing = false;
    
    function refreshData(showLoader = false) {
        if (isRefreshing) return;
        isRefreshing = true;
        
        const refreshBtn = document.getElementById('refreshBtn');
        if (showLoader && refreshBtn) {
            const originalHTML = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<div class="loading-spinner" style="width: 16px; height: 16px;"></div>';
            refreshBtn.disabled = true;
            setTimeout(() => {
                if (refreshBtn) {
                    refreshBtn.innerHTML = originalHTML;
                    refreshBtn.disabled = false;
                }
            }, 1000);
        }
        
        fetch(window.location.pathname + '?ajax=1&t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.temperature !== undefined) document.getElementById('tempValue').innerText = data.temperature;
                    if (data.humidity !== undefined) document.getElementById('humValue').innerText = data.humidity;
                    if (data.smoke !== undefined) document.getElementById('smokeValue').innerText = data.smoke;
                    if (data.light !== undefined) document.getElementById('lightValue').innerText = data.light;
                    if (data.bulb1 !== undefined) {
                        document.getElementById('bulb1Status').innerText = data.bulb1 ? 'ON' : 'OFF';
                        if (document.getElementById('bulb1StatusControl')) document.getElementById('bulb1StatusControl').innerText = data.bulb1 ? 'ON' : 'OFF';
                    }
                    if (data.bulb2 !== undefined) {
                        document.getElementById('bulb2Status').innerText = data.bulb2 ? 'ON' : 'OFF';
                        if (document.getElementById('bulb2StatusControl')) document.getElementById('bulb2StatusControl').innerText = data.bulb2 ? 'ON' : 'OFF';
                    }
                    if (data.buzzer !== undefined) {
                        document.getElementById('buzzerStatus').innerText = data.buzzer ? 'ON' : 'OFF';
                        if (document.getElementById('buzzerStatusControl')) document.getElementById('buzzerStatusControl').innerText = data.buzzer ? 'ON' : 'OFF';
                    }
                    if (data.emergency !== undefined) document.getElementById('emergencyFlag').innerText = data.emergency ? 'ACTIVE' : 'CLEAR';
                    if (data.lastUpdate) document.getElementById('lastUpdateTime').innerText = data.lastUpdate;
                    
                    if (data.chartLabels && data.chartTempValues && window.tempChart) {
                        window.tempChart.data.labels = data.chartLabels;
                        window.tempChart.data.datasets[0].data = data.chartTempValues;
                        window.tempChart.update();
                    }
                    if (data.chartLabels && data.chartHumValues && window.humChart) {
                        window.humChart.data.labels = data.chartLabels;
                        window.humChart.data.datasets[0].data = data.chartHumValues;
                        window.humChart.update();
                    }
                }
            })
            .catch(err => console.warn('Auto-refresh error:', err))
            .finally(() => { isRefreshing = false; });
    }
    
    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(() => refreshData(false), 3000);
    }
    
    // Update real-time clock
    function updateDateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const timeElement = document.getElementById('timeDate');
        if (timeElement) timeElement.textContent = timeString;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Chart Initialization
    let tempChart, humChart;
    document.addEventListener('DOMContentLoaded', function() {
        const tempCtx = document.getElementById('tempChart')?.getContext('2d');
        if (tempCtx) {
            tempChart = new Chart(tempCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Temperature (°C)',
                        data: <?php echo json_encode($chartTempValues); ?>,
                        borderColor: '#E30613',
                        backgroundColor: 'rgba(227, 6, 19, 0.05)',
                        borderWidth: 3,
                        pointBackgroundColor: '#002D5A',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        }
        
        const humCtx = document.getElementById('humChart')?.getContext('2d');
        if (humCtx) {
            humChart = new Chart(humCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Humidity (%)',
                        data: <?php echo json_encode($chartHumValues); ?>,
                        borderColor: '#0288D1',
                        backgroundColor: 'rgba(2, 136, 209, 0.05)',
                        borderWidth: 3,
                        pointBackgroundColor: '#002D5A',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        }
        
        window.tempChart = tempChart;
        window.humChart = humChart;
        startAutoRefresh();
    });
    
    window.refreshData = refreshData;
    
    // Send command to device via AJAX
    function sendCommand(device, action) {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=' + device + '&command=' + action
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Command sent successfully!', 'success');
                setTimeout(() => refreshData(true), 500);
            } else {
                showToast('Failed to send command', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error sending command', 'error');
        });
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ' + (type === 'success' ? 'bg-green-600' : 'bg-red-600');
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Threshold form submission
    const thresholdForm = document.getElementById('thresholdForm');
    if (thresholdForm) {
        thresholdForm.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=thresholds&smoke=' + document.getElementById('smokeThreshold').value + '&light=' + document.getElementById('lightThreshold').value + '&motion=' + document.getElementById('motionDistance').value + '&auto_light=' + (document.getElementById('autoLight').checked ? 1 : 0) + '&auto_motion=' + (document.getElementById('autoMotion').checked ? 1 : 0)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) showToast('Settings saved!', 'success');
                else showToast('Failed to save settings', 'error');
            });
        });
    }
    
    // Theme settings
    function saveSettings() {
        const theme = document.getElementById('themeSelect').value;
        document.body.setAttribute('data-theme', theme);
        document.cookie = "theme=" + theme + "; path=/";
        showToast('Theme saved!', 'success');
    }
    
    // Set active tab based on current hash or default
    function setActiveTabFromHash() {
        const hash = window.location.hash.substring(1);
        if (hash && document.getElementById(hash + '-tab')) {
            switchTab(hash);
        }
    }
    setActiveTabFromHash();
    window.addEventListener('hashchange', setActiveTabFromHash);
</script>

<?php
// Handle AJAX request for auto-refresh data
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $ajaxData = ['success' => false];
    
    // Get latest sensor reading
    $result = $conn->query("SELECT temperature, humidity, smoke_level, light_level, bulb1_status, bulb2_status, buzzer_status, emergency_flag FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ajaxData['temperature'] = formatValue($row['temperature'] ?? null, 'temp');
        $ajaxData['humidity'] = formatValue($row['humidity'] ?? null, 'hum');
        $ajaxData['smoke'] = formatValue($row['smoke_level'] ?? null, 'smoke');
        $ajaxData['light'] = formatValue($row['light_level'] ?? null, 'light');
        $ajaxData['bulb1'] = $row['bulb1_status'] ?? 0;
        $ajaxData['bulb2'] = $row['bulb2_status'] ?? 0;
        $ajaxData['buzzer'] = $row['buzzer_status'] ?? 0;
        $ajaxData['emergency'] = $row['emergency_flag'] ?? 0;
    }
    
    // Get chart data
    $chartLabelsAjax = [];
    $chartTempAjax = [];
    $chartHumAjax = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabelsAjax[] = date('M d', strtotime($date));
        $dayData = $conn->query("SELECT AVG(temperature) as avg_temp, AVG(humidity) as avg_hum FROM sensor_readings WHERE DATE(created_at) = '$date'");
        $avgTemp = 0; $avgHum = 0;
        if ($dayData && $dayData->num_rows > 0) {
            $row = $dayData->fetch_assoc();
            $avgTemp = round($row['avg_temp'] ?? 0, 1);
            $avgHum = round($row['avg_hum'] ?? 0, 1);
        }
        $chartTempAjax[] = $avgTemp;
        $chartHumAjax[] = $avgHum;
    }
    $ajaxData['chartLabels'] = $chartLabelsAjax;
    $ajaxData['chartTempValues'] = $chartTempAjax;
    $ajaxData['chartHumValues'] = $chartHumAjax;
    $ajaxData['lastUpdate'] = date('H:i:s');
    $ajaxData['success'] = true;
    
    echo json_encode($ajaxData);
    exit;
}

$conn->close();
?>
</body>
</html>