<?php
// config/config.php

// Include Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Configuration file for Promptash

// Check if application is installed
if (!file_exists(__DIR__ . '/database.php')) {
    header('Location: install/index.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/database.php';

// Include security helper
require_once __DIR__ . '/../helpers/Security.php';

// Set security headers
Security::setSecurityHeaders();

// --- REMOVED STATIC DEFINES ---
// define('APP_NAME', 'Promptash');
define('APP_VERSION', '1.0.0'); // Version can remain static for now
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));

// Derive the relying party domain for passkey support.
if (!defined('APP_DOMAIN')) {
    $host = parse_url(APP_URL, PHP_URL_HOST);
    if (!$host) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    }
    define('APP_DOMAIN', $host);
}


// Security settings
define('SESSION_LIFETIME', 31536000); // 1 year
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Start session with security settings
if (session_status() == PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    // Set the session cookie to live for the same duration as the session
    session_set_cookie_params(SESSION_LIFETIME);

    session_start();
}

// Set timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- ADDED DYNAMIC SETTINGS LOADER ---
// Autoload AppSettings model if installation is complete
if (file_exists(__DIR__ . '/../app/models/AppSettings.php')) {
    require_once __DIR__ . '/../helpers/Database.php';
    require_once __DIR__ . '/../app/models/AppSettings.php';
    
    try {
        // Only initialize AppSettings if we're not in install mode
        // and database configuration exists
        if (file_exists(__DIR__ . '/database.php')) {
            $appSettings = new AppSettings();
            
            // If database connection failed, set to null
            if (!$appSettings || $appSettings->getSetting('test') === null) {
                // Test if we can actually query the database
                // If not, we're probably still setting up
            }
        } else {
            $appSettings = null;
        }
    } catch (Exception $e) {
        // If database connection fails during installation or configuration
        error_log("AppSettings initialization failed: " . $e->getMessage());
        $appSettings = null;
    }
} else {
    $appSettings = null;
}
?>
