<?php
/**
 * Notification Processing Cron Job
 * 
 * This script should be run periodically (recommended: every hour) to:
 * 1. Check and send usage notifications for all users
 * 2. Send subscription expiry warnings
 * 3. Clean up old notifications
 * 
 * Usage:
 * php cron_notifications.php
 * 
 * Or via cron (every hour):
 * 0 * * * * /usr/bin/php /path/to/cron_notifications.php
 */

// Ensure this script is run from command line only
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('This script can only be run from the command line.');
}

// Start output buffering to capture all output
ob_start();

echo "=== Promptash Notification Processing Cron Job ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Include required files
    require_once __DIR__ . '/helpers/Database.php';
    require_once __DIR__ . '/helpers/NotificationService.php';
    
    // Initialize services
    $notificationService = new NotificationService();
    
    echo "📧 Processing usage notifications for all users...\n";
    
    // Process usage notifications for all users
    $usage_notifications = $notificationService->processAllUsageNotifications();
    echo "   ✅ Sent {$usage_notifications} usage notifications\n\n";
    
    echo "📅 Processing subscription expiry notifications...\n";
    
    // Process subscription expiry notifications
    $expiry_notifications = $notificationService->processSubscriptionExpiryNotifications();
    echo "   ✅ Sent {$expiry_notifications} subscription expiry notifications\n\n";
    
    echo "🧹 Cleaning up old notifications...\n";
    
    // Clean up old notifications (older than 30 days)
    $cleaned_notifications = $notificationService->cleanupOldNotifications();
    echo "   ✅ Cleaned up {$cleaned_notifications} old notifications\n\n";
    
    // Summary
    $total_notifications = $usage_notifications + $expiry_notifications;
    echo "📊 Summary:\n";
    echo "   • Usage notifications sent: {$usage_notifications}\n";
    echo "   • Expiry notifications sent: {$expiry_notifications}\n";
    echo "   • Total notifications sent: {$total_notifications}\n";
    echo "   • Old notifications cleaned: {$cleaned_notifications}\n\n";
    
    echo "✅ Notification processing completed successfully!\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error during notification processing:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n\n";
    
    // Log the error
    error_log("Cron notification processing error: " . $e->getMessage());
    
    exit(1);
}

// Get the output and log it
$output = ob_get_contents();
ob_end_clean();

// Log the output
$log_file = __DIR__ . '/logs/cron_notifications.log';
$log_dir = dirname($log_file);

// Create logs directory if it doesn't exist
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Append to log file
file_put_contents($log_file, $output, FILE_APPEND | LOCK_EX);

// Also output to console
echo $output;

// Exit with success
exit(0);
?>