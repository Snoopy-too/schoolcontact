<?php
require 'config.php';

try {
    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )";
    $pdo->exec($sql);

    // Default values
    $defaults = [
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '587',
        'smtp_user' => 'your_email@example.com',
        'smtp_pass' => 'your_password',
        'from_email' => 'noreply@schoolcontact.com',
        'admin_email' => 'admin@schoolcontact.com'
    ];

    foreach ($defaults as $key => $val) {
        // Insert if not exists
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }

    echo "Settings table created and populated.";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
