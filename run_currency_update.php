<?php
/**
 * Execute Currency and Payment System Update
 */

require_once __DIR__ . '/helpers/Database.php';

echo "=== Updating Currency and Payment System ===\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🔄 Updating payment currency to GHS...\n";
    $stmt = $db->prepare("UPDATE app_settings SET setting_value = 'GHS' WHERE setting_name = 'payment_currency'");
    $stmt->execute();
    echo "✅ Payment currency updated\n";
    
    echo "🔄 Updating AI model to Kimi...\n";
    $stmt = $db->prepare("UPDATE app_settings SET setting_value = 'moonshotai/kimi-k2:free' WHERE setting_name = 'selected_ai_model'");
    $stmt->execute();
    echo "✅ AI model updated\n";
    
    echo "🔄 Updating premium tier pricing...\n";
    $stmt = $db->prepare("UPDATE membership_tiers SET price_annual = 100.00, price_monthly = 10.00 WHERE name = 'premium'");
    $stmt->execute();
    echo "✅ Premium tier pricing updated\n";
    
    echo "🔄 Ensuring admin users have premium membership...\n";
    $stmt = $db->prepare("UPDATE users SET current_tier_id = 2 WHERE role = 'admin' AND current_tier_id = 1");
    $result = $stmt->execute();
    $affected = $stmt->rowCount();
    echo "✅ {$affected} admin user(s) upgraded to premium\n";
    
    echo "\n🎉 Currency and payment system update completed successfully!\n";
    echo "✅ Currency: Ghana Cedis (GHS)\n";
    echo "✅ Premium plan: GHS 100/year\n";
    echo "✅ AI model: Kimi K2 Free\n";
    echo "✅ Admin users: Premium membership\n";
    
} catch (Exception $e) {
    echo "❌ Update failed: " . $e->getMessage() . "\n";
}

echo "\n=== Update Complete ===\n";
?>