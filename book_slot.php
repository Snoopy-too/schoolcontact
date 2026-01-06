<?php
// book_slot.php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF Check
if (!isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Honeypot
if (!empty($input['website'])) {
    echo json_encode(['success' => true, 'message' => 'Booking Confirmed']);
    exit;
}

// Validation
// slot_id is optional (NULL allowed for Waitlist)
$slot_id = isset($input['slot_id']) ? filter_var($input['slot_id'], FILTER_VALIDATE_INT) : null;
$name = trim($input['name'] ?? '');
$contact = trim($input['contact'] ?? '');
$level = trim($input['level'] ?? ''); // Can store grade or level here
$type = trim($input['type'] ?? 'Booking'); // 'Booking' or 'Waitlist'

if (empty($name) || empty($contact)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    // If there is a slot_id, check capacity
    if ($slot_id) {
        $stmt = $pdo->prepare("
            SELECT s.max_capacity, COUNT(b.id) as current_bookings
            FROM slots s
            LEFT JOIN bookings b ON s.id = b.slot_id
            WHERE s.id = :id
            GROUP BY s.id
        ");
        $stmt->execute(['id' => $slot_id]);
        $slot = $stmt->fetch();

        if (!$slot) {
            throw new Exception('Slot not found');
        }

        if ($slot['current_bookings'] >= $slot['max_capacity']) {
            throw new Exception('Slot is full');
        }
    }

    // Insert Booking or Waitlist
    $insertStmt = $pdo->prepare("
        INSERT INTO bookings (slot_id, name, contact_info, english_level)
        VALUES (:slot_id, :name, :contact, :level)
    ");
    $insertStmt->execute([
        'slot_id' => $slot_id, // Can be NULL
        'name' => $name,
        'contact' => $contact,
        'level' => $level
    ]);

    $pdo->commit();

    // Email Logic
    $to = ADMIN_EMAIL;
    $subject = $slot_id ? "New Booking Receipt" : "New WAITLIST Inquiry";
    
    // Construct Message
    $messageBody = "New " . ($slot_id ? "Booking" : "Waitlist Request") . "\n\n";
    if ($slot_id) $messageBody .= "Slot ID: $slot_id\n";
    $messageBody .= "Name: $name\n";
    $messageBody .= "Contact: $contact\n";
    $messageBody .= "Level/Grade: $level\n";
    $messageBody .= "\nView in Dashboard: " . "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/admin/dashboard.php";

    // Headers
    $headers = [
        'From' => FROM_EMAIL,
        'Reply-To' => FROM_EMAIL,
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    // Add CC if configured
    if (defined('ADMIN_CC_EMAIL') && ADMIN_CC_EMAIL !== '') {
        $headers['Cc'] = ADMIN_CC_EMAIL;
    }

    // Convert headers array to string
    $headersString = '';
    foreach ($headers as $key => $value) {
        $headersString .= "$key: $value\r\n";
    }

    // Try to send email (basic PHP mail)
    // Note: This requires a working mail server or Sendmail configuration in php.ini
    $mailSent = mail($to, $subject, $messageBody, $headersString);
    
    if (!$mailSent) {
        // Fallback logging if mail fails (common in local dev without SMTP)
        error_log("Mail() failed. simulated to: $to, CC: " . (defined('ADMIN_CC_EMAIL') ? ADMIN_CC_EMAIL : 'none') . " Message: $messageBody");
    } else {
        error_log("Mail sent to $to, CC: " . (defined('ADMIN_CC_EMAIL') ? ADMIN_CC_EMAIL : 'none'));
    }

    echo json_encode(['success' => true, 'message' => 'Confirmed!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed: ' . $e->getMessage()]);
}
?>
