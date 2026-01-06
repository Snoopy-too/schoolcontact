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
        'error' => 'Error updating settings: ',
        // Password Change Translations
        'change_password' => 'Change Password',
        'current_password' => 'Current Password',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm New Password',
        'update_password' => 'Update Password',
        'password_updated' => 'Password updated successfully.',
        'password_mismatch' => 'New passwords do not match.',
        'current_password_wrong' => 'Current password is incorrect.',
        'password_required' => 'All password fields are required.'
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
        'error' => '設定の更新中にエラーが発生しました: ',
        // Password Change Translations
        'change_password' => 'パスワード変更',
        'current_password' => '現在のパスワード',
        'new_password' => '新しいパスワード',
        'confirm_password' => '新しいパスワード (確認)',
        'update_password' => 'パスワードを更新',
        'password_updated' => 'パスワードを更新しました。',
        'password_mismatch' => '新しいパスワードが一致しません。',
        'current_password_wrong' => '現在のパスワードが正しくありません。',
        'password_required' => 'すべてのパスワードフィールドを入力してください。'
    ]
];
$txt = $t[$lang];

$message = '';
$passwordMessage = '';
$passwordError = false;

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordMessage = $txt['password_required'];
        $passwordError = true;
    } elseif ($newPassword !== $confirmPassword) {
        $passwordMessage = $txt['password_mismatch'];
        $passwordError = true;
    } else {
        // Verify current password
        $adminId = $_SESSION['admin_id'];
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($currentPassword, $admin['password_hash'])) {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $adminId]);
            $passwordMessage = $txt['password_updated'];
        } else {
            $passwordMessage = $txt['current_password_wrong'];
            $passwordError = true;
        }
    }
}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updates = [
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_user' => $_POST['smtp_user'],
        'smtp_pass' => $_POST['smtp_pass'],
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
            
            <!-- System Settings Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0"><?php echo $txt['system_settings']; ?></h4>
                </div>
                <div class="card-body">
                    <?php if($message): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="save_settings" value="1">
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
                            <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($current['smtp_pass']); ?>">
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

            <!-- Password Change Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><?php echo $txt['change_password']; ?></h4>
                </div>
                <div class="card-body">
                    <?php if($passwordMessage): ?>
                        <div class="alert <?php echo $passwordError ? 'alert-danger' : 'alert-success'; ?>">
                            <?php echo htmlspecialchars($passwordMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['current_password']; ?></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['new_password']; ?></label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo $txt['confirm_password']; ?></label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-warning"><?php echo $txt['update_password']; ?></button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
