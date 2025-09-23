<?php
/**
 * Upgrade User to Premium by Email
 * * This script allows you to upgrade any user to premium tier by providing their email address.
 * Usage: php upgrade_user_premium.php email@example.com
 */

// Check if email is provided as command line argument or via form
$userEmail = null;

// Command line usage
if (isset($argv[1])) {
    $userEmail = trim($argv[1]);
}

// Web form usage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $userEmail = trim($_POST['email']);
}

// If no email provided and accessed via web, show form
if (!$userEmail && isset($_SERVER['HTTP_HOST'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Upgrade User to Premium</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #005a87; }
            .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>
    </head>
    <body>
        <h1>🚀 Upgrade User to Premium</h1>
        <p>Enter the email address of the user you want to upgrade to premium membership:</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">User Email Address:</label>
                <input type="email" id="email" name="email" required placeholder="user@example.com">
            </div>
            <button type="submit">Upgrade to Premium</button>
        </form>
        
        <hr>
        <p><strong>Note:</strong> This will immediately upgrade the specified user to premium tier with:</p>
        <ul>
            <li>Unlimited prompts per month</li>
            <li>300 AI generations per month</li>
            <li>Unlimited categories</li>
            </ul>
    </body>
    </html>
    <?php
    exit();
}

// If no email provided in CLI mode, show usage
if (!$userEmail) {
    echo "❌ Usage: php upgrade_user_premium.php <email>\n";
    echo "   Example: php upgrade_user_premium.php admin@example.com\n";
    exit(1);
}

// Validate email format
if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    $error = "❌ Invalid email format: {$userEmail}";
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "<div class='alert alert-danger'>{$error}</div>";
        echo "<a href='?'>← Go Back</a>";
        exit();
    } else {
        echo $error . "\n";
        exit(1);
    }
}

// Include required files
require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/MembershipTier.php';

try {
    $output = [];
    $output[] = "=== Premium User Upgrade Script ===";
    $output[] = "";
    
    $userModel = new User();
    $tierModel = new MembershipTier();
    
    // Get premium tier
    $premiumTier = $tierModel->getPremiumTier();
    if (!$premiumTier) {
        throw new Exception("Premium tier not found. Please ensure the membership system is properly set up.");
    }
    
    $output[] = "✅ Premium tier found: {$premiumTier['display_name']} (ID: {$premiumTier['id']})";
    $output[] = "   Limits: Prompts=Unlimited, AI={$premiumTier['max_ai_generations_per_month']}, Categories=Unlimited";
    $output[] = "";
    
    // Find user by email
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, first_name, last_name, role, current_tier_id, is_active,
                     (SELECT display_name FROM membership_tiers WHERE id = users.current_tier_id) as current_tier_name
              FROM users 
              WHERE email = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $userEmail);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found with email: {$userEmail}");
    }
    
    if (!$user['is_active']) {
        throw new Exception("User account is inactive: {$userEmail}");
    }
    
    $output[] = "👤 User found: {$user['first_name']} {$user['last_name']} ({$user['username']})";
    $output[] = "   Email: {$user['email']}";
    $output[] = "   Role: {$user['role']}";
    $output[] = "   Status: " . ($user['is_active'] ? 'Active' : 'Inactive');
    $output[] = "   Current tier: " . ($user['current_tier_name'] ?? 'None') . " (ID: " . ($user['current_tier_id'] ?? 'NULL') . ")";
    $output[] = "";
    
    // Check if already has premium tier
    if ($user['current_tier_id'] == $premiumTier['id']) {
        $output[] = "ℹ️ User already has premium tier!";
        $success = true;
        $message = "User already has premium membership.";
    } else {
        // Upgrade to premium tier
        $result = $userModel->updateMembershipTier($user['id'], $premiumTier['id']);
        if ($result) {
            $output[] = "🚀 Successfully upgraded user to premium tier!";
            $output[] = "";
            $output[] = "✅ {$user['first_name']} {$user['last_name']} now has premium access with:";
            // MODIFICATION START: Changed 500 to 300
            $output[] = "   • Unlimited prompts per month";
            $output[] = "   • 300 AI generations per month";
            $output[] = "   • Unlimited categories";
            // MODIFICATION END
            $output[] = "   • Priority support";
            $success = true;
            $message = "User successfully upgraded to premium!";
        } else {
            throw new Exception("Failed to upgrade user to premium tier. Please check database permissions and try again.");
        }
    }
    
    $output[] = "";
    $output[] = "🎯 Upgrade completed successfully!";
    
    // Output results
    if (isset($_SERVER['HTTP_HOST'])) {
        // Web interface
        $class = $success ? 'alert-success' : 'alert-danger';
        echo "<div class='alert {$class}'>";
        echo "<h3>" . ($success ? "✅ Success!" : "❌ Error!") . "</h3>";
        echo "<p>{$message}</p>";
        if ($success) {
            echo "<h4>User Details:</h4>";
            echo "<p><strong>Name:</strong> {$user['first_name']} {$user['last_name']}<br>";
            echo "<strong>Email:</strong> {$user['email']}<br>";
            echo "<strong>Username:</strong> {$user['username']}<br>";
            echo "<strong>Role:</strong> {$user['role']}<br>";
            echo "<strong>New Tier:</strong> Premium</p>";
        }
        echo "</div>";
        echo "<a href='?'>← Upgrade Another User</a>";
    } else {
        // Command line interface
        foreach ($output as $line) {
            echo $line . "\n";
        }
    }
    
} catch (Exception $e) {
    $error = "❌ Error: " . $e->getMessage();
    
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "<div class='alert alert-danger'>";
        echo "<h3>❌ Error!</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "<a href='?'>← Try Again</a>";
    } else {
        echo $error . "\n";
        exit(1);
    }
}
?>