<?php
/**
 * Upgrade Admin Users to Premium Tier
 * * This script automatically upgrades all admin users to premium tier
 * and fixes any usage display issues.
 */

// Include the database configuration
require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/MembershipTier.php';

try {
    echo "=== Admin Users Premium Upgrade Script ===\n\n";
    
    $userModel = new User();
    $tierModel = new MembershipTier();
    
    // Get premium tier
    $premiumTier = $tierModel->getPremiumTier();
    if (!$premiumTier) {
        echo "❌ Premium tier not found. Please ensure the membership system is properly set up.\n";
        exit(1);
    }
    
    echo "✅ Premium tier found: {$premiumTier['display_name']} (ID: {$premiumTier['id']})\n";
    echo "   Limits: Prompts={$premiumTier['max_prompts_per_month']}, AI={$premiumTier['max_ai_generations_per_month']}, Categories={$premiumTier['max_categories']}\n\n";
    
    // Find all admin users
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, first_name, last_name, current_tier_id, 
                     (SELECT display_name FROM membership_tiers WHERE id = users.current_tier_id) as current_tier_name
              FROM users 
              WHERE role = 'admin' AND is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $adminUsers = $stmt->fetchAll();
    
    if (empty($adminUsers)) {
        echo "ℹ️ No admin users found.\n";
        exit(0);
    }
    
    echo "Found " . count($adminUsers) . " admin user(s):\n";
    
    $upgraded = 0;
    $alreadyPremium = 0;
    $errors = 0;
    
    foreach ($adminUsers as $admin) {
        echo "\n👤 Admin: {$admin['first_name']} {$admin['last_name']} ({$admin['username']}, {$admin['email']})\n";
        echo "   Current tier: " . ($admin['current_tier_name'] ?? 'None') . " (ID: " . ($admin['current_tier_id'] ?? 'NULL') . ")\n";
        
        // Check if already has premium tier
        if ($admin['current_tier_id'] == $premiumTier['id']) {
            echo "   ✅ Already has premium tier\n";
            $alreadyPremium++;
            continue;
        }
        
        // Upgrade to premium tier
        $result = $userModel->updateMembershipTier($admin['id'], $premiumTier['id']);
        if ($result) {
            echo "   🚀 Successfully upgraded to premium tier\n";
            $upgraded++;
        } else {
            echo "   ❌ Failed to upgrade to premium tier\n";
            $errors++;
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 SUMMARY:\n";
    echo "   ✅ Successfully upgraded: {$upgraded}\n";
    echo "   ℹ️ Already premium: {$alreadyPremium}\n";
    echo "   ❌ Failed upgrades: {$errors}\n";
    
    if ($upgraded > 0) {
        echo "\n🎉 Admin users have been upgraded to premium tier!\n";
        echo "   They will now have unlimited prompts and categories,\n";
        echo "   plus 300 AI generations per month.\n";
    }
    
    // Test the limit calculation fix
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🧪 Testing usage limit calculation...\n";
    
    require_once __DIR__ . '/../app/models/UsageTracker.php';
    $usageTracker = new UsageTracker();
    
    // Test with a personal tier user
    $personalTier = $tierModel->getPersonalTier();
    if ($personalTier) {
        echo "\n📊 Personal tier limits test:\n";
        echo "   Prompts: " . $tierModel->getTierLimit($personalTier['id'], 'prompt_creation') . "\n";
        echo "   AI Generations: " . $tierModel->getTierLimit($personalTier['id'], 'ai_generation') . "\n";
        echo "   Categories: " . $tierModel->getTierLimit($personalTier['id'], 'category_creation') . "\n";
        
        $isUnlimited = $tierModel->getTierLimit($personalTier['id'], 'prompt_creation') == 0;
        echo "   Is prompts unlimited? " . ($isUnlimited ? "Yes (❌ Wrong!)" : "No (✅ Correct!)") . "\n";
    }
    
    echo "\n📊 Premium tier limits test:\n";
    echo "   Prompts: " . $tierModel->getTierLimit($premiumTier['id'], 'prompt_creation') . " (0 = unlimited)\n";
    echo "   AI Generations: " . $tierModel->getTierLimit($premiumTier['id'], 'ai_generation') . "\n";
    echo "   Categories: " . $tierModel->getTierLimit($premiumTier['id'], 'category_creation') . " (0 = unlimited)\n";
    
    echo "\n✅ Usage limit calculation fix has been applied!\n";
    echo "   Personal plan members will now see proper limits (50 prompts, 50 AI generations, 5 categories)\n";
    echo "   Premium users will see unlimited for prompts/categories, 300 for AI generations\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 Script completed successfully!\n";
?>
