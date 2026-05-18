<?php
/**
 * HOME AUTOMATION IoT - Configuration
 * Global deployment ready
 */

// Database credentials - UPDATE THESE FOR YOUR HOSTING
define('DB_HOST', 'localhost');
define('DB_NAME', 'home_automation');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security
define('API_KEY', 'HA_IOT_SECRET_KEY_2026_CHANGE_THIS'); // CHANGE THIS!
define('SESSION_TIMEOUT', 3600); // 1 hour

// Device settings
define('DEFAULT_DEVICE_ID', 'home_unit_01');

// CORS for global access (restrict in production)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit();
        }
    }
    return $pdo;
}

// API Key validation
function validateApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';

    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API Key']);
        exit();
    }
}

// Session start for dashboard
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function requireAuth() {
    startSession();
    if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity'])) {
        header('Location: login.php');
        exit();
    }
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Log system events
function logEvent($type, $message, $source = 'system') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO system_log (log_type, message, source) VALUES (?, ?, ?)");
        $stmt->execute([$type, $message, $source]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}