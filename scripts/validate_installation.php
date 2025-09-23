<?php
/**
 * Database Validation Script
 * 
 * This script verifies that all required tables and triggers exist for a complete installation.
 * Run this after installation to ensure everything is properly set up.
 */

// Include required files
require_once __DIR__ . '/../helpers/Database.php';

// Required tables for the application
$required_tables = [
    'membership_tiers' => 'Membership system',
    'users' => 'User management',
    'categories' => 'Prompt categories',
    'prompts' => 'Prompt storage',
    'user_subscriptions' => 'Subscription tracking',
    'usage_tracking' => 'Usage monitoring',
    'payment_transactions' => 'Payment processing',
    'password_reset_tokens' => 'Password resets',
    'app_settings' => 'Application settings',
    'user_settings' => 'User preferences',
    'shared_prompts' => 'Prompt sharing',
    'usage_notifications' => 'Usage alerts',
    'user_notifications' => 'In-app notifications'
];

// Required columns for critical tables
$required_columns = [
    'users' => ['current_tier_id', 'two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes'],
    'membership_tiers' => ['max_prompts_per_month', 'max_ai_generations_per_month', 'max_categories'],
    'usage_tracking' => ['usage_type', 'usage_month', 'usage_count']
];

// Required triggers
$required_triggers = [
    'auto_upgrade_admin_to_premium',
    'upgrade_existing_user_to_admin'
];

// Required app settings
$required_settings = [
    'membership_enabled',
    'paystack_public_key',
    'paystack_secret_key',
    'payment_currency'
];

try {
    echo "=== Promptash Database Validation ===\n\n";
    
    $database = new Database();
    $db = $database->getConnection();
    
    $errors = 0;
    $warnings = 0;
    
    // Check tables
    echo "🔍 Checking required tables...\n";
    foreach ($required_tables as $table => $description) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ $table ($description)\n";
        } else {
            echo "❌ $table ($description) - MISSING\n";
            $errors++;
        }
    }
    
    // Check columns
    echo "\n🔍 Checking critical columns...\n";
    foreach ($required_columns as $table => $columns) {
        $query = "DESCRIBE $table";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        foreach ($columns as $column) {
            if (in_array($column, $existing_columns)) {
                echo "✅ $table.$column\n";
            } else {
                echo "❌ $table.$column - MISSING\n";
                $errors++;
            }
        }
    }
    
    // Check triggers
    echo "\n🔍 Checking database triggers...\n";
    foreach ($required_triggers as $trigger) {
        $query = "SHOW TRIGGERS WHERE Trigger = '$trigger'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ $trigger\n";
        } else {
            echo "⚠️ $trigger - MISSING (admins won't auto-upgrade to premium)\n";
            $warnings++;
        }
    }
    
    // Check membership tiers
    echo "\n🔍 Checking membership tiers...\n";
    $query = "SELECT name, display_name FROM membership_tiers ORDER BY sort_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $tiers = $stmt->fetchAll();
    
    if (count($tiers) >= 2) {
        foreach ($tiers as $tier) {
            echo "✅ {$tier['display_name']} ({$tier['name']})\n";
        }
    } else {
        echo "❌ Insufficient membership tiers (found " . count($tiers) . ", need at least 2)\n";
        $errors++;
    }
    
    // Check app settings
    echo "\n🔍 Checking app settings...\n";
    $query = "SELECT setting_key FROM app_settings WHERE setting_key IN ('" . implode("','", $required_settings) . "')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $existing_settings = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    foreach ($required_settings as $setting) {
        if (in_array($setting, $existing_settings)) {
            echo "✅ $setting\n";
        } else {
            echo "❌ $setting - MISSING\n";
            $errors++;
        }
    }
    
    // Check for admin users
    echo "\n🔍 Checking admin users...\n";
    $query = "SELECT id, username, email, current_tier_id, 
                     (SELECT name FROM membership_tiers WHERE id = users.current_tier_id) as tier_name
              FROM users WHERE role = 'admin' LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    if (count($admins) > 0) {
        foreach ($admins as $admin) {
            $tier_status = ($admin['tier_name'] === 'premium') ? '✅' : '⚠️';
            echo "$tier_status Admin: {$admin['username']} ({$admin['email']}) - Tier: {$admin['tier_name']}\n";
            if ($admin['tier_name'] !== 'premium') {
                $warnings++;
            }
        }
    } else {
        echo "❌ No admin users found\n";
        $errors++;
    }
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 VALIDATION SUMMARY:\n";
    
    if ($errors == 0 && $warnings == 0) {
        echo "🎉 EXCELLENT! All checks passed. Your installation is complete and ready for production.\n";
    } elseif ($errors == 0) {
        echo "✅ GOOD! Core installation is complete. You have $warnings warning(s) that should be addressed:\n";
        if ($warnings > 0) {
            echo "   - Run the admin upgrade script if needed: scripts/upgrade_admin_users.php\n";
        }
    } else {
        echo "❌ ISSUES FOUND! You have $errors critical error(s) and $warnings warning(s).\n";
        echo "   Please re-run the installation or import the complete database schema.\n";
        echo "   Use: mysql -u username -p database_name < database_complete.sql\n";
    }
    
    echo "\n📋 Next steps:\n";
    echo "   1. Complete the installation wizard at /install/\n";
    echo "   2. Delete the /install/ directory after setup\n";
    echo "   3. Configure Paystack keys in App Settings (if using payments)\n";
    echo "   4. Set up your admin account and start using the application\n";
    
} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
    echo "   Please check your database configuration in config/database.php\n";
    exit(1);
}

echo "\n🎯 Validation completed!\n";
?>