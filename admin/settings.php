<?php
// admin/settings.php
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
        'settings_title' => 'Settings - School Admin',
        'dashboard' => 'Dashboard',
        'logout' => 'Logout',
        'system_settings' => 'System Settings',
        'email_config' => 'Email Configuration (SMTP)',
        'smtp_host' => 'SMTP Host',
        'smtp_port' => 'SMTP Port',
        'smtp_user' => 'SMTP User',
        'smtp_pass' => 'SMTP Password',
        'general_email' => 'General Email Settings',
        'from_email' => 'From Email (Sender)',
        'from_desc' => 'Emails to students will appear from this address.',
        'admin_email' => 'Admin Email (Receiver)',
        'admin_desc' => 'Booking notifications will be sent to this address.',
        'cc_email' => 'CC Email (Optional)',
        'cc_desc' => 'Receive a copy of notifications here.',
        'save' => 'Save Settings',
        'back' => 'Back to Dashboard',
        'updated' => 'Settings updated successfully.',
        'error' => 'Error updating settings: '
    ],
    'jp' => [
        'settings_title' => '設定 - School Admin',
        'dashboard' => 'ダッシュボード',
        'logout' => 'ログアウト',
        'system_settings' => 'システム設定',
        'email_config' => 'メール設定 (SMTP)',
        'smtp_host' => 'SMTP ホスト',
        'smtp_port' => 'SMTP ポート',
        'smtp_user' => 'SMTP ユーザー',
        'smtp_pass' => 'SMTP パスワード',
        'general_email' => '一般メール設定',
        'from_email' => '送信元メールアドレス',
        'from_desc' => '生徒へ送信されるメールの送信元となります。',
        'admin_email' => '管理者メールアドレス (受信)',
        'admin_desc' => '予約通知がこのアドレスに送信されます。',
        'cc_email' => 'CC メールアドレス (任意)',
        'cc_desc' => '通知のコピーをここで受信します。',
        'save' => '設定を保存',
        'back' => 'ダッシュボードへ戻る',
        'updated' => '設定を更新しました。',
        'error' => '設定の更新中にエラーが発生しました: '
    ]
];
$txt = $t[$lang];

$message = '';

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_user' => $_POST['smtp_user'],
        'smtp_pass' => $_POST['smtp_pass'], // In real app, handle blank pass to keep existing
        'from_email' => $_POST['from_email'],
        'admin_email' => $_POST['admin_email'],
        'admin_cc_email' => $_POST['admin_cc_email']
    ];

    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($updates as $key => $val) {
            $stmt->execute([$key, $val, $val]);
        }
        $message = $txt['updated'];
    } catch (PDOException $e) {
        $message = $txt['error'] . $e->getMessage();
    }
}

// Fetch current values
$current = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $current[$row['setting_key']] = $row['setting_value'];
}

// Defaults to avoid warnings
$defaults = [
    'smtp_host' => '', 'smtp_port' => '', 'smtp_user' => '', 
    'smtp_pass' => '', 'from_email' => '', 'admin_email' => '', 'admin_cc_email' => ''
];
$current = array_merge($defaults, $current);

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $txt['settings_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php?lang=<?php echo $lang; ?>">
        <img src="../images/logo.png" alt="School Admin" style="height: 40px; background: white; padding: 2px; border-radius: 4px;"> 
        School Admin
    </a>
    <div class="d-flex">
        <a href="dashboard.php?lang=<?php echo $lang; ?>" class="btn btn-sm btn-outline-light me-2"><?php echo $txt['dashboard']; ?></a>
        <a href="logout.php" class="btn btn-danger btn-sm"><?php echo $txt['logout']; ?></a>
    </div>
  </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0"><?php echo $txt['system_settings']; ?></h4>
                </div>
                <div class="card-body">
                    <?php if($message): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <h5 class="mb-3"><?php echo $txt['email_config']; ?></h5>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['smtp_host']; ?></label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($current['smtp_host']); ?>">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo $txt['smtp_port']; ?></label>
                                <input type="text" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($current['smtp_port']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo $txt['smtp_user']; ?></label>
                                <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($current['smtp_user']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['smtp_pass']; ?></label>
                            <input type="text" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($current['smtp_pass']); ?>">
                        </div>

                        <hr>

                        <h5 class="mb-3"><?php echo $txt['general_email']; ?></h5>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['from_email']; ?></label>
                            <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($current['from_email']); ?>">
                            <small class="text-muted"><?php echo $txt['from_desc']; ?></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['admin_email']; ?></label>
                            <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($current['admin_email']); ?>">
                            <small class="text-muted"><?php echo $txt['admin_desc']; ?></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['cc_email']; ?></label>
                            <input type="email" name="admin_cc_email" class="form-control" value="<?php echo htmlspecialchars($current['admin_cc_email']); ?>">
                            <small class="text-muted"><?php echo $txt['cc_desc']; ?></small>
                        </div>

                        <button type="submit" class="btn btn-primary"><?php echo $txt['save']; ?></button>
                        <a href="dashboard.php?lang=<?php echo $lang; ?>" class="btn btn-link"><?php echo $txt['back']; ?></a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
