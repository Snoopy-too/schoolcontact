<?php
date_default_timezone_set('Asia/Tokyo');
/**
 * GitHub Webhook Deployment Script
 *
 * This script receives GitHub webhooks and automatically deploys your app
 * to your hosting account.
 *
 * SECURITY: Configuration is loaded from config/.env.deploy
 * Never commit .env.deploy to version control!
 */

// ===== CONFIGURATION =====
// Load secure deployment configuration
$deploy_config = __DIR__ . '/config/deploy_config.php';
if (!file_exists($deploy_config)) {
    // Fallback check for old .env style
    if (file_exists(__DIR__ . '/config/.env.deploy')) {
        require_once __DIR__ . '/config/.env.deploy';
    } else {
        http_response_code(500);
        die('Deployment configuration not found. Please upload config/deploy_config.php');
    }
} else {
    require_once $deploy_config;
}

// Use constants from config file
$GITHUB_SECRET = GITHUB_WEBHOOK_SECRET;
$GITHUB_OWNER = GITHUB_OWNER;
$GITHUB_REPO = GITHUB_REPO;
$GITHUB_BRANCH = GITHUB_BRANCH;

// Directories to preserve (won't be overwritten)
// config.php is preserved so database credentials aren't overwritten
$PRESERVE_FILES = ['config.php'];
$PRESERVE_DIRS = ['config']; // Preserve config folder (for .env.deploy/deploy_config.php)

// ===== END CONFIGURATION =====

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set up logging
$log_file = __DIR__ . '/deploy.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function verify_github_webhook($secret, $payload, $signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($hash, $signature);
}

function send_response($code, $message) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => ($code === 200 ? 'success' : 'error'), 'message' => $message]);
    exit;
}

try {
    // First, create initial log entry to confirm script is running
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Deployment script executed\n", FILE_APPEND);

    log_message('Webhook received from GitHub');
    // log_message('Headers: ' . json_encode(getallheaders())); // Optional: might leak info if logged publicly
    log_message('Request method: ' . $_SERVER['REQUEST_METHOD']);

    // Get the raw POST data
    $payload = file_get_contents('php://input');
    log_message('Payload size: ' . strlen($payload) . ' bytes');

    if (empty($payload)) {
        log_message('Error: No payload received');
        send_response(400, 'No payload received');
    }

    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (!verify_github_webhook($GITHUB_SECRET, $payload, $signature)) {
        log_message('Invalid webhook signature - verification failed');
        send_response(403, 'Invalid signature');
    }

    log_message('Webhook signature verified successfully');

    // Parse the JSON payload
    $data = json_decode($payload, true);

    if (!$data) {
        send_response(400, 'Invalid JSON payload');
    }

    // Check if this is a push event on the main branch
    // Note: GitHub sends refs/heads/master or refs/heads/main
    $ref = $data['ref'] ?? '';
    if ($ref !== "refs/heads/$GITHUB_BRANCH") {
        log_message("Push to different branch received: $ref, ignoring. Expected: refs/heads/$GITHUB_BRANCH");
        send_response(200, "Not $GITHUB_BRANCH branch, ignoring");
    }

    log_message('Valid webhook received for main branch');

    // Get the commit info
    $commit = $data['head_commit'] ?? [];
    $author = $commit['author']['name'] ?? 'Unknown';
    $message = $commit['message'] ?? 'No message';

    log_message("Deployment triggered by: $author - $message");

    // Download and extract the latest release
    $download_url = "https://github.com/$GITHUB_OWNER/$GITHUB_REPO/archive/refs/heads/$GITHUB_BRANCH.zip";
    $temp_file = tempnam(sys_get_temp_dir(), 'deploy_');
    $extract_dir = sys_get_temp_dir() . '/deploy_extract_' . time();

    log_message("Downloading from: $download_url");

    // Download the repository as ZIP
    $ch = curl_init($download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DeployScript/1.0'); // GitHub API requires User Agent
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $zip_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || empty($zip_content)) {
        log_message("Failed to download repository. HTTP Code: $http_code. Error: $error");
        send_response(500, 'Failed to download repository');
    }

    // Save the ZIP file
    if (file_put_contents($temp_file, $zip_content) === false) {
        log_message("Failed to save temporary ZIP file");
        send_response(500, 'Failed to save temporary file');
    }

    log_message("ZIP file downloaded, size: " . filesize($temp_file) . " bytes");

    // Extract the ZIP file
    if (!mkdir($extract_dir, 0755, true)) {
        log_message("Failed to create extraction directory: $extract_dir");
        send_response(500, 'Failed to create extraction directory');
    }

    $zip = new ZipArchive();
    if ($zip->open($temp_file) === TRUE) {
        $zip->extractTo($extract_dir);
        $zip->close();
        log_message("ZIP extracted successfully");
    } else {
        log_message("Failed to open ZIP file");
        send_response(500, 'Failed to open ZIP file');
    }

    // Find the extracted folder (GitHub appends -branchname to the folder)
    $extracted_files = scandir($extract_dir);
    $source_dir = null;

    foreach ($extracted_files as $file) {
        if ($file !== '.' && $file !== '..' && is_dir("$extract_dir/$file")) {
            $source_dir = "$extract_dir/$file";
            break;
        }
    }

    if (!$source_dir) {
        log_message("Could not find extracted directory inside ZIP");
        send_response(500, 'Extraction failed - no directory found');
    }

    log_message("Source directory determined: $source_dir");

    // Get current app directory
    $app_dir = dirname(__FILE__);

    // Copy files from source to app directory, skipping preserved files/dirs
    log_message("Copying files from $source_dir to $app_dir");
    
    // Recursive copy function with filtering
    copy_dir_selective($source_dir, $app_dir, $PRESERVE_DIRS, $PRESERVE_FILES);

    // Clean up temporary files
    @unlink($temp_file);
    remove_dir($extract_dir);

    log_message('Deployment completed successfully');
    send_response(200, 'Deployment successful');

} catch (Exception $e) {
    log_message('Error: ' . $e->getMessage());
    send_response(500, 'Deployment failed: ' . $e->getMessage());
}

/**
 * Recursively copy directory, excluding certain directories and files
 */
function copy_dir_selective($src, $dst, $exclude_dirs, $exclude_files) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);

    while (false !== ($file = readdir($dir))) {
        if ($file != "." && $file != "..") {
            
            // Check if file is in exclude list
            if (in_array($file, $exclude_files)) {
                // log_message("Skipping preserved file: $file"); // Verbose
                continue;
            }

            // Check if directory is in exclude list
            if (is_dir("$src/$file") && in_array($file, $exclude_dirs)) {
                // log_message("Skipping preserved directory: $file"); // Verbose
                continue;
            }

            if (is_dir("$src/$file")) {
                copy_dir_selective("$src/$file", "$dst/$file", $exclude_dirs, $exclude_files);
            } else {
                copy("$src/$file", "$dst/$file");
            }
        }
    }
    closedir($dir);
}

/**
 * Recursively remove directory
 */
function remove_dir($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? remove_dir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
?>
