<?php
require 'config.php';

try {
    // Add admin_cc_email setting
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['admin_cc_email', '']);
    echo "Added admin_cc_email setting.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
