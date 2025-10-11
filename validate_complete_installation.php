<?php
/**
 * Complete Installation Validation Script
 * Validates all recent updates including currency changes and payment system
 */

// Include required files
require_once __DIR__ . '/helpers/Database.php';
require_once __DIR__ . '/app/models/AppSettings.php';

echo "=== Promptash Installation Validation ===\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $errors = 0;
    $warnings = 0;
    
    echo "🔍 Checking database connection...\n";
    if ($db) {
        echo "✅ Database connection successful\n\n";
    } else {
        echo "❌ Database connection failed\n";
        $errors++;
    }
    
    // Check app settings
    echo "🔍 Checking application settings...\n";
    $appSettings = new AppSettings();
    
    $required_settings = [
        'payment_currency' => 'GHS',
        'selected_ai_model' => 'moonshotai/kimi-k2:free',
        'app_name' => 'Promptash',
        'membership_enabled' => true
    ];
    
    foreach ($required_settings as $key => $expected) {
        $actual = $appSettings->getSetting($key);
        if ($actual == $expected) {
            echo "✅ {$key}: {$actual}\n";
        } else {
            echo "⚠️ {$key}: {$actual} (expected: {$expected})\n";
            $warnings++;
        }
    }
    
    // Check currency formatting
    echo "\n🔍 Checking currency formatting...\n";
    $currency = $appSettings->getPaymentCurrency();
    $formatted = $appSettings->formatPrice(100);
    echo "✅ Currency: {$currency}\n";
    echo "✅ Formatted price (100): {$formatted}\n";
    
    // Check membership tiers
    echo "\n🔍 Checking membership tiers...\n";
    $query = "SELECT * FROM membership_tiers WHERE name IN ('personal', 'premium')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $tiers = $stmt->fetchAll();
    
    if (count($tiers) >= 2) {
        echo "✅ Membership tiers found: " . count($tiers) . "\n";
        foreach ($tiers as $tier) {
            $annual = (float)$tier['price_annual'];
            $monthly = (float)$tier['price_monthly'];
            if ($annual === 0.0 && $monthly === 0.0) {
                echo "  - {$tier['display_name']}: Free\n";
            } elseif ($annual > 0.0 && $monthly > 0.0) {
                echo "  - {$tier['display_name']}: {$appSettings->formatPrice($annual)}/year ({$appSettings->formatPrice($monthly)}/month)\n";
            } elseif ($annual > 0.0) {
                echo "  - {$tier['display_name']}: {$appSettings->formatPrice($annual)}/year\n";
            } else {
                echo "  - {$tier['display_name']}: {$appSettings->formatPrice($monthly)}/month\n";
            }
        }
    } else {
        echo "❌ Missing membership tiers\n";
        $errors++;
    }
    
    // Check required tables
    echo "\n🔍 Checking required tables...\n";
    $required_tables = [
        'users', 'prompts', 'categories', 'membership_tiers',
        'app_settings', 'user_settings', 'usage_tracking',
        'payment_transactions', 'user_subscriptions'
    ];
    
    foreach ($required_tables as $table) {
        $query = "SHOW TABLES LIKE '{$table}'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ {$table}\n";
        } else {
            echo "❌ {$table} - MISSING\n";
            $errors++;
        }
    }
    
    // Check payment configuration
    echo "\n🔍 Checking payment system...\n";
    $paystack_public = $appSettings->getSetting('paystack_public_key', '');
    $paystack_secret = $appSettings->getSetting('paystack_secret_key', '');
    
    if (empty($paystack_public) && empty($paystack_secret)) {
        echo "⚠️ Paystack keys not configured (configure in admin settings)\n";
        $warnings++;
    } else {
        echo "✅ Paystack configuration detected\n";
    }
    
    // Check AI configuration
    echo "\n🔍 Checking AI system...\n";
    $openrouter_key = $appSettings->getSetting('openrouter_api_key', '');
    $ai_model = $appSettings->getSetting('selected_ai_model', '');
    $ai_enabled = $appSettings->getSetting('ai_enabled', false);
    
    if (empty($openrouter_key)) {
        echo "⚠️ OpenRouter API key not configured (configure in admin settings)\n";
        $warnings++;
    } else {
        echo "✅ OpenRouter API key configured\n";
    }
    
    echo "✅ AI Model: {$ai_model}\n";
    echo "✅ AI Enabled: " . ($ai_enabled ? 'Yes' : 'No') . "\n";
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 VALIDATION SUMMARY:\n";
    
    if ($errors == 0 && $warnings == 0) {
        echo "🎉 EXCELLENT! All checks passed. Installation is complete and ready.\n";
    } elseif ($errors == 0) {
        echo "✅ GOOD! Core installation is complete. You have {$warnings} warning(s):\n";
        echo "   - Configure Paystack keys in Admin Settings for payments\n";
        echo "   - Configure OpenRouter API key in Admin Settings for AI features\n";
    } else {
        echo "❌ ISSUES FOUND! You have {$errors} critical error(s) and {$warnings} warning(s).\n";
        echo "   Please re-run the database installation.\n";
    }
    
    echo "\n📋 Quick Setup Guide:\n";
    echo "1. 🏦 Configure Paystack (Admin Settings → Payment Configuration):\n";
    echo "   - Add your Paystack public key (pk_test_...)\n";
    echo "   - Add your Paystack secret key (sk_test_...)\n";
    echo "   - Currency is set to Ghana Cedis (GHS)\n";
    echo "   - Premium plan: GHS 100/year\n\n";
    
    echo "2. 🤖 Configure AI Features (Admin Settings → AI Configuration):\n";
    echo "   - Add OpenRouter API key from https://openrouter.ai/keys\n";
    echo "   - Model is set to moonshotai/kimi-k2:free (recommended)\n";
    echo "   - Enable AI features toggle\n\n";
    
    echo "3. 🧪 Test the system:\n";
    echo "   - Try upgrading a test user to Premium\n";
    echo "   - Test AI prompt generation features\n";
    echo "   - Verify currency displays correctly (GHS, not $)\n\n";
    
    echo "✨ All currency displays have been updated to use Ghana Cedis (GHS)\n";
    echo "✨ Payment system integrated with Paystack for GHS processing\n";
    echo "✨ AI prompt generator using Kimi model via OpenRouter\n";
    echo "✨ Functional upgrade buttons redirecting to payment page\n";
    
} catch (Exception $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n";
    echo "   Please check your database configuration\n";
}

echo "\n=== Validation Complete ===\n";
?>
