<?php
/**
 * Fix User Tiers - Ensure all users have a valid current_tier_id
 * 
 * This script fixes users who don't have a current_tier_id assigned,
 * which causes the usage tracking system to fail.
 */

// Include the database configuration
require_once __DIR__ . '/../helpers/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== User Tier Fix Script ===\n\n";
    
    // First, check for users without current_tier_id
    $checkQuery = "SELECT id, username, email, current_tier_id FROM users WHERE current_tier_id IS NULL OR current_tier_id = 0";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute();
    $usersWithoutTier = $checkStmt->fetchAll();
    
    echo "Found " . count($usersWithoutTier) . " users without proper tier assignment:\n";
    
    if (count($usersWithoutTier) > 0) {
        foreach ($usersWithoutTier as $user) {
            echo "- User ID {$user['id']}: {$user['username']} ({$user['email']}) - current_tier_id: " . ($user['current_tier_id'] ?? 'NULL') . "\n";
        }
        
        echo "\nFixing tier assignments...\n";
        
        // Update users to have free tier (ID: 1)
        $fixQuery = "UPDATE users SET current_tier_id = 1 WHERE current_tier_id IS NULL OR current_tier_id = 0";
        $fixStmt = $db->prepare($fixQuery);
        $result = $fixStmt->execute();
        
        if ($result) {
            $affectedRows = $fixStmt->rowCount();
            echo "✅ Successfully updated {$affectedRows} users to free tier (ID: 1)\n";
        } else {
            echo "❌ Failed to update user tiers\n";
        }
    } else {
        echo "✅ All users already have valid tier assignments\n";
    }
    
    // Verify the fix
    echo "\nVerifying fix...\n";
    $verifyQuery = "SELECT COUNT(*) as count FROM users WHERE current_tier_id IS NULL OR current_tier_id = 0";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute();
    $result = $verifyStmt->fetch();
    
    if ($result['count'] == 0) {
        echo "✅ All users now have valid tier assignments\n";
    } else {
        echo "❌ Warning: {$result['count']} users still don't have tier assignments\n";
    }
    
    // Show current tier distribution
    echo "\nCurrent tier distribution:\n";
    $distQuery = "SELECT mt.name, mt.display_name, COUNT(u.id) as user_count 
                  FROM membership_tiers mt 
                  LEFT JOIN users u ON u.current_tier_id = mt.id 
                  GROUP BY mt.id, mt.name, mt.display_name 
                  ORDER BY mt.id";
    $distStmt = $db->prepare($distQuery);
    $distStmt->execute();
    $distribution = $distStmt->fetchAll();
    
    foreach ($distribution as $tier) {
        echo "- {$tier['display_name']} ({$tier['name']}): {$tier['user_count']} users\n";
    }
    
    echo "\n=== Fix completed ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>