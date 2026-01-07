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
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$level = trim($input['level'] ?? ''); // Can store grade or level here
$type = trim($input['type'] ?? 'Booking'); // 'Booking' or 'Waitlist'

if (empty($name) || empty($email) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields (name, email, and phone are required)']);
    exit;
}

// Auto-migrate: Add phone column if it doesn't exist
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'phone'");
    if ($checkColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN phone VARCHAR(50) AFTER contact_info");
    }
} catch (PDOException $e) {
    // Log but don't fail - column might already exist
    error_log("book_slot.php: Could not check/add phone column: " . $e->getMessage());
}

try {
    $pdo->beginTransaction();

    // Variable to store slot datetime for emails
    $slotDatetime = null;
    $slotDisplayStr = null;

    // If there is a slot_id, check capacity and get datetime
    if ($slot_id) {
        $stmt = $pdo->prepare("
            SELECT s.slot_datetime, s.max_capacity, COUNT(b.id) as current_bookings
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

        // Store the datetime for use in emails
        $slotDatetime = new DateTime($slot['slot_datetime']);
        $slotDisplayStr = $slotDatetime->format('Y年n月j日 (D) H:i');
    }

    // Insert Booking or Waitlist
    $insertStmt = $pdo->prepare("
        INSERT INTO bookings (slot_id, name, contact_info, phone, english_level)
        VALUES (:slot_id, :name, :email, :phone, :level)
    ");
    $insertStmt->execute([
        'slot_id' => $slot_id, // Can be NULL
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'level' => $level
    ]);

    $pdo->commit();

    // ========================================
    // EMAIL 1: Admin Notification
    // ========================================
    $adminTo = ADMIN_EMAIL;
    $adminSubject = $slot_id ? "【新規予約】New Booking Receipt" : "【ウェイトリスト】New WAITLIST Inquiry";
    
    // Construct Admin Message
    $adminMessage = "New " . ($slot_id ? "Booking" : "Waitlist Request") . "\n";
    $adminMessage .= "新しい" . ($slot_id ? "予約" : "ウェイトリストリクエスト") . "\n\n";
    
    if ($slot_id && $slotDisplayStr) {
        $adminMessage .= "予約日時 / Date & Time: $slotDisplayStr\n";
    }
    $adminMessage .= "お名前 / Name: $name\n";
    $adminMessage .= "メール / Email: $email\n";
    $adminMessage .= "電話 / Phone: $phone\n";
    $adminMessage .= "レベル / Level/Grade: $level\n";
    $adminMessage .= "\n管理画面 / Dashboard: " . "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/admin/dashboard.php";

    // Admin Headers
    $adminHeaders = [
        'From' => FROM_EMAIL,
        'Reply-To' => $email, // Reply goes to the student
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/plain; charset=UTF-8'
    ];
    
    // Add CC if configured
    if (defined('ADMIN_CC_EMAIL') && ADMIN_CC_EMAIL !== '') {
        $adminHeaders['Cc'] = ADMIN_CC_EMAIL;
    }

    // Convert headers array to string
    $adminHeadersString = '';
    foreach ($adminHeaders as $key => $value) {
        $adminHeadersString .= "$key: $value\r\n";
    }

    // Send Admin Email
    $adminMailSent = mail($adminTo, $adminSubject, $adminMessage, $adminHeadersString);
    
    if (!$adminMailSent) {
        error_log("Admin Mail() failed. To: $adminTo, Message: $adminMessage");
    } else {
        error_log("Admin Mail sent to $adminTo");
    }

    // ========================================
    // EMAIL 2: Student Confirmation
    // ========================================
    $studentSubject = $slot_id 
        ? "【予約確認】無料体験レッスンのご予約ありがとうございます / Booking Confirmation" 
        : "【受付完了】お問い合わせありがとうございます / Inquiry Received";
    
    // Construct Student Message (Bilingual: Japanese first, then English)
    $studentMessage = "";
    
    if ($slot_id && $slotDisplayStr) {
        // Confirmed booking
        $studentMessage .= "$name 様\n\n";
        $studentMessage .= "この度は無料体験レッスンをご予約いただき、誠にありがとうございます。\n";
        $studentMessage .= "以下の内容でご予約を承りました。\n\n";
        $studentMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
        $studentMessage .= "【ご予約内容】\n";
        $studentMessage .= "日時: $slotDisplayStr\n";
        $studentMessage .= "レベル: $level\n";
        $studentMessage .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $studentMessage .= "当日はお気をつけてお越しください。\n";
        $studentMessage .= "ご質問がございましたら、お気軽にご連絡ください (pierenglish@yahoo.co.jp)。\n\n";
        $studentMessage .= "---\n\n";
        $studentMessage .= "Dear $name,\n\n";
        $studentMessage .= "Thank you for booking a free trial lesson.\n";
        $studentMessage .= "Your reservation has been confirmed as follows:\n\n";
        $studentMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
        $studentMessage .= "【Booking Details】\n";
        $studentMessage .= "Date & Time: " . $slotDatetime->format('F j, Y (D) H:i') . "\n";
        $studentMessage .= "Level: $level\n";
        $studentMessage .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $studentMessage .= "We look forward to seeing you!\n";
        $studentMessage .= "If you have any questions, please feel free to contact us (pierenglish@yahoo.co.jp).\n";
    } else {
        // Waitlist
        $studentMessage .= "$name 様\n\n";
        $studentMessage .= "この度はお問い合わせいただき、誠にありがとうございます。\n";
        $studentMessage .= "現在、ご希望のクラスに空きがないため、ウェイトリストに登録させていただきました。\n";
        $studentMessage .= "空きが出次第、ご連絡いたします。\n\n";
        $studentMessage .= "ご質問がございましたら、お気軽にご連絡ください (pierenglish@yahoo.co.jp)。\n\n";
        $studentMessage .= "---\n\n";
        $studentMessage .= "Dear $name,\n\n";
        $studentMessage .= "Thank you for your inquiry.\n";
        $studentMessage .= "We have added you to our waitlist and will contact you as soon as an opening becomes available.\n\n";
        $studentMessage .= "If you have any questions, please feel free to contact us (pierenglish@yahoo.co.jp).\n";
    }

    // Student Headers
    $studentHeaders = [
        'From' => FROM_EMAIL,
        'Reply-To' => FROM_EMAIL,
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/plain; charset=UTF-8'
    ];

    // Convert headers array to string
    $studentHeadersString = '';
    foreach ($studentHeaders as $key => $value) {
        $studentHeadersString .= "$key: $value\r\n";
    }

    // Send Student Confirmation Email
    $studentMailSent = mail($email, $studentSubject, $studentMessage, $studentHeadersString);
    
    if (!$studentMailSent) {
        error_log("Student Mail() failed. To: $email");
    } else {
        error_log("Student confirmation email sent to $email");
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
