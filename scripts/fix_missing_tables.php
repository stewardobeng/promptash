<?php
/**
 * Fix Missing Tables Script
 * 
 * This script creates any missing tables that weren't imported properly.
 */

echo "=== Fixing Missing Tables ===\n\n";

require_once __DIR__ . '/../helpers/Database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "✅ Database connected\n\n";
    
    // Create missing tables
    $missing_tables = [
        'user_subscriptions' => "
            CREATE TABLE IF NOT EXISTS `user_subscriptions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `tier_id` INT NOT NULL,
                `status` ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'active',
                `billing_cycle` ENUM('monthly', 'annual') DEFAULT 'annual',
                `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NULL,
                `cancelled_at` TIMESTAMP NULL,
                `auto_renew` BOOLEAN DEFAULT TRUE,
                `payment_method` VARCHAR(50) DEFAULT 'paystack',
                `external_subscription_id` VARCHAR(255),
                `last_payment_at` TIMESTAMP NULL,
                `next_payment_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`tier_id`) REFERENCES `membership_tiers`(`id`) ON DELETE RESTRICT,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_tier_id` (`tier_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'payment_transactions' => "
            CREATE TABLE IF NOT EXISTS `payment_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `subscription_id` INT,
                `transaction_type` ENUM('subscription', 'upgrade', 'renewal', 'refund') NOT NULL,
                `payment_method` VARCHAR(50) DEFAULT 'paystack',
                `external_transaction_id` VARCHAR(255) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `currency` VARCHAR(3) DEFAULT 'USD',
                `status` ENUM('pending', 'success', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
                `gateway_response` JSON,
                `processed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_external_transaction_id` (`external_transaction_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'usage_notifications' => "
            CREATE TABLE IF NOT EXISTS `usage_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `notification_type` ENUM('warning_75', 'warning_90', 'limit_reached', 'limit_exceeded') NOT NULL,
                `usage_type` ENUM('prompt_creation', 'ai_generation', 'category_creation') NOT NULL,
                `usage_month` DATE NOT NULL,
                `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `acknowledged_at` TIMESTAMP NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `unique_notification` (`user_id`, `notification_type`, `usage_type`, `usage_month`),
                INDEX `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'user_notifications' => "
            CREATE TABLE IF NOT EXISTS `user_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `type` VARCHAR(50) DEFAULT 'info',
                `action_url` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `read_at` TIMESTAMP NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    foreach ($missing_tables as $table_name => $sql) {
        echo "🔧 Creating table: $table_name...\n";
        try {
            $conn->exec($sql);
            echo "✅ Table $table_name created successfully\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️ Table $table_name already exists\n";
            } else {
                echo "❌ Error creating $table_name: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Create free subscriptions for existing users
    echo "\n🔧 Creating free subscriptions for existing users...\n";
    try {
        $sql = "INSERT IGNORE INTO `user_subscriptions` (`user_id`, `tier_id`, `status`, `billing_cycle`, `started_at`, `auto_renew`)
                SELECT `id`, 1, 'active', 'annual', NOW(), FALSE
                FROM `users`";
        $conn->exec($sql);
        echo "✅ Free subscriptions created for existing users\n";
    } catch (PDOException $e) {
        echo "ℹ️ Subscription creation: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 Database fix completed!\n";
    echo "\n📋 Next steps:\n";
    echo "   1. Open your browser: http://localhost/storeprompts\n";
    echo "   2. Complete the installation wizard\n";
    echo "   3. Create your admin account\n";
    echo "   4. Start testing!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>