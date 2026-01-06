<?php
// deploy_test.php
// Upload this to the same directory as deploy.php and visit it in your browser.
// e.g. https://your-domain.com/deploy_test.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Deployment Diagnostic Tool</h1>";

// 1. Check Permissions
echo "<h3>1. File Permissions</h3>";
$testFile = 'test_write_' . time() . '.txt';
if (@file_put_contents($testFile, 'test') !== false) {
    echo "<div style='color:green'>[PASS] Directory is writable.</div>";
    unlink($testFile);
} else {
    echo "<div style='color:red'>[FAIL] Directory is NOT writable. The web server user cannot save files here.</div>";
}

// 2. Check Prerequisites
echo "<h3>2. Server Prerequisites</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "ZIP Extension: " . (extension_loaded('zip') ? "<span style='color:green'>[PASS] Installed</span>" : "<span style='color:red'>[FAIL] Missing</span>") . "<br>";
echo "CURL Extension: " . (extension_loaded('curl') ? "<span style='color:green'>[PASS] Installed</span>" : "<span style='color:red'>[FAIL] Missing</span>") . "<br>";

// 3. Check Configuration
echo "<h3>3. Configuration File</h3>";
$configFile = __DIR__ . '/config/deploy_config.php';
$oldConfigFile = __DIR__ . '/config/.env.deploy';

if (file_exists($configFile)) {
    echo "<div style='color:green'>[PASS] config/deploy_config.php found.</div>";
    require_once $configFile;
} elseif (file_exists($oldConfigFile)) {
     echo "<div style='color:green'>[PASS] config/.env.deploy found.</div>";
     require_once $oldConfigFile;
} else {
    echo "<div style='color:red'>[FAIL] config/deploy_config.php NOT found.<br>Please upload the config/deploy_config.sample.php file to the 'config' folder and rename it to 'deploy_config.php'.</div>";
}

if (defined('GITHUB_OWNER')) { // Only process if loaded
    
    // Check constants
    $vars = ['GITHUB_OWNER', 'GITHUB_REPO', 'GITHUB_BRANCH', 'GITHUB_WEBHOOK_SECRET'];
    $missingConfig = false;
    foreach ($vars as $v) {
        if (!defined($v) || constant($v) === 'YOUR_...' || constant($v) === '') {
            echo "<div style='color:red'>[FAIL] $v is not set correctly. Value: " . (defined($v) ? constant($v) : 'Not Defined') . "</div>";
            $missingConfig = true;
        } else {
            echo "<div style='color:green'>[PASS] $v is set.</div>";
        }
    }

    // 4. Test Connectivity (Only if config is valid)
    if (!$missingConfig) {
        echo "<h3>4. GitHub Connectivity</h3>";
        $url = "https://github.com/" . GITHUB_OWNER . "/" . GITHUB_REPO . "/archive/refs/heads/" . GITHUB_BRANCH . ".zip";
        echo "Target URL: $url<br>";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // Head request only
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DeployTest/1.0');
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "<div style='color:green'>[PASS] Repository is accessible (HTTP 200).</div>";
        } elseif ($httpCode == 404) {
             echo "<div style='color:red'>[FAIL] Repository not found (HTTP 404). <br><strong>Is your repository PRIVATE?</strong> If so, you need to add an authentication token or make it public. This script currently only supports Public repositories or those authenticating via URL tokens.</div>";
        } else {
             echo "<div style='color:orange'>[WARN] unexpected HTTP Status: $httpCode.</div>";
        }
    }

} else {
    echo "<div style='color:red'>[FAIL] config/.env.deploy NOT found at $configFile</div>";
}

// 5. Check Log File
echo "<h3>5. Log File</h3>";
$logFile = __DIR__ . '/deploy.log';
if (file_exists($logFile)) {
    echo "<div style='color:green'>[INFO] deploy.log exists. Content (last 1000 chars):</div>";
    echo "<pre style='background:#f4f4f4;padding:10px;'>" . htmlspecialchars(substr(file_get_contents($logFile), -1000)) . "</pre>";
} else {
    echo "<div style='color:orange'>[INFO] deploy.log does not exist yet.</div>";
}
?>
