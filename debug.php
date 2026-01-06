<?php
// debug.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";
echo "Attempting to require config.php...<br>";

if (file_exists('config.php')) {
    echo "config.php found.<br>";
    try {
        require 'config.php';
        echo "config.php included successfully.<br>";
        
        echo "PDO Status: " . (isset($pdo) ? "Connected" : "Not connected") . "<br>";
        
    } catch (Throwable $e) {
        echo "<div style='color:red'>Error loading config: " . $e->getMessage() . "</div>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<div style='color:red'>config.php NOT found!</div>";
}
?>
