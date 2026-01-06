<?php
// setup_schema.php
require 'config.php';

try {
    // 1. Create Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'admins' checked/created.<br>";

    // 2. Create Slots Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_datetime DATETIME NOT NULL,
        target_audience ENUM('kid', 'adult') NOT NULL,
        max_capacity INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'slots' checked/created.<br>";

    // 3. Create Bookings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        contact_info VARCHAR(255) NOT NULL,
        english_level VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
    )");
    echo "Table 'bookings' checked/created.<br>";

    // 4. Insert Default Admin User
    // User: admin, Pass: password123
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $passHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES ('admin', ?)");
        $stmt->execute([$passHash]);
        echo "Default admin user created (User: admin, Pass: password123).<br>";
    } else {
        echo "Default admin user already exists.<br>";
    }
    
    // Optional: Insert some dummy slots for testing if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM slots");
    if ($stmt->fetchColumn() == 0) {
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00'));
        $dayAfter = date('Y-m-d H:i:s', strtotime('+2 days 15:00:00'));
        
        $pdo->exec("INSERT INTO slots (slot_datetime, target_audience, max_capacity) VALUES 
            ('$tomorrow', 'kid', 6),
            ('$dayAfter', 'adult', 4)
        ");
        echo "Dummy slots inserted for testing.<br>";
    }

    echo "Database setup completed successfully.";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
