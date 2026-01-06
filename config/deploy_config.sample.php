<?php
// config/deploy_config.php
// Configuration for Auto-Deployment
// Upload this file to your server in the 'config/' directory.

// GitHub Repository Details
define('GITHUB_OWNER', 'YOUR_GITHUB_USERNAME');
define('GITHUB_REPO', 'YOUR_REPO_NAME');
define('GITHUB_BRANCH', 'main');

// Security Secret
// Generate a strong random string (e.g. `openssl rand -hex 20`)
// You must enter this EXACT SAME string in your GitHub Repo -> Settings -> Webhooks -> Secret
define('GITHUB_WEBHOOK_SECRET', 'YOUR_RANDOM_SECRET_KEY_HERE');
?>
