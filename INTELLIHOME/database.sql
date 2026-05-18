-- ============================================================
-- HOME AUTOMATION IoT - DATABASE SCHEMA (CORRECTED)
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================


-- ------------------------------------------------------------
-- 1. USERS (Dashboard login)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role ENUM('admin','viewer') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Default admin: admin / admin123 (change after first login!)
-- Password hash generated for: admin123
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2b$10$SnXEK6oyiqokUA5K23SoEOOddlMHPobuDfFtvulCsBJmHxpRvBfj6', 'System Admin', 'admin@home.local', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- ------------------------------------------------------------
-- 2. SENSOR READINGS (All sensor data)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sensor_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temperature DECIMAL(5,2) DEFAULT NULL,
    humidity DECIMAL(5,2) DEFAULT NULL,
    distance INT DEFAULT NULL,
    light_level INT DEFAULT NULL,
    smoke_level INT DEFAULT NULL,
    bulb1_status TINYINT(1) DEFAULT 0,
    bulb2_status TINYINT(1) DEFAULT 0,
    buzzer_status TINYINT(1) DEFAULT 0,
    emergency_flag TINYINT(1) DEFAULT 0,
    device_id VARCHAR(50) DEFAULT 'home_unit_01',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_emergency (emergency_flag)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. DEVICE STATUS (Current state snapshot)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) DEFAULT 'home_unit_01',
    bulb1_status TINYINT(1) DEFAULT 0,
    bulb2_status TINYINT(1) DEFAULT 0,
    buzzer_status TINYINT(1) DEFAULT 0,
    emergency_flag TINYINT(1) DEFAULT 0,
    auto_light_enabled TINYINT(1) DEFAULT 1,
    auto_motion_enabled TINYINT(1) DEFAULT 1,
    smoke_threshold INT DEFAULT 400,
    light_threshold INT DEFAULT 300,
    motion_distance INT DEFAULT 50,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_device (device_id)
) ENGINE=InnoDB;

INSERT INTO device_status (device_id) VALUES ('home_unit_01')
ON DUPLICATE KEY UPDATE id=id;

-- ------------------------------------------------------------
-- 4. DEVICE COMMANDS (Queue for Arduino)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) DEFAULT 'home_unit_01',
    command_type ENUM('bulb1','bulb2','buzzer','acknowledge','reset_emergency','update_threshold') NOT NULL,
    command_value VARCHAR(50) NOT NULL,
    status ENUM('pending','executed','failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_device (device_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. ALERTS LOG (Emergency and motion events)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('smoke','motion','temperature_high','humidity_high','system_offline') NOT NULL,
    severity ENUM('info','warning','critical') DEFAULT 'warning',
    message TEXT,
    sensor_value INT DEFAULT NULL,
    device_id VARCHAR(50) DEFAULT 'home_unit_01',
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (alert_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. EMERGENCY EVENTS (Smoke incident tracking)
-- FIXED: Removed duplicate PRIMARY KEY declaration
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS emergency_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) DEFAULT 'home_unit_01',
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    smoke_value_at_trigger INT DEFAULT NULL,
    cleared_at TIMESTAMP NULL,
    duration_seconds INT DEFAULT NULL,
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by INT NULL,
    notes TEXT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. SYSTEM LOG (Bridge connectivity, errors)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('info','warning','error','bridge') DEFAULT 'info',
    message TEXT,
    source VARCHAR(50) DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (log_type)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VIEWS FOR DASHBOARD
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW latest_reading AS
SELECT * FROM sensor_readings ORDER BY created_at DESC LIMIT 1;

CREATE OR REPLACE VIEW today_stats AS
SELECT 
    COUNT(*) as total_readings,
    AVG(temperature) as avg_temp,
    MAX(temperature) as max_temp,
    MIN(temperature) as min_temp,
    AVG(humidity) as avg_humidity,
    MAX(smoke_level) as max_smoke,
    SUM(emergency_flag) as emergency_count
FROM sensor_readings 
WHERE DATE(created_at) = CURDATE();