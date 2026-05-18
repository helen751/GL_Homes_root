<?php
/**
 * HOME AUTOMATION IoT - Dashboard
 * Fixed for real-time data refresh and accurate sensor readings
 */

// Define constant to prevent direct access
define('STONE_SYSTEM', true);

// --- Direct Database Connection ---
$conn = new mysqli("localhost", "glhorgia_admin", "GLHOMES_DB_ADMIN06", "glhorgia_wms_home_automation");
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

// --- Fetch latest sensor reading ---
$latestReading = null;
$result = $conn->query("SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $latestReading = $result->fetch_assoc();
}

// --- Fetch current device status from device_status table ---
$deviceStatus = null;
$statusResult = $conn->query("SELECT * FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
if ($statusResult && $statusResult->num_rows > 0) {
    $deviceStatus = $statusResult->fetch_assoc();
} else {
    // Insert default settings if not exists
    $conn->query("INSERT INTO device_status (device_id) VALUES ('home_unit_01')");
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

// --- Fetch recent alerts (last 10) ---
$recentAlerts = [];
$alertsResult = $conn->query("SELECT * FROM alerts_log ORDER BY created_at DESC LIMIT 10");
if ($alertsResult && $alertsResult->num_rows > 0) {
    while ($row = $alertsResult->fetch_assoc()) {
        $recentAlerts[] = $row;
    }
}

// --- Chart Data: Last 7 Days ---
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

$currentTheme = $_COOKIE['theme'] ?? 'light';

// Time-based greeting
$hour = (int)date('H');
if ($hour < 12) $greeting = "Good morning";
elseif ($hour < 17) $greeting = "Good afternoon";
else $greeting = "Good evening";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Automation IoT - Smart Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        :root {
            --bk-primary: #002D5A;
            --bk-secondary: #E30613;
            --bk-accent: #F8FAFC;
            --bg-primary: #F0F4F8;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --hover-bg: #F1F5F9;
            --card-shadow: 0 20px 25px -12px rgba(0,0,0,0.05);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --hover-bg: #2D3A4F;
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
            border-radius: 1rem;
        }
        
        .bk-gradient {
            background: linear-gradient(135deg, var(--bk-primary) 0%, #1E3A6F 100%);
        }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0,45,90,0.2);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse { animation: pulse 2s infinite; }
        
        .online-dot {
            width: 10px;
            height: 10px;
            background-color: #22c55e;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        .offline-dot {
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            display: inline-block;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #E2E8F0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--bk-primary); border-radius: 3px; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        }
        .toast-success { background: #22c55e; }
        .toast-error { background: #ef4444; }
        .toast-info { background: #3b82f6; }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: var(--bk-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body data-theme="<?php echo $currentTheme; ?>">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="fixed lg:static w-[280px] h-screen bg-[var(--bg-card)] shadow-xl flex flex-col z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 border-r border-[var(--border-color)]" id="sidebar">
        <div class="bk-gradient p-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                    <i class="fas fa-microchip text-white text-xl"></i>
                </div>
                <div>
                    <span class="text-white font-bold text-lg tracking-wide">SMART HOME</span>
                    <span class="block text-white/60 text-xs">IoT Control Panel</span>
                </div>
            </div>
        </div>
        
        <div class="p-5 border-b border-[var(--border-color)]">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bk-gradient flex items-center justify-center text-white font-bold text-lg shadow-md">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="flex-1">
                    <div class="font-semibold text-[var(--text-primary)] truncate"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="online-dot"></span>
                        <span class="text-xs text-[var(--text-secondary)]">Online</span>
                    </div>
                </div>
            </div>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-5 px-4">
            <div class="space-y-1">
                <button onclick="switchTab('dashboard')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt w-5"></i><span>Dashboard</span>
                </button>
                <button onclick="switchTab('controls')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="controls">
                    <i class="fas fa-gamepad w-5"></i><span>Device Controls</span>
                </button>
                <button onclick="switchTab('history')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="history">
                    <i class="fas fa-chart-line w-5"></i><span>Sensor History</span>
                </button>
                <button onclick="switchTab('alerts')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="alerts">
                    <i class="fas fa-exclamation-triangle w-5"></i><span>Alerts Log</span>
                </button>
                <button onclick="switchTab('thresholds')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="thresholds">
                    <i class="fas fa-sliders-h w-5"></i><span>Threshold Settings</span>
                </button>
                <button onclick="switchTab('settings')" class="tab-nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-[var(--text-primary)] hover:bg-[var(--hover-bg)]" data-tab="settings">
                    <i class="fas fa-cog w-5"></i><span>Settings</span>
                </button>
            </div>
        </nav>
        
        <div class="p-4 border-t border-[var(--border-color)]">
            <div class="text-xs text-center text-[var(--text-secondary)] mb-3 font-mono" id="timeDate"></div>
            <button onclick="location.reload()" class="w-full py-2.5 bg-[var(--bg-card)] border border-[var(--border-color)] text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg flex items-center justify-center gap-2">
                <i class="fas fa-sync-alt"></i><span class="text-sm font-medium">Refresh</span>
            </button>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="bg-[var(--bg-card)] border-b border-[var(--border-color)] px-6 py-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden w-10 h-10 flex items-center justify-center text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold" style="background: linear-gradient(135deg, #002D5A, #E30613); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Home Automation IoT</h1>
                        <p class="text-xs text-[var(--text-secondary)]">Real-time Monitoring & Control</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-[var(--hover-bg)] px-3 py-1.5 rounded-full">
                        <span class="online-dot"></span>
                        <span class="text-xs font-medium text-[var(--text-secondary)]">Live Data</span>
                        <span class="text-xs text-[var(--text-primary)]" id="lastUpdateTime">--:--:--</span>
                    </div>
                    <button onclick="manualRefresh()" id="refreshBtn" class="w-10 h-10 flex items-center justify-center text-[var(--text-primary)] hover:bg-[var(--hover-bg)] rounded-lg transition-all">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-6">
            
            <!-- DASHBOARD TAB -->
            <div id="dashboard-tab" class="tab-content active">
                <div class="bk-gradient rounded-2xl p-8 mb-8 text-white relative overflow-hidden shadow-xl">
                    <div class="relative z-10">
                        <h2 class="text-3xl font-light mb-2">
                            <span class="font-semibold"><?php echo $greeting; ?>,</span> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                        </h2>
                        <p class="text-white/80 max-w-2xl text-sm">Monitor your home environment: temperature, humidity, smoke detection, light levels, and control devices remotely.</p>
                    </div>
                </div>
                
                <!-- Current Sensor Readings -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-thermometer-half text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)]" id="tempValue"><?php echo formatValue($latestReading['temperature'] ?? null, 'temp'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)] mt-1">Temperature</div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-tint text-cyan-600 text-2xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)]" id="humValue"><?php echo formatValue($latestReading['humidity'] ?? null, 'hum'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)] mt-1">Humidity</div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-smog text-amber-600 text-2xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)]" id="smokeValue"><?php echo formatValue($latestReading['smoke_level'] ?? null, 'smoke'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)] mt-1">Smoke Level</div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                <i class="fas fa-sun text-purple-600 text-2xl"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-[var(--text-primary)]" id="lightValue"><?php echo formatValue($latestReading['light_level'] ?? null, 'light'); ?></div>
                        <div class="text-sm text-[var(--text-secondary)] mt-1">Light Level</div>
                    </div>
                </div>
                
                <!-- Device Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-[var(--text-primary)]" id="bulb1Status"><?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                                <div class="text-sm text-[var(--text-secondary)]">Bulb 1</div>
                            </div>
                            <i class="fas fa-lightbulb text-3xl <?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'text-yellow-500' : 'text-gray-400'; ?>"></i>
                        </div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-[var(--text-primary)]" id="bulb2Status"><?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                                <div class="text-sm text-[var(--text-secondary)]">Bulb 2</div>
                            </div>
                            <i class="fas fa-lightbulb text-3xl <?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                        </div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-[var(--text-primary)]" id="buzzerStatus"><?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'ON' : 'OFF'; ?></div>
                                <div class="text-sm text-[var(--text-secondary)]">Buzzer</div>
                            </div>
                            <i class="fas fa-bell text-3xl <?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'text-red-500' : 'text-gray-400'; ?>"></i>
                        </div>
                    </div>
                    <div class="glass-card p-6 stat-card fade-in">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-[var(--text-primary)]" id="emergencyFlag"><?php echo ($deviceStatus['emergency_flag'] ?? 0) ? 'ACTIVE' : 'CLEAR'; ?></div>
                                <div class="text-sm text-[var(--text-secondary)]">Emergency</div>
                            </div>
                            <i class="fas fa-exclamation-triangle text-3xl <?php echo ($deviceStatus['emergency_flag'] ?? 0) ? 'text-red-500 animate-pulse' : 'text-gray-400'; ?>"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="glass-card p-6 fade-in">
                        <h3 class="font-semibold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-line text-[var(--bk-primary)] mr-2"></i> Temperature Trend (Last 7 Days)</h3>
                        <canvas id="tempChart" height="200"></canvas>
                    </div>
                    <div class="glass-card p-6 fade-in">
                        <h3 class="font-semibold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-line text-[var(--bk-primary)] mr-2"></i> Humidity Trend (Last 7 Days)</h3>
                        <canvas id="humChart" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Today's Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
                    <div class="glass-card p-6 text-center">
                        <div class="text-3xl font-bold text-[var(--bk-primary)]"><?php echo number_format($todayStats['total_readings'] ?? 0); ?></div>
                        <div class="text-xs text-[var(--text-secondary)]">Readings Today</div>
                    </div>
                    <div class="glass-card p-6 text-center">
                        <div class="text-3xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($todayStats['avg_temp'] ?? 0, 'temp'); ?></div>
                        <div class="text-xs text-[var(--text-secondary)]">Avg Temp</div>
                    </div>
                    <div class="glass-card p-6 text-center">
                        <div class="text-3xl font-bold text-[var(--bk-primary)]"><?php echo formatValue($todayStats['avg_humidity'] ?? 0, 'hum'); ?></div>
                        <div class="text-xs text-[var(--text-secondary)]">Avg Humidity</div>
                    </div>
                    <div class="glass-card p-6 text-center">
                        <div class="text-3xl font-bold text-[var(--bk-primary)]"><?php echo number_format($todayStats['max_smoke'] ?? 0); ?> ppm</div>
                        <div class="text-xs text-[var(--text-secondary)]">Peak Smoke</div>
                    </div>
                </div>
                
                <!-- Recent Alerts -->
                <div class="glass-card overflow-hidden">
                    <div class="bk-gradient px-6 py-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-bell text-white/80"></i>
                            <h3 class="font-medium text-white">Recent Alerts</h3>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[var(--hover-bg)]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Severity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--text-secondary)]">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php if (!empty($recentAlerts)): foreach ($recentAlerts as $alert): ?>
                                <tr class="hover:bg-[var(--hover-bg)]">
                                    <td class="px-6 py-3 text-sm capitalize"><?php echo htmlspecialchars($alert['alert_type']); ?></td>
                                    <td class="px-6 py-3">
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
                                        <i class="fas fa-check-circle text-4xl mb-3 opacity-30 block"></i>
                                        No recent alerts
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- CONTROLS TAB -->
            <div id="controls-tab" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Bulb 1 Control -->
                    <div class="glass-card p-8 text-center">
                        <i class="fas fa-lightbulb text-6xl <?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'text-yellow-500' : 'text-gray-400'; ?> mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Bulb 1</h3>
                        <p class="text-[var(--text-secondary)] mb-6">Status: <span id="ctrlBulb1Status" class="font-bold"><?php echo ($deviceStatus['bulb1_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                        <button onclick="sendCommand('bulb1', 'toggle')" class="px-8 py-3 bk-gradient text-white rounded-lg hover:opacity-90 transition-all">
                            <i class="fas fa-power-off mr-2"></i> Toggle
                        </button>
                    </div>
                    
                    <!-- Bulb 2 Control -->
                    <div class="glass-card p-8 text-center">
                        <i class="fas fa-lightbulb text-6xl <?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'text-green-500' : 'text-gray-400'; ?> mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Bulb 2</h3>
                        <p class="text-[var(--text-secondary)] mb-6">Status: <span id="ctrlBulb2Status" class="font-bold"><?php echo ($deviceStatus['bulb2_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                        <button onclick="sendCommand('bulb2', 'toggle')" class="px-8 py-3 bk-gradient text-white rounded-lg hover:opacity-90 transition-all">
                            <i class="fas fa-power-off mr-2"></i> Toggle
                        </button>
                    </div>
                    
                    <!-- Buzzer Control -->
                    <div class="glass-card p-8 text-center">
                        <i class="fas fa-bell text-6xl <?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'text-red-500' : 'text-gray-400'; ?> mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Buzzer</h3>
                        <p class="text-[var(--text-secondary)] mb-6">Status: <span id="ctrlBuzzerStatus" class="font-bold"><?php echo ($deviceStatus['buzzer_status'] ?? 0) ? 'ON' : 'OFF'; ?></span></p>
                        <div class="flex gap-4 justify-center">
                            <button onclick="sendCommand('buzzer', 'on')" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all">
                                <i class="fas fa-play mr-2"></i> ON
                            </button>
                            <button onclick="sendCommand('buzzer', 'off')" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                                <i class="fas fa-stop mr-2"></i> OFF
                            </button>
                        </div>
                    </div>
                    
                    <!-- Emergency Reset -->
                    <div class="glass-card p-8 text-center">
                        <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Emergency Reset</h3>
                        <p class="text-[var(--text-secondary)] mb-6">Reset emergency flag and buzzer</p>
                        <button onclick="resetEmergency()" class="px-8 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                            <i class="fas fa-undo-alt mr-2"></i> Reset Emergency
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- HISTORY TAB -->
            <div id="history-tab" class="tab-content">
                <div class="glass-card overflow-hidden">
                    <div class="bk-gradient px-6 py-4">
                        <h3 class="font-medium text-white"><i class="fas fa-history mr-2"></i> Sensor History</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">Filter by Date</label>
                            <input type="date" id="historyDate" class="px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]">
                            <button onclick="loadHistory()" class="ml-3 px-6 py-2 bk-gradient text-white rounded-lg">Load</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full" id="historyTable">
                                <thead class="bg-[var(--hover-bg)]">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Temp (°C)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Humidity (%)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Smoke (ppm)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Light (lx)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium">Emergency</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-[var(--text-secondary)]">Select a date to view history</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ALERTS TAB -->
            <div id="alerts-tab" class="tab-content">
                <div class="glass-card overflow-hidden">
                    <div class="bk-gradient px-6 py-4">
                        <h3 class="font-medium text-white"><i class="fas fa-exclamation-triangle mr-2"></i> All Alerts</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full" id="alertsTable">
                            <thead class="bg-[var(--hover-bg)]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium">Severity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-[var(--text-secondary)]">Loading alerts...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- THRESHOLDS TAB -->
            <div id="thresholds-tab" class="tab-content">
                <div class="glass-card p-6 max-w-2xl mx-auto">
                    <h3 class="text-xl font-bold mb-6"><i class="fas fa-sliders-h text-[var(--bk-primary)] mr-2"></i> Threshold Settings</h3>
                    <form id="thresholdForm" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium mb-2">Smoke Threshold (ppm)</label>
                            <input type="number" id="smokeThreshold" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $deviceStatus['smoke_threshold'] ?? 400; ?>">
                            <p class="text-xs text-[var(--text-secondary)] mt-1">If smoke exceeds this value, emergency is triggered</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Light Threshold (lx)</label>
                            <input type="number" id="lightThreshold" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $deviceStatus['light_threshold'] ?? 300; ?>">
                            <p class="text-xs text-[var(--text-secondary)] mt-1">If light drops below this, auto lights turn on</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Motion Distance (cm)</label>
                            <input type="number" id="motionDistance" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]" value="<?php echo $deviceStatus['motion_distance'] ?? 50; ?>">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="autoLight" <?php echo ($deviceStatus['auto_light_enabled'] ?? 1) ? 'checked' : ''; ?> class="w-4 h-4">
                            <label class="text-sm">Enable Auto Light Mode (lights turn on when dark)</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="autoMotion" <?php echo ($deviceStatus['auto_motion_enabled'] ?? 1) ? 'checked' : ''; ?> class="w-4 h-4">
                            <label class="text-sm">Enable Auto Motion Detection</label>
                        </div>
                        <button type="submit" class="w-full py-3 bk-gradient text-white rounded-lg hover:opacity-90 transition-all">
                            <i class="fas fa-save mr-2"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- SETTINGS TAB -->
            <div id="settings-tab" class="tab-content">
                <div class="glass-card p-6 max-w-2xl mx-auto">
                    <h3 class="text-xl font-bold mb-6"><i class="fas fa-cog text-[var(--bk-primary)] mr-2"></i> System Settings</h3>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium mb-2">Theme</label>
                            <select id="themeSelect" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]">
                                <option value="light" <?php echo $currentTheme == 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo $currentTheme == 'dark' ? 'selected' : ''; ?>>Dark</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Auto-Refresh Interval (seconds)</label>
                            <select id="refreshInterval" class="w-full px-4 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--bg-card)] text-[var(--text-primary)]">
                                <option value="2">2 seconds</option>
                                <option value="3" selected>3 seconds</option>
                                <option value="5">5 seconds</option>
                                <option value="10">10 seconds</option>
                            </select>
                        </div>
                        <button onclick="saveSettings()" class="w-full py-3 bk-gradient text-white rounded-lg hover:opacity-90 transition-all">
                            <i class="fas fa-save mr-2"></i> Save Settings
                        </button>
                    </div>
                </div>
            </div>
            
            <footer class="mt-8 text-center">
                <p class="text-xs text-[var(--text-secondary)]">
                    &copy; <?php echo date('Y'); ?> Home Automation IoT | Real-time Dashboard | Auto-refresh Active
                </p>
            </footer>
        </div>
    </main>
</div>

<script>
    let refreshIntervalId = null;
    let currentRefreshInterval = 3;
    
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
    }
    
    // Tab switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.getElementById(tabName + '-tab').classList.add('active');
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
    
    // Show toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')} mr-2"></i>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Fetch and update dashboard data
    async function fetchLiveData() {
    try {
        const response = await fetch('api.php?action=get_latest&t=' + Date.now());
        const data = await response.json();

        console.log("Live data:", data);

        if (data.success && data.reading) {
            const r = data.reading;

            const temperature = parseFloat(r.temperature);
            const humidity = parseFloat(r.humidity);
            const smoke = parseInt(r.smoke_level);
            const light = parseInt(r.light_level);
            const bulb1 = parseInt(r.bulb1_status);
            const bulb2 = parseInt(r.bulb2_status);
            const buzzer = parseInt(r.buzzer_status);
            const emergency = parseInt(r.emergency_flag);

            document.getElementById('tempValue').innerText = isNaN(temperature) ? '--' : temperature.toFixed(1) + '°C';
            document.getElementById('humValue').innerText = isNaN(humidity) ? '--' : humidity.toFixed(1) + '%';
            document.getElementById('smokeValue').innerText = isNaN(smoke) ? '--' : smoke + ' ppm';
            document.getElementById('lightValue').innerText = isNaN(light) ? '--' : light + ' lx';

            document.getElementById('bulb1Status').innerText = bulb1 ? 'ON' : 'OFF';
            document.getElementById('bulb2Status').innerText = bulb2 ? 'ON' : 'OFF';
            document.getElementById('buzzerStatus').innerText = buzzer ? 'ON' : 'OFF';
            document.getElementById('emergencyFlag').innerText = emergency ? 'ACTIVE' : 'CLEAR';

            const ctrlBulb1 = document.getElementById('ctrlBulb1Status');
            const ctrlBulb2 = document.getElementById('ctrlBulb2Status');
            const ctrlBuzzer = document.getElementById('ctrlBuzzerStatus');

            if (ctrlBulb1) ctrlBulb1.innerText = bulb1 ? 'ON' : 'OFF';
            if (ctrlBulb2) ctrlBulb2.innerText = bulb2 ? 'ON' : 'OFF';
            if (ctrlBuzzer) ctrlBuzzer.innerText = buzzer ? 'ON' : 'OFF';

            document.getElementById('lastUpdateTime').innerText = new Date().toLocaleTimeString();
        } else {
            console.log("No reading found");
        }

    } catch (error) {
        console.error('Fetch error:', error);
    }
}
    
    // Manual refresh
    async function manualRefresh() {
        const btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<div class="loading-spinner"></div>';
        await fetchLiveData();
        btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        showToast('Data refreshed', 'success');
    }
    
    // Send command to device
    async function sendCommand(device, command) {
        const btn = event?.target?.closest('button');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="loading-spinner"></div>';
        }
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${device}&command=${command}`
            });
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message || `${device} command sent`, 'success');
                setTimeout(() => fetchLiveData(), 500);
            } else {
                showToast(data.message || 'Command failed', 'error');
            }
        } catch (error) {
            showToast('Network error', 'error');
        }
        
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = command === 'toggle' ? '<i class="fas fa-power-off mr-2"></i> Toggle' : (command === 'on' ? '<i class="fas fa-play mr-2"></i> ON' : '<i class="fas fa-stop mr-2"></i> OFF');
        }
    }
    
    // Reset emergency
    async function resetEmergency() {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=reset_emergency'
            });
            const data = await response.json();
            if (data.success) {
                showToast('Emergency reset successfully', 'success');
                fetchLiveData();
            } else {
                showToast('Reset failed', 'error');
            }
        } catch (error) {
            showToast('Network error', 'error');
        }
    }
    
    // Load history by date
    async function loadHistory() {
        const date = document.getElementById('historyDate').value || new Date().toISOString().split('T')[0];
        try {
            const response = await fetch(`api.php?action=get_history&date=${date}`);
            const data = await response.json();
            const tbody = document.querySelector('#historyTable tbody');
            
            if (data.success && data.readings && data.readings.length > 0) {
                tbody.innerHTML = data.readings.map(r => `
                    <tr class="hover:bg-[var(--hover-bg)]">
                        <td class="px-4 py-3 text-sm">${new Date(r.created_at).toLocaleTimeString()}</td>
                        <td class="px-4 py-3 text-sm">${r.temperature ? r.temperature.toFixed(1) + '°C' : '--'}</td>
                        <td class="px-4 py-3 text-sm">${r.humidity ? r.humidity.toFixed(1) + '%' : '--'}</td>
                        <td class="px-4 py-3 text-sm">${r.smoke_level ? r.smoke_level + ' ppm' : '--'}</td>
                        <td class="px-4 py-3 text-sm">${r.light_level ? r.light_level + ' lx' : '--'}</td>
                        <td class="px-4 py-3 text-sm">${r.emergency_flag ? '<span class="text-red-600 font-bold">ACTIVE</span>' : 'CLEAR'}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-[var(--text-secondary)]">No readings found for this date</td></tr>';
            }
        } catch (error) {
            console.error('Error loading history:', error);
        }
    }
    
    // Load alerts
    async function loadAlerts() {
        try {
            const response = await fetch('api.php?action=get_alerts');
            const data = await response.json();
            const tbody = document.querySelector('#alertsTable tbody');
            
            if (data.success && data.alerts && data.alerts.length > 0) {
                tbody.innerHTML = data.alerts.map(a => `
                    <tr class="hover:bg-[var(--hover-bg)]">
                        <td class="px-6 py-3 text-sm capitalize">${a.alert_type}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-medium ${a.severity === 'critical' ? 'bg-red-100 text-red-800' : (a.severity === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')}">
                                ${a.severity}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm">${a.message}</td>
                        <td class="px-6 py-3 text-sm">${new Date(a.created_at).toLocaleString()}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-12 text-center text-[var(--text-secondary)]">No alerts found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading alerts:', error);
        }
    }
    
    // Save threshold settings
    document.getElementById('thresholdForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new URLSearchParams();
        formData.append('action', 'thresholds');
        formData.append('smoke', document.getElementById('smokeThreshold').value);
        formData.append('light', document.getElementById('lightThreshold').value);
        formData.append('motion', document.getElementById('motionDistance').value);
        formData.append('auto_light', document.getElementById('autoLight').checked ? 1 : 0);
        formData.append('auto_motion', document.getElementById('autoMotion').checked ? 1 : 0);
        
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                showToast('Threshold settings saved', 'success');
            } else {
                showToast(data.message || 'Save failed', 'error');
            }
        } catch (error) {
            showToast('Network error', 'error');
        }
    });
    
    // Save settings (theme, refresh interval)
    function saveSettings() {
        const theme = document.getElementById('themeSelect').value;
        const interval = parseInt(document.getElementById('refreshInterval').value);
        
        document.body.setAttribute('data-theme', theme);
        document.cookie = `theme=${theme}; path=/`;
        
        if (interval !== currentRefreshInterval) {
            currentRefreshInterval = interval;
            if (refreshIntervalId) clearInterval(refreshIntervalId);
            refreshIntervalId = setInterval(fetchLiveData, interval * 1000);
        }
        
        showToast('Settings saved', 'success');
    }
    
    // Update clock
    function updateDateTime() {
        const timeElement = document.getElementById('timeDate');
        if (timeElement) {
            timeElement.textContent = new Date().toLocaleTimeString();
        }
    }
    
    // Chart initialization
    let tempChart, humChart;
    
    function initCharts() {
        const tempCtx = document.getElementById('tempChart')?.getContext('2d');
        const humCtx = document.getElementById('humChart')?.getContext('2d');
        
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
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        }
        
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
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        }
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        initCharts();
        fetchLiveData();
        loadAlerts();
        updateDateTime();
        setInterval(updateDateTime, 1000);
        refreshIntervalId = setInterval(fetchLiveData, 2000);
        document.getElementById('historyDate').value = new Date().toISOString().split('T')[0];
    });
    
    // Expose functions globally
    window.switchTab = switchTab;
    window.sendCommand = sendCommand;
    window.resetEmergency = resetEmergency;
    window.loadHistory = loadHistory;
    window.saveSettings = saveSettings;
    window.manualRefresh = manualRefresh;
</script>

<?php $conn->close(); ?>
</body>
</html>