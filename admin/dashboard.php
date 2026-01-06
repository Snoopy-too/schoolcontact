<?php
// admin/dashboard.php
require '../config.php';

// Auth Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Language Logic
$lang = $_GET['lang'] ?? 'en';
$t = [
    'en' => [
        'dashboard' => 'Dashboard',
        'logout' => 'Logout',
        'create_slot' => 'Create Slot',
        'one_off' => 'One-off Slot',
        'recurring' => 'Recurring Generator',
        'bookings' => 'Bookings',
        'date' => 'Date',
        'time' => 'Time',
        'audience' => 'Audience',
        'cap' => 'Capacity',
        'kid' => 'Kids',
        'adult' => 'Adults',
        'create' => 'Create',
        'name' => 'Name',
        'contact' => 'Contact',
        'level' => 'Level',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'day_of_week' => 'Day of Week',
        'generated' => 'Slots Generated successfully!',
        'error' => 'Error occurred.',
        'delete' => 'Delete',
        'bulk_delete' => 'Delete Selected',
        'booked_cap' => 'Booked/Cap',
        'clear_all' => 'Clear All',
        'confirm_clear_slots' => 'WARNING: This will delete ALL future slots AND their associated bookings. This cannot be undone. Are you sure?',
        'confirm_clear_bookings' => 'WARNING: This will delete ALL bookings history. This cannot be undone. Are you sure?'
    ],
    'jp' => [
        'dashboard' => 'ダッシュボード',
        'logout' => 'ログアウト',
        'create_slot' => '枠作成',
        'one_off' => '単発作成',
        'recurring' => '繰り返し作成',
        'bookings' => '予約一覧',
        'date' => '日付',
        'time' => '時間',
        'audience' => '対象',
        'cap' => '定員',
        'kid' => '子供',
        'adult' => '大人',
        'create' => '作成',
        'name' => '名前',
        'contact' => '連絡先',
        'level' => 'レベル',
        'start_date' => '開始日',
        'end_date' => '終了日',
        'day_of_week' => '曜日',
        'generated' => '枠を作成しました！',
        'error' => 'エラーが発生しました。',
        'delete' => '削除',
        'bulk_delete' => '選択した項目を削除',
        'booked_cap' => '予約/定員',
        'clear_all' => '全て削除',
        'confirm_clear_slots' => '警告：すべての将来の枠とそれに関連する予約を削除します。この操作は取り消せません。よろしいですか？',
        'confirm_clear_bookings' => '警告：すべての予約履歴を削除します。この操作は取り消せません。よろしいですか？'
    ]
];
$txt = $t[$lang];

$message = '';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. One-off Creation
    if (isset($_POST['action']) && $_POST['action'] === 'one_off') {
        $date = $_POST['date'];
        $time = $_POST['time'];
        $audience = $_POST['audience'];
        $capacity = intval($_POST['capacity']);
        
        $datetime = "$date $time:00";
        
        try {
            $stmt = $pdo->prepare("INSERT INTO slots (slot_datetime, target_audience, max_capacity) VALUES (?, ?, ?)");
            $stmt->execute([$datetime, $audience, $capacity]);
            $message = $txt['generated'];
        } catch (PDOException $e) {
            $message = $txt['error'] . ' ' . $e->getMessage();
        }
    }

    // 2. Recurring Creation
    if (isset($_POST['action']) && $_POST['action'] === 'recurring') {
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $dayOfWeek = intval($_POST['day_of_week']); // 0=Sun, 6=Sat
        $time = $_POST['time'];
        $audience = $_POST['audience'];
        $capacity = intval($_POST['capacity']);

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        $endDate->modify('+1 day');

        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
        
        $count = 0;
        $sql = "INSERT INTO slots (slot_datetime, target_audience, max_capacity) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($period as $dt) {
            if ($dt->format('w') == $dayOfWeek) {
                $datetime = $dt->format('Y-m-d') . " $time:00";
                try {
                    $stmt->execute([$datetime, $audience, $capacity]);
                    $count++;
                } catch (Exception $e) {
                    // Ignore
                }
            }
        }
        $message = str_replace('Slots', "$count Slots", $txt['generated']);
    }

    // 4. Bulk Delete Slots (Selected)
    if (isset($_POST['bulk_delete_slots'])) {
        $ids = $_POST['slot_ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM slots WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            $message = count($ids) . " slots deleted.";
        }
    }

    // 5. Clear All Future Slots
    if (isset($_POST['clear_future_slots'])) {
        try {
            // Get IDs of future slots
            $idsStmt = $pdo->query("SELECT id FROM slots WHERE slot_datetime >= CURDATE()");
            $ids = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                // Delete associated bookings first (simulate CASCADE)
                $stmtDelBookings = $pdo->prepare("DELETE FROM bookings WHERE slot_id IN ($placeholders)");
                $stmtDelBookings->execute($ids);
                
                // Delete slots
                $stmtDelSlots = $pdo->prepare("DELETE FROM slots WHERE id IN ($placeholders)");
                $stmtDelSlots->execute($ids);
                
                $message = "All future slots (" . count($ids) . ") and their bookings cleared.";
            } else {
                $message = "No future slots found to clear.";
            }

        } catch (PDOException $e) {
            $message = "Error clearing slots: " . $e->getMessage();
        }
    }

    // 6. Clear All Bookings
    if (isset($_POST['clear_all_bookings'])) {
        try {
            $pdo->exec("DELETE FROM bookings"); // Truncate might fail if constraints, DELETE is safer
            // Add: ALTER TABLE bookings AUTO_INCREMENT = 1; if desired, but skip for simplicity
            $message = "All booking history cleared.";
        } catch (PDOException $e) {
            $message = "Error clearing bookings: " . $e->getMessage();
        }
    }
}

// Sorting logic
$sort = $_GET['sort'] ?? 'slot_datetime';
$order = $_GET['order'] ?? 'ASC';

// Allowed columns to sort by (white list for security)
$allowedSorts = ['id', 'slot_datetime', 'target_audience', 'booked_count'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'slot_datetime';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'ASC';
}

// Function to generate sort URL with label
function sortLink($col, $label, $currentSort, $currentOrder, $currentLang, $currentPage) {
    $newOrder = ($col === $currentSort && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    if ($col === $currentSort) {
        $arrow = ($currentOrder === 'ASC') ? ' ▲' : ' ▼';
    }
    return "<a href='?lang=$currentLang&page=$currentPage&sort=$col&order=$newOrder' class='text-dark text-decoration-none'>$label$arrow</a>";
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count Total Future Slots
$countStmt = $pdo->query("SELECT COUNT(*) FROM slots WHERE slot_datetime >= CURDATE()");
$totalSlots = $countStmt->fetchColumn();
$totalPages = ceil($totalSlots / $limit);

// Fetch Recent Slots (Future) with Sorting & Pagination
$sql = "
    SELECT s.*, COUNT(b.id) as booked_count 
    FROM slots s 
    LEFT JOIN bookings b ON s.id = b.slot_id 
    WHERE s.slot_datetime >= CURDATE()
    GROUP BY s.id
    ORDER BY $sort $order
    LIMIT :limit OFFSET :offset
";
$slotsStmt = $pdo->prepare($sql);
$slotsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$slotsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$slotsStmt->execute();
$futureSlots = $slotsStmt->fetchAll();

// Fetch Bookings (All for now)
$bookingsStmt = $pdo->query("
    SELECT b.*, s.slot_datetime, s.target_audience 
    FROM bookings b 
    LEFT JOIN slots s ON b.slot_id = s.id 
    ORDER BY s.slot_datetime ASC
");
$bookings = $bookingsStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">
        <img src="../images/logo.png" alt="School Admin" style="height: 40px; background: white; padding: 2px; border-radius: 4px;"> 
        School Admin
    </a>
    <div class="d-flex">
        <a href="settings.php?lang=<?php echo $lang; ?>" class="btn btn-sm btn-outline-light me-2"><?php echo $lang == 'en' ? 'Settings' : '設定'; ?></a>
        <a href="?lang=en" class="btn btn-sm btn-outline-light me-2 <?php echo $lang=='en'?'active':''; ?>">EN</a>
        <a href="?lang=jp" class="btn btn-sm btn-outline-light me-3 <?php echo $lang=='jp'?'active':''; ?>">JP</a>
        <a href="logout.php" class="btn btn-danger btn-sm"><?php echo $txt['logout']; ?></a>
    </div>
  </div>
</nav>

<div class="container py-4">
    <?php if($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Create One-off -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <?php echo $txt['one_off']; ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="one_off">
                        <div class="row mb-2">
                            <div class="col">
                                <label><?php echo $txt['date']; ?></label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div class="col">
                                <label><?php echo $txt['time']; ?></label>
                                <input type="time" name="time" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col">
                                <label><?php echo $txt['audience']; ?></label>
                                <select name="audience" class="form-select">
                                    <option value="Kids New">Kids New</option>
                                    <option value="Kids (C)">Kids (C)</option>
                                    <option value="Kids (B)">Kids (B)</option>
                                    <option value="Kids (B2)">Kids (B2)</option>
                                    <option value="Kids (A)">Kids (A)</option>
                                    <option value="Jr. High">Jr. High</option>
                                    <option value="High Sch">High Sch</option>
                                    <option value="Adult (L)">Adult (L)</option>
                                    <option value="Adult (H)">Adult (H)</option>
                                </select>
                            </div>
                            <div class="col">
                                <label><?php echo $txt['cap']; ?></label>
                                <input type="number" name="capacity" class="form-control" value="6">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $txt['create']; ?></button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Recurring -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <?php echo $txt['recurring']; ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="recurring">
                        <div class="row mb-2">
                            <div class="col">
                                <label><?php echo $txt['start_date']; ?></label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col">
                                <label><?php echo $txt['end_date']; ?></label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col">
                                <label><?php echo $txt['day_of_week']; ?></label>
                                <select name="day_of_week" class="form-select">
                                    <option value="1">Mon (月)</option>
                                    <option value="2">Tue (火)</option>
                                    <option value="3">Wed (水)</option>
                                    <option value="4">Thu (木)</option>
                                    <option value="5">Fri (金)</option>
                                    <option value="6">Sat (土)</option>
                                    <option value="0">Sun (日)</option>
                                </select>
                            </div>
                            <div class="col">
                                <label><?php echo $txt['time']; ?></label>
                                <input type="time" name="time" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col">
                                <label><?php echo $txt['audience']; ?></label>
                                <select name="audience" class="form-select">
                                    <option value="Kids New">Kids New</option>
                                    <option value="Kids (C)">Kids (C)</option>
                                    <option value="Kids (B)">Kids (B)</option>
                                    <option value="Kids (B2)">Kids (B2)</option>
                                    <option value="Kids (A)">Kids (A)</option>
                                    <option value="Jr. High">Jr. High</option>
                                    <option value="High Sch">High Sch</option>
                                    <option value="Adult (L)">Adult (L)</option>
                                    <option value="Adult (H)">Adult (H)</option>
                                </select>
                            </div>
                            <div class="col">
                                <label><?php echo $txt['cap']; ?></label>
                                <input type="number" name="capacity" class="form-control" value="6">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><?php echo $txt['create']; ?> (Generate)</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Future Slots List with Bulk Delete -->
    <h4 class="mb-3 d-flex justify-content-between align-items-center">
        <span>Future Slots</span>
        <form method="post">
            <input type="hidden" name="clear_future_slots" value="1">
            <button type="button" class="btn btn-danger btn-sm" onclick="showConfirmModal(this, '<?php echo htmlspecialchars($txt['confirm_clear_slots'], ENT_QUOTES); ?>')"><?php echo $txt['clear_all']; ?></button>
        </form>
    </h4>
    
    <div class="d-flex justify-content-between mb-2">
        <div>
            <!-- Pagination Info -->
            <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total: <?php echo $totalSlots; ?>)</span>
        </div>
    </div>

    <form method="post" onsubmit="return confirm('Delete selected slots?');">
        <input type="hidden" name="bulk_delete_slots" value="1">
        
        <div class="table-responsive mb-3 bg-white shadow-sm p-3">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th><?php echo sortLink('slot_datetime', $txt['date'] . '/' . $txt['time'], $sort, $order, $lang, $page); ?></th>
                        <th><?php echo sortLink('target_audience', $txt['audience'], $sort, $order, $lang, $page); ?></th>
                        <th><?php echo sortLink('booked_count', $txt['booked_cap'], $sort, $order, $lang, $page); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($futureSlots)): ?>
                        <tr><td colspan="5" class="text-center">No future slots found.</td></tr>
                    <?php else: ?>
                        <?php foreach($futureSlots as $slot): ?>
                        <tr>
                            <td><input type="checkbox" name="slot_ids[]" value="<?php echo $slot['id']; ?>"></td>
                            <td><?php echo $slot['slot_datetime']; ?></td>
                            <td><?php echo $slot['target_audience']; ?></td>
                            <td>
                                <span class="badge <?php echo $slot['booked_count'] >= $slot['max_capacity'] ? 'bg-danger' : 'bg-info'; ?>">
                                    <?php echo $slot['booked_count'] . '/' . $slot['max_capacity']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mb-5">
            <button type="submit" class="btn btn-danger"><?php echo $txt['bulk_delete']; ?></button>
            
            <!-- Pagination Controls -->
            <div class="btn-group float-end" role="group">
                <?php if($page > 1): ?>
                    <a href="?lang=<?php echo $lang; ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-outline-secondary">&laquo; Prev</a>
                <?php endif; ?>
                
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?lang=<?php echo $lang; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-outline-secondary <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if($page < $totalPages): ?>
                    <a href="?lang=<?php echo $lang; ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-outline-secondary">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>

    </form>

    <!-- Bookings Table -->
    <h3 class="mb-3 d-flex justify-content-between align-items-center">
        <span><?php echo $txt['bookings']; ?></span>
        <form method="post">
            <input type="hidden" name="clear_all_bookings" value="1">
            <button type="button" class="btn btn-danger btn-sm" onclick="showConfirmModal(this, '<?php echo htmlspecialchars($txt['confirm_clear_bookings'], ENT_QUOTES); ?>')"><?php echo $txt['clear_all']; ?></button>
        </form>
    </h3>
    <div class="table-responsive bg-white shadow p-3">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo $txt['date']; ?></th>
                    <th><?php echo $txt['time']; ?></th>
                    <th><?php echo $txt['name']; ?></th>
                    <th><?php echo $txt['contact']; ?></th>
                    <th><?php echo $txt['level']; ?></th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($bookings)): ?>
                    <tr><td colspan="7" class="text-center">No bookings yet.</td></tr>
                <?php else: ?>
                    <?php foreach($bookings as $b): 
                        if ($b['slot_datetime']) {
                            $d = new DateTime($b['slot_datetime']);
                            $dateStr = $d->format('Y-m-d');
                            $timeStr = $d->format('H:i');
                            $typeStr = $b['target_audience'];
                        } else {
                            // Waitlist
                            $dateStr = '-';
                            $timeStr = 'WAITLIST';
                            $typeStr = '-';
                        }
                    ?>
                    <tr>
                        <td><?php echo $dateStr; ?></td>
                        <td><?php echo $timeStr; ?></td>
                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                        <td><?php echo htmlspecialchars($b['contact_info']); ?></td>
                        <td><?php echo htmlspecialchars($b['english_level']); ?></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $typeStr; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Warning</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmationMessage">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmBtn">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let formToSubmit = null;

    function showConfirmModal(button, message) {
        formToSubmit = button.closest('form');
        document.getElementById('confirmationMessage').innerText = message;
        var myModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        myModal.show();
    }

    document.getElementById('confirmBtn').addEventListener('click', function() {
        if(formToSubmit) {
            formToSubmit.submit();
        }
    });

    // Toggle for checkboxes
    function toggleAll(source) {
        checkboxes = document.getElementsByName('slot_ids[]');
        for(var i=0, n=checkboxes.length;i<n;i++) {
            checkboxes[i].checked = source.checked;
        }
    }
</script>
</html>
