<?php
// #############################################################################################
// #
// # COMPOSER INSTALL SCRIPT FOR SHARED HOSTING
// #
// #############################################################################################
// #
// # This script is designed to allow you to run 'composer install' on a shared web host
// # where you do not have SSH or command-line access.
// #
// # SECURITY WARNING:
// # This file is a MAJOR security risk if left on a public server.
// # 1. CHANGE the SECRET_KEY to something long and random.
// # 2. DELETE THIS FILE from your server as soon as you are done.
// #
// #############################################################################################

// --- SECURITY ---
// Set a secret key to prevent unauthorized access.
// *** CHANGE THIS TO A LONG, RANDOM STRING ***
define('SECRET_KEY', 'CHANGE_THIS_SECRET_KEY');

// --- CONFIGURATION ---
// Set the path to your composer.phar file.
$composer_phar_path = __DIR__ . '/composer.phar';

// Set the path to your composer.json file.
$composer_json_path = __DIR__ . '/composer.json';

// --- SCRIPT LOGIC ---

// Check for the secret key in the URL
if (!isset($_GET['key']) || $_GET['key'] !== SECRET_KEY) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1>";
    echo "<p>Access denied. You must provide the correct secret key.</p>";
    exit;
}

// Check if composer.phar exists
if (!file_exists($composer_phar_path)) {
    die("<h1>Error</h1><p>The 'composer.phar' file was not found. Please make sure it is in the same directory as this script.</p>");
}

// Check if composer.json exists
if (!file_exists($composer_json_path)) {
    die("<h1>Error</h1><p>The 'composer.json' file was not found.</p>");
}

// Set a longer execution time and more memory to prevent timeouts
@set_time_limit(300); // 5 minutes
@ini_set('memory_limit', '512M');

// Set up the environment for Composer
putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');
putenv('COMPOSER_CACHE_DIR=' . __DIR__ . '/.composer/cache');

// Prepare the command to be executed
// We use 'php' to execute the PHAR file.
// '--no-dev' is recommended for production environments.
// '--optimize-autoloader' creates a faster autoloader for production.
$command = 'php ' . escapeshellarg($composer_phar_path) . ' install --no-interaction --no-ansi --no-dev --optimize-autoloader';

// --- EXECUTION & OUTPUT ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Composer Install</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; background-color: #f8f9fa; color: #212529; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #007bff; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        pre { background: #e9ecef; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Composer Installer</h1>
        <p>Attempting to run the following command:</p>
        <pre><?php echo htmlspecialchars($command); ?></pre>
        <hr>
        <h2>Output:</h2>
        <pre><?php
            // Execute the command and capture the output
            $output = shell_exec($command . ' 2>&1'); // '2>&1' merges errors into the output

            if ($output !== null) {
                echo htmlspecialchars($output);
                echo "\n\n<p class='success'>Script finished.</p>";
            } else {
                echo "<p class='error'>Execution failed. 'shell_exec' might be disabled on this server.</p>";
            }
        ?></pre>
        <hr>
        <p class="warning">
            IMPORTANT: For security reasons, please delete this file (composer-install.php) from your server immediately!
        </p>
    </div>
</body>
</html>
