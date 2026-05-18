<?php
require_once 'config.php';
startSession();
requireAuth();

$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Automation IoT - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
                        dark: { 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
                        emergency: { 50: '#fef2f2', 100: '#fee2e2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-dark-950 text-slate-200 font-sans antialiased min-h-screen">
    <!-- Emergency Overlay -->
    <div id="emergencyOverlay" class="fixed inset-0 z-[9999] bg-emergency-600/90 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="text-center animate-pulse">
            <i class="fas fa-radiation text-6xl text-white mb-4"></i>
            <h1 class="text-4xl font-bold text-white mb-2">EMERGENCY DETECTED</h1>
            <p class="text-xl text-white/90 mb-6">Smoke detected! System shutdown activated.</p>
            <div class="bg-white/10 rounded-2xl p-6 backdrop-blur-md border border-white/20 max-w-md mx-auto">
                <p class="text-white/80 mb-4">All electrical devices have been shut down for safety.</p>
                <button onclick="acknowledgeEmergency()" 
                        class="px-8 py-3 bg-white text-emergency-700 font-bold rounded-xl hover:bg-slate-100 transition-all shadow-lg">
                    <i class="fas fa-check-circle mr-2"></i> ACKNOWLEDGE ALARM
                </button>
                <p class="text-xs text-white/60 mt-3">This silences the buzzer but keeps system halted until smoke clears</p>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-dark-900/95 border-r border-slate-800/50 backdrop-blur-xl z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-xl bg-primary-600/20 border border-primary-500/30 flex items-center justify-center">
                    <i class="fas fa-home text-primary-400"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-sm">Smart Home</h2>
                    <p class="text-xs text-slate-500">IoT Control</p>
                </div>
            </div>

            <nav class="space-y-1">
                <a href="#" class="flex items-center gap-3 px-4 py-3 bg-primary-600/10 border border-primary-500/20 rounded-xl text-primary-400 font-medium">
                    <i class="fas fa-chart-line w-5"></i> Dashboard
                </a>
                <a href="#" onclick="showSection('analytics')" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-xl transition-all">
                    <i class="fas fa-chart-bar w-5"></i> Analytics
                </a>
                <a href="#" onclick="showSection('alerts')" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-xl transition-all">
                    <i class="fas fa-bell w-5"></i> Alerts
                    <span id="alertBadge" class="ml-auto bg-emergency-600 text-white text-xs px-2 py-0.5 rounded-full hidden">0</span>
                </a>
                <a href="#" onclick="showSection('settings')" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-xl transition-all">
                    <i class="fas fa-cog w-5"></i> Settings
                </a>
            </nav>
        </div>

        <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-slate-800/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center">
                    <i class="fas fa-user text-slate-400"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-xs text-slate-500 capitalize"><?= $userRole ?></p>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-red-400 transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen">
        <!-- Top Header -->
        <header class="sticky top-0 z-30 bg-dark-950/80 backdrop-blur-xl border-b border-slate-800/50">
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold text-white">Dashboard</h1>
                        <p class="text-xs text-slate-500">Real-time monitoring & control</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Connection Status -->
                    <div id="connectionStatus" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Online</span>
                    </div>

                    <!-- Refresh Indicator -->
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <i id="refreshIcon" class="fas fa-sync-alt"></i>
                        <span id="lastUpdate">Just now</span>
                    </div>

                    <!-- Emergency Indicator -->
                    <div id="headerEmergency" class="hidden px-3 py-1.5 rounded-full bg-emergency-600 text-white text-xs font-bold animate-pulse">
                        <i class="fas fa-exclamation-triangle mr-1"></i> EMERGENCY
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-5 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
                            <i class="fas fa-thermometer-half text-orange-400"></i>
                        </div>
                        <span class="text-xs text-slate-500">Live</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="statTemp">--°C</p>
                    <p class="text-xs text-slate-400 mt-1">Temperature</p>
                    <div class="mt-2 h-1 bg-slate-800 rounded-full overflow-hidden">
                        <div id="barTemp" class="h-full bg-orange-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-5 border-l-4 border-cyan-500">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center">
                            <i class="fas fa-tint text-cyan-400"></i>
                        </div>
                        <span class="text-xs text-slate-500">Live</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="statHumidity">--%</p>
                    <p class="text-xs text-slate-400 mt-1">Humidity</p>
                    <div class="mt-2 h-1 bg-slate-800 rounded-full overflow-hidden">
                        <div id="barHumidity" class="h-full bg-cyan-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/10 flex items-center justify-center">
                            <i class="fas fa-sun text-yellow-400"></i>
                        </div>
                        <span class="text-xs text-slate-500">Live</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="statLight">--</p>
                    <p class="text-xs text-slate-400 mt-1">Light Level</p>
                    <div class="mt-2 h-1 bg-slate-800 rounded-full overflow-hidden">
                        <div id="barLight" class="h-full bg-yellow-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-5 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                            <i class="fas fa-ruler-horizontal text-purple-400"></i>
                        </div>
                        <span class="text-xs text-slate-500">Live</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="statDistance">--cm</p>
                    <p class="text-xs text-slate-400 mt-1">Distance</p>
                    <div class="mt-2 h-1 bg-slate-800 rounded-full overflow-hidden">
                        <div id="barDistance" class="h-full bg-purple-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <!-- Smoke & Emergency Card -->
            <div id="smokeCard" class="glass-card rounded-2xl p-6 border border-slate-800/50">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center" id="smokeIconBg">
                            <i class="fas fa-smog text-xl text-slate-400" id="smokeIcon"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-white">Smoke & Gas Detection</h3>
                            <p class="text-xs text-slate-500">MQ-2 Sensor Status</p>
                        </div>
                    </div>
                    <div id="smokeBadge" class="px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold">
                        SAFE
                    </div>
                </div>

                <div class="flex items-end gap-4 mb-4">
                    <div>
                        <p class="text-4xl font-bold text-white" id="statSmoke">--</p>
                        <p class="text-xs text-slate-500">PPM Level</p>
                    </div>
                    <div class="flex-1">
                        <div class="h-4 bg-slate-800 rounded-full overflow-hidden relative">
                            <div id="barSmoke" class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                            <!-- Threshold marker -->
                            <div class="absolute top-0 bottom-0 w-0.5 bg-red-500" style="left: 40%" title="Danger Threshold"></div>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500 mt-1">
                            <span>0</span>
                            <span class="text-red-400">Danger: 400+</span>
                            <span>1023</span>
                        </div>
                    </div>
                </div>

                <div id="emergencyActions" class="hidden">
                    <div class="bg-emergency-500/10 border border-emergency-500/30 rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-emergency-500 text-xl animate-bounce"></i>
                            <div>
                                <p class="text-emergency-400 font-bold">EMERGENCY MODE ACTIVE</p>
                                <p class="text-xs text-emergency-300/70">System halted. Buzzer active.</p>
                            </div>
                        </div>
                        <button onclick="acknowledgeEmergency()" 
                                class="px-4 py-2 bg-emergency-600 hover:bg-emergency-500 text-white rounded-lg text-sm font-medium transition-all">
                            <i class="fas fa-volume-mute mr-1"></i> Silence Alarm
                        </button>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass-card rounded-2xl p-6 border border-slate-800/50">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-white">Temperature & Humidity History</h3>
                        <div class="flex gap-1">
                            <button onclick="setChartRange('24h')" class="chart-range-btn px-3 py-1 text-xs rounded-lg bg-primary-600 text-white" data-range="24h">24H</button>
                            <button onclick="setChartRange('7d')" class="chart-range-btn px-3 py-1 text-xs rounded-lg bg-slate-800 text-slate-400 hover:text-white" data-range="7d">7D</button>
                            <button onclick="setChartRange('30d')" class="chart-range-btn px-3 py-1 text-xs rounded-lg bg-slate-800 text-slate-400 hover:text-white" data-range="30d">30D</button>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-6 border border-slate-800/50">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-white">Smoke & Light Levels</h3>
                        <span class="text-xs text-slate-500">Real-time</span>
                    </div>
                    <div class="h-64">
                        <canvas id="secondaryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="glass-card rounded-2xl p-6 border border-slate-800/50">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-600/10 flex items-center justify-center">
                            <i class="fas fa-sliders-h text-primary-400"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-white">Device Control Panel</h3>
                            <p class="text-xs text-slate-500">Manual override switches</p>
                        </div>
                    </div>
                    <div id="controlLock" class="hidden px-3 py-1 rounded-full bg-emergency-600/20 border border-emergency-600/40 text-emergency-400 text-xs font-bold">
                        <i class="fas fa-lock mr-1"></i> LOCKED (Emergency)
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Bulb 1 -->
                    <div class="bg-dark-900/50 rounded-xl p-5 border border-slate-800/50 transition-all hover:border-slate-700/50" id="controlBulb1">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-yellow-500/10 flex items-center justify-center" id="iconBulb1">
                                    <i class="fas fa-lightbulb text-yellow-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Bulb 1</p>
                                    <p class="text-xs text-slate-500">Main Light</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="switchBulb1" class="sr-only peer" onchange="toggleDevice('bulb1', this.checked)">
                                <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-slate-500" id="statusBulb1">Auto-controlled by LDR</p>
                    </div>

                    <!-- Bulb 2 -->
                    <div class="bg-dark-900/50 rounded-xl p-5 border border-slate-800/50 transition-all hover:border-slate-700/50" id="controlBulb2">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center" id="iconBulb2">
                                    <i class="fas fa-lightbulb text-blue-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Bulb 2</p>
                                    <p class="text-xs text-slate-500">Security Light</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="switchBulb2" class="sr-only peer" onchange="toggleDevice('bulb2', this.checked)">
                                <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-slate-500" id="statusBulb2">Auto-controlled by Motion</p>
                    </div>

                    <!-- Buzzer -->
                    <div class="bg-dark-900/50 rounded-xl p-5 border border-slate-800/50 transition-all hover:border-slate-700/50" id="controlBuzzer">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center" id="iconBuzzer">
                                    <i class="fas fa-bell text-red-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Buzzer</p>
                                    <p class="text-xs text-slate-500">Alarm System</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="switchBuzzer" class="sr-only peer" onchange="toggleDevice('buzzer', this.checked)">
                                <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>
                        <p class="text-xs text-slate-500" id="statusBuzzer">Auto-triggered by smoke</p>
                    </div>
                </div>
            </div>

            <!-- Alerts Log -->
            <div class="glass-card rounded-2xl p-6 border border-slate-800/50">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-white">Recent Alerts</h3>
                    <button onclick="loadAlerts()" class="text-xs text-primary-400 hover:text-primary-300">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b border-slate-800">
                                <th class="pb-3 font-medium">Type</th>
                                <th class="pb-3 font-medium">Message</th>
                                <th class="pb-3 font-medium">Value</th>
                                <th class="pb-3 font-medium">Time</th>
                                <th class="pb-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody id="alertsTableBody" class="text-slate-300">
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">Loading alerts...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-xs text-slate-600 pt-4">
                <p>Home Automation IoT System v2.0 | Global Real-Time Monitoring</p>
                <p class="mt-1">Last sync: <span id="footerTime">--</span></p>
            </div>
        </div>
    </main>

    <script src="assets/dashboard.js"></script>
</body>
</html>