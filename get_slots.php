<?php
// get_slots.php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Support array of audiences ?audience[]=A&audience[]=B
$audiences = $_GET['audience'] ?? [];

if (empty($audiences)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid audience parameter']);
    exit;
}

// Ensure it's an array
if (!is_array($audiences)) {
    $audiences = [$audiences];
}

try {
    // Determine placeholders for IN clause
    $placeholders = str_repeat('?,', count($audiences) - 1) . '?';
    
    $sql = "
        SELECT s.id, s.slot_datetime, s.target_audience, s.max_capacity, COUNT(b.id) as current_bookings
        FROM slots s
        LEFT JOIN bookings b ON s.id = b.slot_id
        WHERE s.target_audience IN ($placeholders)
          AND s.slot_datetime > NOW()
        GROUP BY s.id
        HAVING current_bookings < s.max_capacity
        ORDER BY s.slot_datetime ASC
        LIMIT 4
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($audiences);
    $slots = $stmt->fetchAll();

    $formattedSlots = array_map(function($slot) {
        $dateObj = new DateTime($slot['slot_datetime']);
        return [
            'id' => $slot['id'],
            'date_str' => $dateObj->format('Y-m-d'),
            'time_str' => $dateObj->format('H:i'),
            'display_datetime' => $dateObj->format('Y/m/d (D) H:i'),
            'spots_left' => $slot['max_capacity'] - $slot['current_bookings']
        ];
    }, $slots);

    echo json_encode(['slots' => $formattedSlots]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
