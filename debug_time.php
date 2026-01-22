<?php
require 'config.php';

echo "PHP Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Timezone: " . date_default_timezone_get() . "\n";

$stmt = $pdo->query("SELECT NOW() as mysql_now, @@session.time_zone as session_tz, @@global.time_zone as global_tz");
$res = $stmt->fetch();

echo "MySQL NOW(): " . $res['mysql_now'] . "\n";
echo "MySQL Session TZ: " . $res['session_tz'] . "\n";
echo "MySQL Global TZ: " . $res['global_tz'] . "\n";

// Check if DATE_SUB works correctly
$stmt = $pdo->query("SELECT DATE_SUB('2026-01-22 11:00:00', INTERVAL 0 HOUR) > NOW() as is_future");
$is_future = $stmt->fetchColumn();
echo "Is 11:00 AM still future? " . ($is_future ? 'YES' : 'NO') . "\n";
