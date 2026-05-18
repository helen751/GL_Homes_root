/**
 * HOME AUTOMATION IoT - DASHBOARD CONTROLLER
 * Real-time monitoring, charts, emergency handling
 */

// Configuration
const CONFIG = {
    refreshInterval: 5000,      // Normal: 5 seconds
    emergencyInterval: 2000,    // Emergency: 2 seconds
    apiBase: 'api/',
    deviceId: 'home_unit_01',
    chartColors: {
        temp: '#f97316',        // orange-500
        humidity: '#06b6d4',    // cyan-500
        light: '#eab308',       // yellow-500
        smoke: '#ef4444',       // red-500
        distance: '#a855f7'     // purple-500
    }
};

// State
let state = {
    isEmergency: false,
    refreshTimer: null,
    charts: {},
    currentRange: '24h',
    lastData: null
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    loadDashboardData();
    startAutoRefresh();
});

// ==================== AUTO REFRESH ====================
function startAutoRefresh() {
    if (state.refreshTimer) clearInterval(state.refreshTimer);
    const interval = state.isEmergency ? CONFIG.emergencyInterval : CONFIG.refreshInterval;
    state.refreshTimer = setInterval(loadDashboardData, interval);
}

function setRefreshInterval(emergency) {
    if (state.isEmergency !== emergency) {
        state.isEmergency = emergency;
        startAutoRefresh();
    }
}

// ==================== DATA LOADING ====================
async function loadDashboardData() {
    try {
        const response = await fetch(`${CONFIG.apiBase}get_data.php?device_id=${CONFIG.deviceId}&range=${state.currentRange}`);
        const data = await response.json();

        if (data.success) {
            updateDashboard(data);
            updateConnectionStatus(true);
        } else {
            console.error('API Error:', data.error);
            updateConnectionStatus(false);
        }
    } catch (error) {
        console.error('Fetch Error:', error);
        updateConnectionStatus(false);
    }
}

function updateConnectionStatus(online) {
    const el = document.getElementById('connectionStatus');
    if (online) {
        el.className = 'flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium';
        el.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span>Online</span>';
    } else {
        el.className = 'flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium';
        el.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-500"></span><span>Offline</span>';
    }

    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
    document.getElementById('footerTime').textContent = new Date().toLocaleString();

    // Spin refresh icon
    const icon = document.getElementById('refreshIcon');
    icon.classList.add('fa-spin');
    setTimeout(() => icon.classList.remove('fa-spin'), 1000);
}

// ==================== UPDATE DASHBOARD ====================
function updateDashboard(data) {
    const latest = data.latest;
    const status = data.status;
    const emergency = data.active_emergency;

    if (!latest) return;

    // Update stats
    updateStat('Temp', latest.temperature, '°C', latest.temperature, 50);
    updateStat('Humidity', latest.humidity, '%', latest.humidity, 100);
    updateStat('Light', latest.light_level, '', latest.light_level, 1023);
    updateStat('Distance', latest.distance, 'cm', latest.distance, 400);
    updateStat('Smoke', latest.smoke_level, ' PPM', latest.smoke_level, 1023);

    // Smoke card styling
    const smokeCard = document.getElementById('smokeCard');
    const smokeBadge = document.getElementById('smokeBadge');
    const smokeIcon = document.getElementById('smokeIcon');
    const smokeIconBg = document.getElementById('smokeIconBg');
    const barSmoke = document.getElementById('barSmoke');

    if (latest.smoke_level > 400) {
        smokeCard.classList.add('border-emergency-500/50');
        smokeBadge.className = 'px-3 py-1 rounded-full bg-emergency-600 text-white text-xs font-bold animate-pulse';
        smokeBadge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> DANGER';
        smokeIcon.className = 'fas fa-smog text-xl text-emergency-500 animate-pulse';
        smokeIconBg.className = 'w-12 h-12 rounded-xl bg-emergency-500/20 flex items-center justify-center';
        barSmoke.className = 'h-full bg-emergency-500 rounded-full transition-all duration-1000 animate-pulse';
    } else if (latest.smoke_level > 200) {
        smokeCard.classList.remove('border-emergency-500/50');
        smokeBadge.className = 'px-3 py-1 rounded-full bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 text-xs font-bold';
        smokeBadge.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> WARNING';
        smokeIcon.className = 'fas fa-smog text-xl text-yellow-400';
        smokeIconBg.className = 'w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center';
        barSmoke.className = 'h-full bg-yellow-500 rounded-full transition-all duration-1000';
    } else {
        smokeCard.classList.remove('border-emergency-500/50');
        smokeBadge.className = 'px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold';
        smokeBadge.innerHTML = 'SAFE';
        smokeIcon.className = 'fas fa-smog text-xl text-emerald-400';
        smokeIconBg.className = 'w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center';
        barSmoke.className = 'h-full bg-emerald-500 rounded-full transition-all duration-1000';
    }

    // Emergency handling
    const isEmergency = latest.emergency_flag == 1;
    const emergencyOverlay = document.getElementById('emergencyOverlay');
    const emergencyActions = document.getElementById('emergencyActions');
    const headerEmergency = document.getElementById('headerEmergency');
    const controlLock = document.getElementById('controlLock');

    if (isEmergency) {
        emergencyOverlay.classList.remove('hidden');
        emergencyActions.classList.remove('hidden');
        headerEmergency.classList.remove('hidden');
        controlLock.classList.remove('hidden');

        // Lock controls
        document.getElementById('switchBulb1').disabled = true;
        document.getElementById('switchBulb2').disabled = true;
        document.getElementById('switchBuzzer').disabled = true;

        // Visual feedback on control cards
        ['controlBulb1', 'controlBulb2'].forEach(id => {
            document.getElementById(id).classList.add('opacity-50', 'pointer-events-none');
        });

        setRefreshInterval(true);
    } else {
        emergencyOverlay.classList.add('hidden');
        emergencyActions.classList.add('hidden');
        headerEmergency.classList.add('hidden');
        controlLock.classList.add('hidden');

        // Unlock controls
        document.getElementById('switchBulb1').disabled = false;
        document.getElementById('switchBulb2').disabled = false;
        document.getElementById('switchBuzzer').disabled = false;

        ['controlBulb1', 'controlBulb2'].forEach(id => {
            document.getElementById(id).classList.remove('opacity-50', 'pointer-events-none');
        });

        setRefreshInterval(false);
    }

    // Update switches to match actual state
    if (!isEmergency) {
        document.getElementById('switchBulb1').checked = latest.bulb1_status == 1;
        document.getElementById('switchBulb2').checked = latest.bulb2_status == 1;
        document.getElementById('switchBuzzer').checked = latest.buzzer_status == 1;
    }

    // Update control icons
    updateControlIcon('Bulb1', latest.bulb1_status == 1, 'yellow');
    updateControlIcon('Bulb2', latest.bulb2_status == 1, 'blue');
    updateControlIcon('Buzzer', latest.buzzer_status == 1, 'red');

    // Update charts
    updateCharts(data.history);

    // Update alerts table
    updateAlertsTable(data.alerts);

    // Update alert badge
    const unacknowledged = data.alerts ? data.alerts.filter(a => a.is_acknowledged == 0 && a.severity === 'critical').length : 0;
    const badge = document.getElementById('alertBadge');
    if (unacknowledged > 0) {
        badge.textContent = unacknowledged;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }

    state.lastData = data;
}

function updateStat(name, value, unit, rawValue, maxValue) {
    const el = document.getElementById(`stat${name}`);
    const bar = document.getElementById(`bar${name}`);
    if (el) el.textContent = (value !== null ? value : '--') + unit;
    if (bar && rawValue !== null) {
        const pct = Math.min((rawValue / maxValue) * 100, 100);
        bar.style.width = pct + '%';
    }
}

function updateControlIcon(name, isOn, color) {
    const icon = document.getElementById(`icon${name}`);
    const status = document.getElementById(`status${name}`);
    if (isOn) {
        icon.className = `fas fa-lightbulb text-${color}-400`;
        if (status) status.textContent = 'Status: ON (Manual)';
        if (status && name === 'Buzzer') {
            icon.className = 'fas fa-bell text-red-400 animate-swing';
            status.textContent = 'Status: ALARM ACTIVE';
        }
    } else {
        icon.className = `fas fa-lightbulb text-slate-600`;
        if (status) {
            if (name === 'Bulb1') status.textContent = 'Auto-controlled by LDR';
            else if (name === 'Bulb2') status.textContent = 'Auto-controlled by Motion';
            else if (name === 'Buzzer') status.textContent = 'Auto-triggered by smoke';
        }
    }
}

// ==================== CHARTS ====================
function initCharts() {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = '#334155';

    // Main Chart: Temp & Humidity
    const ctx1 = document.getElementById('mainChart').getContext('2d');
    state.charts.main = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Temperature (°C)',
                    data: [],
                    borderColor: CONFIG.chartColors.temp,
                    backgroundColor: CONFIG.chartColors.temp + '20',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6
                },
                {
                    label: 'Humidity (%)',
                    data: [],
                    borderColor: CONFIG.chartColors.humidity,
                    backgroundColor: CONFIG.chartColors.humidity + '20',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true } }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: '#1e293b' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Secondary Chart: Smoke & Light
    const ctx2 = document.getElementById('secondaryChart').getContext('2d');
    state.charts.secondary = new Chart(ctx2, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Smoke Level',
                    data: [],
                    borderColor: CONFIG.chartColors.smoke,
                    backgroundColor: CONFIG.chartColors.smoke + '10',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 2
                },
                {
                    label: 'Light Level',
                    data: [],
                    borderColor: CONFIG.chartColors.light,
                    backgroundColor: CONFIG.chartColors.light + '10',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#1e293b' } },
                x: { grid: { display: false } }
            }
        }
    });
}

function updateCharts(history) {
    if (!history || history.length === 0) return;

    const labels = history.map(h => {
        const d = new Date(h.time_label);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    });

    const temps = history.map(h => parseFloat(h.avg_temp).toFixed(1));
    const humidities = history.map(h => parseFloat(h.avg_humidity).toFixed(1));
    const smokes = history.map(h => parseInt(h.avg_smoke));
    const lights = history.map(h => parseInt(h.avg_light));

    // Update main chart
    state.charts.main.data.labels = labels;
    state.charts.main.data.datasets[0].data = temps;
    state.charts.main.data.datasets[1].data = humidities;
    state.charts.main.update('none');

    // Update secondary chart
    state.charts.secondary.data.labels = labels;
    state.charts.secondary.data.datasets[0].data = smokes;
    state.charts.secondary.data.datasets[1].data = lights;
    state.charts.secondary.update('none');
}

function setChartRange(range) {
    state.currentRange = range;
    document.querySelectorAll('.chart-range-btn').forEach(btn => {
        if (btn.dataset.range === range) {
            btn.className = 'chart-range-btn px-3 py-1 text-xs rounded-lg bg-primary-600 text-white';
        } else {
            btn.className = 'chart-range-btn px-3 py-1 text-xs rounded-lg bg-slate-800 text-slate-400 hover:text-white';
        }
    });
    loadDashboardData();
}

// ==================== DEVICE CONTROL ====================
async function toggleDevice(device, isOn) {
    const value = isOn ? 'ON' : 'OFF';

    try {
        const response = await fetch(`${CONFIG.apiBase}control_device.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device_id: CONFIG.deviceId,
                command_type: device,
                command_value: value
            })
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `${device.toUpperCase()} turned ${value}`,
                showConfirmButton: false,
                timer: 2000,
                background: '#1e293b',
                color: '#fff'
            });
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: data.error || 'Command failed',
                showConfirmButton: false,
                timer: 3000,
                background: '#1e293b',
                color: '#fff'
            });
            // Revert switch
            loadDashboardData();
        }
    } catch (error) {
        console.error('Control Error:', error);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Network error',
            showConfirmButton: false,
            timer: 2000,
            background: '#1e293b',
            color: '#fff'
        });
    }
}

// ==================== EMERGENCY ====================
async function acknowledgeEmergency() {
    try {
        const response = await fetch(`${CONFIG.apiBase}acknowledge_alert.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `device_id=${CONFIG.deviceId}`
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Alarm Acknowledged',
                text: 'The buzzer has been silenced. System remains in emergency mode until smoke clears.',
                confirmButtonColor: '#dc2626',
                background: '#1e293b',
                color: '#fff'
            });
            loadDashboardData();
        }
    } catch (error) {
        console.error('Acknowledge Error:', error);
    }
}

// ==================== ALERTS TABLE ====================
function updateAlertsTable(alerts) {
    const tbody = document.getElementById('alertsTableBody');
    if (!alerts || alerts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-slate-500">No recent alerts</td></tr>';
        return;
    }

    const typeIcons = {
        'smoke': 'fa-smog',
        'motion': 'fa-running',
        'temperature_high': 'fa-temperature-high',
        'humidity_high': 'fa-water',
        'system_offline': 'fa-wifi-slash'
    };

    const severityColors = {
        'critical': 'text-red-400 bg-red-500/10 border-red-500/30',
        'warning': 'text-yellow-400 bg-yellow-500/10 border-yellow-500/30',
        'info': 'text-blue-400 bg-blue-500/10 border-blue-500/30'
    };

    tbody.innerHTML = alerts.map(alert => `
        <tr class="border-b border-slate-800/50 hover:bg-slate-800/30 transition-colors">
            <td class="py-3">
                <span class="inline-flex items-center gap-2 px-2 py-1 rounded-lg text-xs font-medium ${severityColors[alert.severity] || severityColors.info} border">
                    <i class="fas ${typeIcons[alert.alert_type] || 'fa-bell'}"></i>
                    ${alert.alert_type.replace('_', ' ').toUpperCase()}
                </span>
            </td>
            <td class="py-3 text-slate-300">${alert.message}</td>
            <td class="py-3 text-slate-400">${alert.sensor_value || '-'}</td>
            <td class="py-3 text-slate-400 text-xs">${new Date(alert.created_at).toLocaleString()}</td>
            <td class="py-3">
                ${alert.is_acknowledged == 1 
                    ? '<span class="text-emerald-400 text-xs"><i class="fas fa-check mr-1"></i>Resolved</span>' 
                    : '<span class="text-yellow-400 text-xs animate-pulse"><i class="fas fa-clock mr-1"></i>Active</span>'}
            </td>
        </tr>
    `).join('');
}

function loadAlerts() {
    loadDashboardData();
}

// ==================== UI UTILITIES ====================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
}

function showSection(section) {
    // Simple section switching - can be expanded
    if (section === 'alerts') {
        document.querySelector('.overflow-x-auto').scrollIntoView({ behavior: 'smooth' });
    }
}

// Add custom animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes swing {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(15deg); }
        75% { transform: rotate(-15deg); }
    }
    .animate-swing {
        animation: swing 0.5s ease-in-out infinite;
    }
    .glass-card {
        background: rgba(30, 41, 59, 0.6);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
`;
document.head.appendChild(style);