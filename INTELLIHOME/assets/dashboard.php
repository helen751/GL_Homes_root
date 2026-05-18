<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "home_automation");

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['toggle_mode'])) {
        $conn->query("UPDATE device_status SET auto_motion_enabled = 1 - auto_motion_enabled WHERE device_id = 'home_unit_01'");
    }
    if (isset($_POST['control_device'])) {
        $device = $_POST['device_name'];
        $value = $_POST['device_value'];
        $stmt = $conn->prepare("INSERT INTO device_commands (command_type, command_value, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ss", $device, $value);
        $stmt->execute();
        $stmt->close();
    }
    if (isset($_POST['update_threshold'])) {
        $threshold_type = $_POST['threshold_type'];
        $threshold_value = $_POST['threshold_value'];
        $conn->query("UPDATE device_status SET $threshold_type = $threshold_value WHERE device_id = 'home_unit_01'");
    }
}

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Fetch latest sensor reading
$reading_res = $conn->query("SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1");
$latest = $reading_res->fetch_assoc();

// Fetch device status
$status_res = $conn->query("SELECT * FROM device_status WHERE device_id = 'home_unit_01' LIMIT 1");
$device_status = $status_res->fetch_assoc();
$auto_mode = isset($device_status['auto_motion_enabled']) ? $device_status['auto_motion_enabled'] : 1;

// Safe defaults
$temp = isset($latest['temperature']) ? $latest['temperature'] : "22.5";
$humid = isset($latest['humidity']) ? $latest['humidity'] : "55.0";
$dist = isset($latest['distance']) ? $latest['distance'] : "85";
$light = isset($latest['light_level']) ? $latest['light_level'] : "420";
$smoke_raw = isset($latest['smoke_level']) ? $latest['smoke_level'] : 0;
$smoke_status = $smoke_raw > 400 ? "DANGER" : ($smoke_raw > 200 ? "WARNING" : "SAFE");

$b1 = isset($latest['bulb1_status']) && $latest['bulb1_status'] == 1 ? "ON" : "OFF";
$b2 = isset($latest['bulb2_status']) && $latest['bulb2_status'] == 1 ? "ON" : "OFF";
$buzz = isset($latest['buzzer_status']) && $latest['buzzer_status'] == 1 ? "ON" : "OFF";

// Fetch historical data (last 24 readings)
$history_res = $conn->query("SELECT temperature, humidity, distance, light_level, smoke_level, created_at FROM sensor_readings ORDER BY created_at DESC LIMIT 24");
$history_data = [];
while ($row = $history_res->fetch_assoc()) {
    array_unshift($history_data, $row);
}

// Fetch alerts from alerts_log table
$alerts_res = $conn->query("SELECT * FROM alerts_log WHERE is_acknowledged = 0 ORDER BY created_at DESC LIMIT 5");
$active_alerts = [];
while ($alert = $alerts_res->fetch_assoc()) {
    $active_alerts[] = $alert;
}

// Fetch today's stats using direct queries
$today_stats_res = $conn->query("SELECT COUNT(*) as total_readings, AVG(temperature) as avg_temp, MAX(temperature) as max_temp, MIN(temperature) as min_temp, AVG(humidity) as avg_humidity, MAX(smoke_level) as max_smoke, SUM(emergency_flag) as emergency_count FROM sensor_readings WHERE DATE(created_at) = CURDATE()");
$today_stats = $today_stats_res->fetch_assoc();

// Fetch command history
$cmd_res = $conn->query("SELECT command_type, command_value, status, created_at FROM device_commands ORDER BY created_at DESC LIMIT 5");
$command_history = [];
while ($cmd = $cmd_res->fetch_assoc()) {
    $command_history[] = $cmd;
}

// Get threshold values
$light_threshold = isset($device_status['light_threshold']) ? $device_status['light_threshold'] : 300;
$motion_threshold = isset($device_status['motion_distance']) ? $device_status['motion_distance'] : 50;
$smoke_threshold = isset($device_status['smoke_threshold']) ? $device_status['smoke_threshold'] : 400;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INTELLIHOME | Smart Monitoring Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%); min-height: 100vh; }
        .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 8px 32px rgba(0,0,0,0.05); }
        .sidebar-item { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border-radius: 1rem; }
        .sidebar-item:hover:not(.active) { background: linear-gradient(135deg, #eef2ff, #e0e7ff); transform: translateX(4px); }
        .sidebar-item.active { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; box-shadow: 0 10px 20px -5px rgba(79,70,229,0.3); }
        .metric-card { transition: all 0.3s ease; }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 20px 30px -12px rgba(0,0,0,0.15); }
        .stat-number { font-size: 2rem; font-weight: 800; background: linear-gradient(135deg, #1e293b, #475569); -webkit-background-clip: text; background-clip: text; color: transparent; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }
        .pulse-warning { animation: pulse 1.5s ease-in-out infinite; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #818cf8; border-radius: 10px; }
    </style>
</head>
<body class="p-4 md:p-6">

<div class="max-w-[1600px] mx-auto">
    <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- SIDEBAR -->
        <aside class="lg:w-80 w-full glass-card rounded-3xl shadow-xl p-5 h-fit lg:sticky lg:top-5">
            <div class="flex items-center gap-3 mb-8 px-2 pb-4 border-b border-slate-200">
                <div class="h-12 w-12 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-home text-white text-2xl"></i>
                </div>
                <div>
                    <span class="text-2xl font-extrabold bg-gradient-to-r from-indigo-800 to-slate-700 bg-clip-text text-transparent">IntelliHome</span>
                    <p class="text-xs text-slate-400">Smart Living OS</p>
                </div>
            </div>
            
            <nav class="space-y-1.5">
                <a href="?tab=dashboard" class="sidebar-item <?php echo $current_tab == 'dashboard' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-chart-line w-5"></i> <span class="font-medium">Dashboard</span>
                </a>
                <a href="?tab=devices" class="sidebar-item <?php echo $current_tab == 'devices' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-microchip w-5"></i> <span>Devices</span>
                </a>
                <a href="?tab=lights" class="sidebar-item <?php echo $current_tab == 'lights' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-lightbulb w-5"></i> <span>Lights</span>
                </a>
                <a href="?tab=climate" class="sidebar-item <?php echo $current_tab == 'climate' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-thermometer-half w-5"></i> <span>Climate</span>
                </a>
                <a href="?tab=electricity" class="sidebar-item <?php echo $current_tab == 'electricity' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-bolt w-5"></i> <span>Electricity</span>
                </a>
                <a href="?tab=security" class="sidebar-item <?php echo $current_tab == 'security' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-shield-alt w-5"></i> <span>Security</span>
                </a>
                <a href="?tab=analytics" class="sidebar-item <?php echo $current_tab == 'analytics' ? 'active' : ''; ?> flex items-center gap-4 px-4 py-3 text-slate-700">
                    <i class="fas fa-chart-pie w-5"></i> <span>Analytics</span>
                </a>
                <div class="pt-6 mt-4 border-t border-slate-200">
                    <a href="?tab=settings" class="sidebar-item flex items-center gap-4 px-4 py-3 text-slate-700">
                        <i class="fas fa-sliders-h w-5"></i> <span>Settings</span>
                    </a>
                    <a href="logout.php" class="flex items-center gap-4 px-4 py-3 rounded-2xl text-red-600 hover:bg-red-50 transition-all mt-1">
                        <i class="fas fa-sign-out-alt w-5"></i> <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="flex-1 space-y-6 fade-in">
            
            <!-- Header -->
            <div class="glass-card rounded-2xl px-6 py-4 flex justify-between items-center shadow-sm">
                <div class="flex items-center gap-3 text-slate-600 text-sm">
                    <i class="fas fa-sync-alt text-indigo-500 animate-spin" style="animation: spin 2s linear infinite;"></i>
                    <span>Live Telemetry • <strong class="text-slate-800"><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <span class="text-xs text-slate-400 ml-2">Updated: <?php echo isset($latest['created_at']) ? date('H:i:s', strtotime($latest['created_at'])) : '--:--'; ?></span>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <i class="far fa-bell text-slate-500 text-lg"></i>
                        <?php if(count($active_alerts) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[9px] rounded-full w-4 h-4 flex items-center justify-center"><?php echo count($active_alerts); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold shadow-md">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                </div>
            </div>

            <!-- DASHBOARD TAB -->
            <?php if($current_tab == 'dashboard'): ?>
            <!-- Metrics Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                <div class="glass-card rounded-2xl p-5 metric-card">
                    <div class="flex justify-between items-start">
                        <i class="fas fa-temperature-high text-3xl text-orange-500"></i>
                        <span class="text-xs px-2 py-1 rounded-full bg-orange-100 text-orange-600">Sensor</span>
                    </div>
                    <p class="stat-number mt-3"><?php echo $temp; ?>°C</p>
                    <p class="text-sm text-slate-500 mt-1">Temperature</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2"><div class="bg-orange-500 h-1.5 rounded-full" style="width: <?php echo min(100, ($temp/45)*100); ?>%"></div></div>
                </div>
                <div class="glass-card rounded-2xl p-5 metric-card">
                    <i class="fas fa-tint text-3xl text-blue-500"></i>
                    <p class="stat-number mt-3"><?php echo $humid; ?>%</p>
                    <p class="text-sm text-slate-500">Humidity</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2"><div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo min(100, $humid); ?>%"></div></div>
                </div>
                <div class="glass-card rounded-2xl p-5 metric-card">
                    <i class="fas fa-ruler-combined text-3xl text-emerald-500"></i>
                    <p class="stat-number mt-3"><?php echo $dist; ?> cm</p>
                    <p class="text-sm text-slate-500">Motion Distance</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2"><div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?php echo min(100, ($dist/200)*100); ?>%"></div></div>
                </div>
                <div class="glass-card rounded-2xl p-5 metric-card">
                    <i class="fas fa-sun text-3xl text-yellow-500"></i>
                    <p class="stat-number mt-3"><?php echo $light; ?> lux</p>
                    <p class="text-sm text-slate-500">Light Level (LDR)</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2"><div class="bg-yellow-500 h-1.5 rounded-full" style="width: <?php echo min(100, ($light/1000)*100); ?>%"></div></div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass-card rounded-2xl p-5">
                    <h3 class="font-bold text-slate-700 mb-4"><i class="fas fa-chart-line text-indigo-500 mr-2"></i>Temperature & Humidity Trend</h3>
                    <canvas id="tempHumChart" height="200"></canvas>
                </div>
                <div class="glass-card rounded-2xl p-5">
                    <h3 class="font-bold text-slate-700 mb-4"><i class="fas fa-chart-area text-indigo-500 mr-2"></i>Distance & Light Level</h3>
                    <canvas id="distLightChart" height="200"></canvas>
                </div>
            </div>

            <!-- Alerts & System Status -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 glass-card rounded-2xl p-5">
                    <h3 class="font-bold text-slate-700 mb-3"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>Active Alerts</h3>
                    <?php if(count($active_alerts) > 0): ?>
                        <div class="space-y-2">
                            <?php foreach($active_alerts as $alert): ?>
                            <div class="flex justify-between items-center p-3 bg-red-50 rounded-xl border-l-4 border-red-500">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-bell text-red-500"></i>
                                    <div>
                                        <p class="text-sm font-semibold text-red-800"><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></p>
                                        <p class="text-xs text-red-600"><?php echo $alert['message']; ?></p>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <button type="submit" name="acknowledge_alert" class="text-xs bg-white px-3 py-1.5 rounded-full text-slate-600 shadow-sm hover:shadow transition">Acknowledge</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8"><i class="fas fa-check-circle text-green-500 text-3xl mb-2 block"></i><p class="text-slate-500">All systems operational</p></div>
                    <?php endif; ?>
                </div>
                <div class="glass-card rounded-2xl p-5">
                    <h3 class="font-bold text-slate-700 mb-3"><i class="fas fa-microchip mr-2"></i>System Mode</h3>
                    <form method="POST">
                        <input type="hidden" name="toggle_mode" value="1">
                        <div class="flex justify-between items-center p-3 rounded-xl <?php echo $auto_mode ? 'bg-indigo-50' : 'bg-amber-50'; ?>">
                            <span class="text-sm font-semibold"><?php echo $auto_mode ? '🤖 Auto Mode Active' : '🕹️ Manual Override'; ?></span>
                            <button type="submit" class="text-xs px-3 py-1.5 rounded-full font-semibold <?php echo $auto_mode ? 'bg-indigo-600 text-white' : 'bg-amber-600 text-white'; ?>">
                                Switch to <?php echo $auto_mode ? 'Manual' : 'Auto'; ?>
                            </button>
                        </div>
                    </form>
                    <div class="mt-4 space-y-2 text-xs text-slate-600">
                        <div class="flex justify-between"><span>🔥 Smoke Level:</span><span class="font-bold <?php echo $smoke_raw > 300 ? 'text-red-600' : 'text-green-600'; ?>"><?php echo $smoke_raw; ?> ppm</span></div>
                        <div class="flex justify-between"><span>💡 Light Threshold:</span><span><?php echo $light_threshold; ?> lux</span></div>
                        <div class="flex justify-between"><span>📏 Motion Trigger:</span><span><?php echo $motion_threshold; ?> cm</span></div>
                        <div class="flex justify-between"><span>⚠️ Smoke Threshold:</span><span><?php echo $smoke_threshold; ?> ppm</span></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- DEVICES TAB -->
            <?php if($current_tab == 'devices'): ?>
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-xl font-bold text-slate-800 mb-6"><i class="fas fa-microchip text-indigo-500 mr-3"></i>Connected Devices</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-slate-50 rounded-xl p-5 flex justify-between items-center">
                        <div><i class="fas fa-lightbulb text-3xl text-yellow-500"></i><p class="font-bold mt-2">Bulb 1 (Living Room)</p><p class="text-sm">Status: <span class="font-semibold"><?php echo $b1; ?></span></p></div>
                        <form method="POST"><input type="hidden" name="control_device" value="1"><input type="hidden" name="device_name" value="bulb1"><input type="hidden" name="device_value" value="<?php echo ($b1 == "ON") ? "OFF" : "ON"; ?>"><button type="submit" <?php echo $auto_mode ? 'disabled' : ''; ?> class="px-5 py-2 rounded-full <?php echo $b1=='ON' ? 'bg-green-500 text-white' : 'bg-gray-400 text-white'; ?> <?php echo $auto_mode ? 'opacity-50 cursor-not-allowed' : ''; ?>">Toggle</button></form>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-5 flex justify-between items-center">
                        <div><i class="fas fa-lightbulb text-3xl text-blue-300"></i><p class="font-bold mt-2">Bulb 2 (Kitchen)</p><p class="text-sm">Status: <span class="font-semibold"><?php echo $b2; ?></span></p></div>
                        <form method="POST"><input type="hidden" name="control_device" value="1"><input type="hidden" name="device_name" value="bulb2"><input type="hidden" name="device_value" value="<?php echo ($b2 == "ON") ? "OFF" : "ON"; ?>"><button type="submit" <?php echo $auto_mode ? 'disabled' : ''; ?> class="px-5 py-2 rounded-full <?php echo $b2=='ON' ? 'bg-green-500 text-white' : 'bg-gray-400 text-white'; ?> <?php echo $auto_mode ? 'opacity-50 cursor-not-allowed' : ''; ?>">Toggle</button></form>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-5 flex justify-between items-center">
                        <div><i class="fas fa-bell text-3xl text-red-400"></i><p class="font-bold mt-2">Buzzer Alarm</p><p class="text-sm">Status: <span class="font-semibold"><?php echo $buzz; ?></span></p></div>
                        <form method="POST"><input type="hidden" name="control_device" value="1"><input type="hidden" name="device_name" value="buzzer"><input type="hidden" name="device_value" value="<?php echo ($buzz == "ON") ? "OFF" : "ON"; ?>"><button type="submit" <?php echo $auto_mode ? 'disabled' : ''; ?> class="px-5 py-2 rounded-full <?php echo $buzz=='ON' ? 'bg-red-500 text-white' : 'bg-gray-400 text-white'; ?> <?php echo $auto_mode ? 'opacity-50 cursor-not-allowed' : ''; ?>"><?php echo $buzz=='ON' ? 'Silence' : 'Trigger'; ?></button></form>
                    </div>
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-5">
                        <i class="fas fa-microchip text-2xl text-indigo-600"></i>
                        <p class="font-bold mt-2">NodeMCU Bridge</p>
                        <p class="text-sm text-green-600"><i class="fas fa-circle text-[8px] align-middle mr-1"></i> Online & Syncing</p>
                        <p class="text-xs text-slate-400 mt-1">Last command: <?php echo isset($command_history[0]['created_at']) ? date('H:i:s', strtotime($command_history[0]['created_at'])) : '--'; ?></p>
                    </div>
                </div>
                <?php if(count($command_history) > 0): ?>
                <div class="mt-6 p-4 bg-slate-50 rounded-xl"><p class="text-xs text-slate-500 font-semibold">Recent Commands:</p><div class="flex gap-3 mt-2 text-xs"><?php foreach(array_slice($command_history, 0, 3) as $cmd): ?><span class="bg-white px-2 py-1 rounded-full"><?php echo $cmd['command_type']; ?>: <?php echo $cmd['command_value']; ?></span><?php endforeach; ?></div></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- CLIMATE TAB -->
            <?php if($current_tab == 'climate'): ?>
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-2xl font-bold"><i class="fas fa-thermometer-half text-orange-500 mr-3"></i>Climate Analytics</h2>
                <div class="grid md:grid-cols-2 gap-6 mt-6">
                    <div><canvas id="tempDetailChart" height="250"></canvas></div>
                    <div><canvas id="humidityDetailChart" height="250"></canvas></div>
                </div>
                <div class="mt-6 p-5 bg-gradient-to-r from-orange-50 to-blue-50 rounded-xl">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div><p class="text-xs text-slate-500">Avg Today</p><p class="text-xl font-bold"><?php echo round($today_stats['avg_temp'] ?? $temp, 1); ?>°C</p></div>
                        <div><p class="text-xs text-slate-500">Max Today</p><p class="text-xl font-bold"><?php echo round($today_stats['max_temp'] ?? $temp, 1); ?>°C</p></div>
                        <div><p class="text-xs text-slate-500">Min Today</p><p class="text-xl font-bold"><?php echo round($today_stats['min_temp'] ?? $temp, 1); ?>°C</p></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- SECURITY TAB -->
            <?php if($current_tab == 'security'): ?>
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-2xl font-bold mb-4"><i class="fas fa-shield-alt text-green-600 mr-3"></i>Security System</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="p-5 rounded-xl <?php echo $dist < $motion_threshold ? 'bg-red-100' : 'bg-green-100'; ?>">
                        <i class="fas fa-person-walking-arrow-right text-3xl mb-2"></i>
                        <p class="font-bold">Motion Detection</p>
                        <p class="text-2xl font-mono"><?php echo $dist; ?> cm</p>
                        <p class="text-sm">Trigger threshold: <?php echo $motion_threshold; ?> cm</p>
                        <p class="text-xs mt-2 <?php echo $dist < $motion_threshold ? 'text-red-600 font-bold' : 'text-green-600'; ?>"><?php echo $dist < $motion_threshold ? '⚠️ MOTION DETECTED!' : '✓ All clear'; ?></p>
                    </div>
                    <div class="p-5 rounded-xl <?php echo $smoke_raw > $smoke_threshold ? 'bg-red-100' : 'bg-green-100'; ?>">
                        <i class="fas fa-smog text-3xl mb-2"></i>
                        <p class="font-bold">Smoke / Gas Sensor</p>
                        <p class="text-2xl font-mono"><?php echo $smoke_raw; ?> ppm</p>
                        <p class="text-sm">Threshold: <?php echo $smoke_threshold; ?> ppm</p>
                        <p class="text-xs mt-2 <?php echo $smoke_raw > $smoke_threshold ? 'text-red-600 font-bold animate-pulse' : 'text-green-600'; ?>"><?php echo $smoke_raw > $smoke_threshold ? '⚠️ CRITICAL: SMOKE DETECTED!' : '✓ Air quality normal'; ?></p>
                    </div>
                </div>
                <?php if($smoke_raw > $smoke_threshold || $dist < $motion_threshold): ?>
                <div class="mt-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg"><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Security breach detected! Immediate attention required.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ANALYTICS TAB -->
            <?php if($current_tab == 'analytics'): ?>
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-2xl font-bold mb-6"><i class="fas fa-chart-pie text-purple-500 mr-3"></i>System Intelligence</h2>
                <div class="grid md:grid-cols-3 gap-5 mb-6">
                    <div class="bg-indigo-50 p-5 rounded-2xl text-center"><i class="fas fa-database text-3xl text-indigo-600"></i><p class="text-2xl font-bold mt-2"><?php echo number_format($today_stats['total_readings'] ?? 0); ?></p><p>Readings Today</p></div>
                    <div class="bg-red-50 p-5 rounded-2xl text-center"><i class="fas fa-exclamation-circle text-3xl text-red-500"></i><p class="text-2xl font-bold mt-2"><?php echo $today_stats['emergency_count'] ?? 0; ?></p><p>Emergency Events</p></div>
                    <div class="bg-green-50 p-5 rounded-2xl text-center"><i class="fas fa-chart-line text-3xl text-green-500"></i><p class="text-2xl font-bold mt-2"><?php echo round($today_stats['avg_humidity'] ?? $humid, 1); ?>%</p><p>Avg Humidity</p></div>
                </div>
                <div class="mt-4"><canvas id="smokeChart" height="100"></canvas></div>
                <div class="mt-6 p-4 bg-slate-50 rounded-xl"><p class="text-sm"><i class="fas fa-lightbulb text-yellow-500 mr-2"></i>Recommendation: <?php echo $light < $light_threshold ? 'Low light detected. Consider enabling auto-lighting.' : 'Light levels are optimal.'; ?></p></div>
            </div>
            <?php endif; ?>

            <!-- SETTINGS TAB -->
            <?php if($current_tab == 'settings'): ?>
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-2xl font-bold mb-6"><i class="fas fa-sliders-h text-slate-600 mr-3"></i>Threshold Configuration</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="p-5 bg-slate-50 rounded-xl"><label class="font-bold block mb-2">💡 Light Threshold (lux)</label><p class="text-sm text-slate-500 mb-2">Current: <?php echo $light_threshold; ?> lux</p><form method="POST"><input type="hidden" name="threshold_type" value="light_threshold"><input type="number" name="threshold_value" class="border rounded-lg px-3 py-1 w-32" value="<?php echo $light_threshold; ?>"><button type="submit" name="update_threshold" class="ml-2 bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm">Update</button></form></div>
                    <div class="p-5 bg-slate-50 rounded-xl"><label class="font-bold block mb-2">📏 Motion Distance (cm)</label><p class="text-sm text-slate-500 mb-2">Current: <?php echo $motion_threshold; ?> cm</p><form method="POST"><input type="hidden" name="threshold_type" value="motion_distance"><input type="number" name="threshold_value" class="border rounded-lg px-3 py-1 w-32" value="<?php echo $motion_threshold; ?>"><button type="submit" name="update_threshold" class="ml-2 bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm">Update</button></form></div>
                    <div class="p-5 bg-slate-50 rounded-xl"><label class="font-bold block mb-2">⚠️ Smoke Threshold (ppm)</label><p class="text-sm text-slate-500 mb-2">Current: <?php echo $smoke_threshold; ?> ppm</p><form method="POST"><input type="hidden" name="threshold_type" value="smoke_threshold"><input type="number" name="threshold_value" class="border rounded-lg px-3 py-1 w-32" value="<?php echo $smoke_threshold; ?>"><button type="submit" name="update_threshold" class="ml-2 bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm">Update</button></form></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const history = <?php echo json_encode($history_data); ?>;
const labels = history.map((_, i) => i+1);
const temps = history.map(h => h.temperature);
const hums = history.map(h => h.humidity);
const dists = history.map(h => h.distance);
const lights = history.map(h => h.light_level);
const smokes = history.map(h => h.smoke_level);

if(document.getElementById('tempHumChart')) new Chart(document.getElementById('tempHumChart'), { type: 'line', data: { labels: labels, datasets: [{ label: 'Temperature (°C)', data: temps, borderColor: '#f97316', tension: 0.3, fill: false },{ label: 'Humidity (%)', data: hums, borderColor: '#3b82f6', tension: 0.3, fill: false }] }, options: { responsive: true, maintainAspectRatio: true } });
if(document.getElementById('distLightChart')) new Chart(document.getElementById('distLightChart'), { type: 'line', data: { labels: labels, datasets: [{ label: 'Distance (cm)', data: dists, borderColor: '#10b981', tension: 0.3 },{ label: 'Light Level (lux)', data: lights, borderColor: '#eab308', tension: 0.3 }] }, options: { responsive: true } });
if(document.getElementById('tempDetailChart')) new Chart(document.getElementById('tempDetailChart'), { type: 'line', data: { labels: labels, datasets: [{ label: 'Temperature Trend', data: temps, borderColor: '#f97316', fill: true, backgroundColor: 'rgba(249,115,22,0.1)' }] } });
if(document.getElementById('humidityDetailChart')) new Chart(document.getElementById('humidityDetailChart'), { type: 'line', data: { labels: labels, datasets: [{ label: 'Humidity Trend', data: hums, borderColor: '#3b82f6', fill: true }] } });
if(document.getElementById('smokeChart')) new Chart(document.getElementById('smokeChart'), { type: 'bar', data: { labels: labels.slice(-10), datasets: [{ label: 'Smoke Level (ppm)', data: smokes.slice(-10), backgroundColor: '#ef4444', borderRadius: 6 }] } });

setTimeout(() => location.reload(), 8000);
</script>
</body>
</html>