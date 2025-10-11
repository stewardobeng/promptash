<?php
/**
 * Complete Installation Verification Script
 * This script validates the entire Promptash installation
 */

require_once 'helpers/Database.php';
require_once 'app/models/User.php';
require_once 'app/models/MembershipTier.php';
require_once 'app/models/Prompt.php';
require_once 'app/models/Category.php';

echo "<h2>Promptash Installation Verification</h2>";

$errors = [];
$warnings = [];
$success = [];

try {
    // Test database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        $success[] = "✅ Database connection: SUCCESS";
        
        // Required tables for complete functionality
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
        
        // Check tables exist
        foreach ($required_tables as $table => $description) {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $success[] = "✅ Table '$table': EXISTS ($description)";
            } else {
                $errors[] = "❌ Table '$table': MISSING ($description)";
            }
        }
        
        // Test membership tiers
        $stmt = $conn->prepare("SELECT id, name, display_name, max_prompts_per_month FROM membership_tiers ORDER BY sort_order");
        $stmt->execute();
        $tiers = $stmt->fetchAll();
        
        if (count($tiers) >= 2) {
            $success[] = "✅ Membership tiers: " . count($tiers) . " found";
            foreach ($tiers as $tier) {
                $prompts_limit = $tier['max_prompts_per_month'] == 0 ? 'Unlimited' : $tier['max_prompts_per_month'];
                $success[] = "   • {$tier['display_name']} ({$tier['name']}): {$prompts_limit} prompts/month";
            }
        } else {
            $errors[] = "❌ Insufficient membership tiers (found " . count($tiers) . ", need at least 2)";
        }
        
        // Test app settings
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM app_settings");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result['count'] >= 15) {
            $success[] = "✅ Application settings: " . $result['count'] . " configured";
        } else {
            $warnings[] = "⚠️ Limited app settings (found " . $result['count'] . ", expected 15+)";
        }
        
        // Test model functionality
        try {
            $tierModel = new MembershipTier();
            $personalTier = $tierModel->getPersonalTier();
            $premiumTier = $tierModel->getPremiumTier();
            
            if ($personalTier && $premiumTier) {
                $success[] = "✅ MembershipTier model: Working correctly";
                $success[] = "   • Personal tier: {$personalTier['display_name']} (ID: {$personalTier['id']})";
                $success[] = "   • Premium tier: {$premiumTier['display_name']} (ID: {$premiumTier['id']})";
            } else {
                $errors[] = "❌ MembershipTier model: Cannot retrieve tier data";
            }
        } catch (Exception $e) {
            $errors[] = "❌ MembershipTier model error: " . $e->getMessage();
        }
        
        // Test critical foreign key relationships
        $foreign_keys = [
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'promptash' AND CONSTRAINT_TYPE = 'FOREIGN KEY'" => "Foreign key constraints"
        ];
        
        foreach ($foreign_keys as $query => $description) {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $fk_count = $stmt->rowCount();
            if ($fk_count >= 10) {
                $success[] = "✅ Database integrity: {$fk_count} foreign key constraints";
            } else {
                $warnings[] = "⚠️ Limited foreign key constraints: {$fk_count} found";
            }
        }
        
        // Test critical indexes for performance
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = 'promptash' AND INDEX_NAME != 'PRIMARY'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result['count'] >= 20) {
            $success[] = "✅ Database performance: " . $result['count'] . " indexes created";
        } else {
            $warnings[] = "⚠️ Limited database indexes: " . $result['count'] . " found";
        }
        
        // Test character set and collation
        $stmt = $conn->prepare("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'promptash'");
        $stmt->execute();
        $charset_info = $stmt->fetch();
        
        if ($charset_info) {
            if ($charset_info['DEFAULT_CHARACTER_SET_NAME'] === 'utf8mb4' && 
                strpos($charset_info['DEFAULT_COLLATION_NAME'], 'utf8mb4') === 0) {
                $success[] = "✅ Database encoding: UTF8MB4 with proper collation";
            } else {
                $warnings[] = "⚠️ Database encoding: " . $charset_info['DEFAULT_CHARACTER_SET_NAME'] . " (" . $charset_info['DEFAULT_COLLATION_NAME'] . ")";
            }
        }
        
        echo "<h3>Installation Summary</h3>";
        
        if (!empty($success)) {
            echo "<h4 style='color: green;'>✅ Successful Checks (" . count($success) . ")</h4>";
            foreach ($success as $msg) {
                echo "<p style='color: green; margin: 2px 0;'>{$msg}</p>";
            }
        }
        
        if (!empty($warnings)) {
            echo "<h4 style='color: orange;'>⚠️ Warnings (" . count($warnings) . ")</h4>";
            foreach ($warnings as $msg) {
                echo "<p style='color: orange; margin: 2px 0;'>{$msg}</p>";
            }
        }
        
        if (!empty($errors)) {
            echo "<h4 style='color: red;'>❌ Errors (" . count($errors) . ")</h4>";
            foreach ($errors as $msg) {
                echo "<p style='color: red; margin: 2px 0;'>{$msg}</p>";
            }
        }
        
        // Overall status
        echo "<h3>Overall Status</h3>";
        if (empty($errors)) {
            if (empty($warnings)) {
                echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 PERFECT INSTALLATION! All systems operational.</p>";
            } else {
                echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ INSTALLATION SUCCESSFUL with minor warnings.</p>";
            }
            echo "<h4>Next Steps:</h4>";
            echo "<ol>";
            echo "<li><strong>Complete Installation:</strong> <a href='/storeprompts/install/' target='_blank'>Run Installation Wizard</a></li>";
            echo "<li><strong>Create Admin Account:</strong> Use the installation wizard to create your admin user</li>";
            echo "<li><strong>Configure Settings:</strong> Set up payment keys and AI integration in admin panel</li>";
            echo "<li><strong>Test Features:</strong> Create prompts, categories, and test sharing functionality</li>";
            echo "</ol>";
        } else {
            echo "<p style='color: red; font-weight: bold; font-size: 18px;'>❌ INSTALLATION INCOMPLETE - Please fix errors above</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Database connection: FAILED</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Verification Error: " . $e->getMessage() . "</p>";
}

// Add some styling
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }";
echo "h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }";
echo "h3 { color: #555; margin-top: 30px; }";
echo "h4 { margin-bottom: 10px; }";
echo "p { margin: 5px 0; }";
echo "ol { margin-left: 20px; }";
echo "a { color: #007cba; text-decoration: none; }";
echo "a:hover { text-decoration: underline; }";
echo "</style>";
?>
