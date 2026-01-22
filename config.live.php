<?php
// config.php
// Manually created with live credentials
date_default_timezone_set('Asia/Tokyo');

// Database Configuration
$host = 'localhost';
$dbname = 'mxbttmmy_schoolcontact';
$username = 'mxbttmmy_schoolcontactu';
$password = 'tN%f?aZ_4Zc&'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set MySQL timezone to JST
    $pdo->exec("SET time_zone = '+09:00';");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load Settings from DB
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // If table doesn't exist yet or error, use defaults (or do nothing)
}

// Define Constants (fallback to defaults if DB is empty/missing key)
define('SMTP_HOST', $settings['smtp_host'] ?? 'smtp.example.com');
define('SMTP_PORT', $settings['smtp_port'] ?? 587);
define('SMTP_USER', $settings['smtp_user'] ?? 'your_email@example.com');
define('SMTP_PASS', $settings['smtp_pass'] ?? 'your_password');
define('FROM_EMAIL', $settings['from_email'] ?? 'noreply@schoolcontact.com');
define('ADMIN_EMAIL', $settings['admin_email'] ?? 'admin@schoolcontact.com');
define('ADMIN_CC_EMAIL', $settings['admin_cc_email'] ?? '');

// Start Session for all pages that include config
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Generate CSRF Token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('CSRF validation failed.');
    }
}
?>
