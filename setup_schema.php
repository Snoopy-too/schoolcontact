<?php
// setup_schema.php
// MASTER MIGRATION SCRIPT
// Upload and run this to create ALL necessary tables in one go.

include 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create SLOTS table
    $sql_slots = "CREATE TABLE IF NOT EXISTS slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_datetime DATETIME NOT NULL,
        max_capacity INT DEFAULT 1,
        target_audience VARCHAR(50) DEFAULT 'Adult (H)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql_slots);
    echo "Table 'slots' checked/created.<br>";

    // 2. Create BOOKINGS table
    $sql_bookings = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_id INT NULL,
        name VARCHAR(100) NOT NULL,
        contact_info VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        english_level VARCHAR(50),
        booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql_bookings);
    echo "Table 'bookings' checked/created.<br>";

    // Add phone column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN phone VARCHAR(50) AFTER contact_info");
        echo "Column 'phone' added to bookings table.<br>";
    } catch (PDOException $e) {
        // Column likely already exists - ignore
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            echo "Note: phone column may already exist.<br>";
        }
    }

    // 3. Create SETTINGS table
    $sql_settings = "CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )";
    $pdo->exec($sql_settings);
    echo "Table 'settings' checked/created.<br>";

    // 4. Populate Default Settings (including cc_email)
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    
    $defaults = [
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => '587',
        'smtp_user' => 'user@example.com',
        'smtp_pass' => '',
        'from_email' => 'noreply@schoolcontact.com',
        'admin_email' => 'admin@schoolcontact.com',
        'admin_cc_email' => ''
    ];

    foreach ($defaults as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    echo "Default settings populated.<br>";
    
    echo "<h2 style='color:green'>Database Setup Complete!</h2>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
