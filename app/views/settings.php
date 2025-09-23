<?php
$user = $auth->getCurrentUser();
$error = '';
$success = '';

// Initialize models
$settingsModel = new UserSettings();
$userModel = new User();

// Start output buffering for layout
ob_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_openai':
                // OpenAI configuration is now managed by admin
                $error = 'AI configuration is now managed by the administrator. Please contact your admin to enable AI features.';
                break;
                
            case 'save_ai_preferences':
                // AI preferences are now centrally managed by admin
                $error = 'AI preferences are now managed by the administrator. Please contact your admin for AI configuration.';
                break;
                
            case 'save_app_settings':
                // Only allow admin users to modify app settings
                if ($user['role'] !== 'admin') {
                    $error = 'Access denied. Only administrators can modify app settings.';
                    break;
                }
                
                require_once __DIR__ . '/../models/AppSettings.php';
                $appSettingsModel = new AppSettings();
                $current_settings = $appSettingsModel->getAllSettings();

                // Handle sensitive fields to avoid saving masked values
                $openai_api_key = trim($_POST['openai_api_key'] ?? '');
                if (strpos($openai_api_key, '***') !== false) {
                    $openai_api_key = $current_settings['openai_api_key']['value'] ?? '';
                }

                $paystack_secret_key = trim($_POST['paystack_secret_key'] ?? '');
                if (strpos($paystack_secret_key, '•••') !== false) {
                    $paystack_secret_key = $current_settings['paystack_secret_key']['value'] ?? '';
                }
                
                $smtp_password = trim($_POST['smtp_password'] ?? '');
                if (strpos($smtp_password, '•••') !== false) {
                    $smtp_password = $current_settings['smtp_password']['value'] ?? '';
                }

                $app_settings = [
                    'app_name' => trim($_POST['app_name'] ?? ''),
                    'app_description' => trim($_POST['app_description'] ?? ''),
                    'allow_registration' => isset($_POST['allow_registration']) ? 'true' : 'false',
                    'max_prompts_per_user' => (int)($_POST['max_prompts_per_user'] ?? 1000),
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 'true' : 'false',
                    'openai_api_key' => $openai_api_key,
                    'selected_openai_model' => trim($_POST['selected_openai_model'] ?? 'gpt-3.5-turbo'),
                    'ai_enabled' => isset($_POST['ai_enabled']) ? 'true' : 'false',
                    'paystack_public_key' => trim($_POST['paystack_public_key'] ?? ''),
                    'paystack_secret_key' => $paystack_secret_key,
                    'payment_currency' => trim($_POST['payment_currency'] ?? 'GHS'),
                    'email_enabled' => isset($_POST['email_enabled']) ? 'true' : 'false',
                    'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                    'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                    'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                    'smtp_password' => $smtp_password,
                    'smtp_encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
                    'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                    'smtp_from_name' => trim($_POST['smtp_from_name'] ?? 'Promptash')
                ];
                
                $saved = 0;
                foreach ($app_settings as $key => $value) {
                    $type = in_array($key, ['allow_registration', 'maintenance_mode', 'ai_enabled', 'email_enabled']) ? 'boolean' : 
                           (in_array($key, ['max_prompts_per_user', 'smtp_port']) ? 'number' : 'string');
                    
                    if ($appSettingsModel->setSetting($key, (string)$value, $type)) {
                        $saved++;
                    }
                }
                
                if ($saved > 0) {
                    $success = 'App settings saved successfully!';
                } else {
                    $error = 'Failed to save app settings. Please try again.';
                }
                break;
        }
    }
}

// Get current settings
try {
    $current_api_key = $settingsModel->getOpenAIKey($user['id']);
    $ai_preferences = $settingsModel->getAIPreferences($user['id']);
} catch (Exception $e) {
    error_log("Settings page user settings retrieval error: " . $e->getMessage());
    $current_api_key = '';
    $ai_preferences = [];
}

// Get membership information
require_once __DIR__ . '/../models/MembershipTier.php';
require_once __DIR__ . '/../models/UsageTracker.php';
$membershipModel = new MembershipTier();
$usageTracker = new UsageTracker();

// Get user's current tier and usage
try {
    $currentUser = $userModel->getById($user['id']);
    $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
    $allTiers = $membershipModel->getTierComparison();
} catch (Exception $e) {
    error_log("Settings page data retrieval error: " . $e->getMessage());
    $usageSummary = null;
    $allTiers = null;
}

// Safety checks for data integrity
if (!$usageSummary || !isset($usageSummary['tier'])) {
    $usageSummary = [
        'tier' => [
            'name' => 'free',
            'display_name' => 'Free Plan',
            'price_annual' => 0,
            'price_monthly' => 0,
            'features' => ['Basic prompt management', 'Limited AI generations'],
            'max_ai_generations_per_month' => 50
        ],
        'usage' => [
            'prompt_creation' => ['used' => 0, 'limit' => 50, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0],
            'ai_generation' => ['used' => 0, 'limit' => 50, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0],
            'category_creation' => ['used' => 0, 'limit' => 5, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0],
            'bookmark_creation' => ['used' => 0, 'limit' => 50, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0, 'is_lifetime' => true],
            'note_creation' => ['used' => 0, 'limit' => 50, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0, 'is_lifetime' => true],
            'document_creation' => ['used' => 0, 'limit' => 20, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0, 'is_lifetime' => true],
            'video_creation' => ['used' => 0, 'limit' => 30, 'is_unlimited' => false, 'is_at_limit' => false, 'is_near_limit' => false, 'percentage' => 0, 'is_lifetime' => true]
        ],
        'next_reset' => date('Y-m-01', strtotime('+1 month'))
    ];
}

if (!$allTiers || empty($allTiers)) {
    $allTiers = [
        [
            'name' => 'free',
            'display_name' => 'Free Plan',
            'description' => 'Perfect for getting started',
            'is_free' => true,
            'is_premium' => false,
            'price_annual' => 0,
            'price_monthly' => 0,
            'limits' => ['prompts' => '50', 'ai_generations' => '50', 'categories' => '5', 'bookmarks' => '50', 'notes' => '50', 'documents' => '20', 'videos' => '30'],
            'features' => ['Basic prompt management', 'Limited AI generations']
        ],
        [
            'name' => 'premium',
            'display_name' => 'Premium Plan',
            'description' => 'For power users and professionals',
            'is_free' => false,
            'is_premium' => true,
            'price_annual' => 10,
            'price_monthly' => 2,
            'limits' => ['prompts' => 'Unlimited', 'ai_generations' => '300', 'categories' => 'Unlimited', 'bookmarks' => 'Unlimited', 'notes' => '250', 'documents' => '150', 'videos' => '200'],
            'features' => ['Unlimited prompts', 'Enhanced AI generations', 'Priority support']
        ]
    ];
}

// Get app settings for admin users
$app_settings = [];
if ($user['role'] === 'admin') {
    try {
        require_once __DIR__ . '/../models/AppSettings.php';
        $appSettingsModel = new AppSettings();
        $all_app_settings = $appSettingsModel->getAllSettings();
        
        // Extract values for form with safety checks
        $app_settings = [
            'app_name' => isset($all_app_settings['app_name']['value']) ? $all_app_settings['app_name']['value'] : 'Promptash',
            'app_description' => isset($all_app_settings['app_description']['value']) ? $all_app_settings['app_description']['value'] : 'Professional prompt management made simple',
            'allow_registration' => isset($all_app_settings['allow_registration']['value']) ? (bool)$all_app_settings['allow_registration']['value'] : true,
            'max_prompts_per_user' => isset($all_app_settings['max_prompts_per_user']['value']) ? (int)$all_app_settings['max_prompts_per_user']['value'] : 1000,
            'maintenance_mode' => isset($all_app_settings['maintenance_mode']['value']) ? (bool)$all_app_settings['maintenance_mode']['value'] : false,
            'openai_api_key' => isset($all_app_settings['openai_api_key']['value']) ? $all_app_settings['openai_api_key']['value'] : '',
            'selected_openai_model' => isset($all_app_settings['selected_openai_model']['value']) ? $all_app_settings['selected_openai_model']['value'] : 'gpt-3.5-turbo',
            'ai_enabled' => isset($all_app_settings['ai_enabled']['value']) ? (bool)$all_app_settings['ai_enabled']['value'] : false,
            'paystack_public_key' => isset($all_app_settings['paystack_public_key']['value']) ? $all_app_settings['paystack_public_key']['value'] : '',
            'paystack_secret_key' => isset($all_app_settings['paystack_secret_key']['value']) ? $all_app_settings['paystack_secret_key']['value'] : '',
            'payment_currency' => isset($all_app_settings['payment_currency']['value']) ? $all_app_settings['payment_currency']['value'] : 'GHS',
            'email_enabled' => isset($all_app_settings['email_enabled']['value']) ? (bool)$all_app_settings['email_enabled']['value'] : false,
            'smtp_host' => isset($all_app_settings['smtp_host']['value']) ? $all_app_settings['smtp_host']['value'] : '',
            'smtp_port' => isset($all_app_settings['smtp_port']['value']) ? (int)$all_app_settings['smtp_port']['value'] : 587,
            'smtp_username' => isset($all_app_settings['smtp_username']['value']) ? $all_app_settings['smtp_username']['value'] : '',
            'smtp_password' => isset($all_app_settings['smtp_password']['value']) ? $all_app_settings['smtp_password']['value'] : '',
            'smtp_encryption' => isset($all_app_settings['smtp_encryption']['value']) ? $all_app_settings['smtp_encryption']['value'] : 'tls',
            'smtp_from_email' => isset($all_app_settings['smtp_from_email']['value']) ? $all_app_settings['smtp_from_email']['value'] : '',
            'smtp_from_name' => isset($all_app_settings['smtp_from_name']['value']) ? $all_app_settings['smtp_from_name']['value'] : 'Promptash'
        ];
    } catch (Exception $e) {
        error_log("Settings page app settings retrieval error: " . $e->getMessage());
        // Fallback to defaults
        $app_settings = [
            'app_name' => 'Promptash',
            'app_description' => 'Professional prompt management made simple',
            'allow_registration' => true,
            'max_prompts_per_user' => 1000,
            'maintenance_mode' => false,
            'openai_api_key' => '',
            'selected_openai_model' => 'gpt-3.5-turbo',
            'ai_enabled' => false,
            'paystack_public_key' => '',
            'paystack_secret_key' => '',
            'payment_currency' => 'GHS'
        ];
    }
}

$page_title = 'Settings';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-cog"></i> Settings</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-crown"></i> Membership & Usage</h5>
        <small class="text-muted">Current plan and usage statistics</small>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-user-tag"></i> Current Plan</h6>
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <?php if (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'premium'): ?>
                            <span class="badge bg-gradient-primary fs-6">
                                <i class="fas fa-crown me-1"></i> <?php echo htmlspecialchars($usageSummary['tier']['display_name'] ?? 'Premium'); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary fs-6">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($usageSummary['tier']['display_name'] ?? 'Free Plan'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'free'): ?>
                        <a href="index.php?page=upgrade" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-up"></i> Upgrade to Premium
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($usageSummary['tier']['price_annual']) && $usageSummary['tier']['price_annual'] > 0): ?>
                    <?php 
                        require_once __DIR__ . '/../models/AppSettings.php';
                        $appSettings = new AppSettings();
                    ?>
                    <p class="text-muted mb-3">
                        <strong>Billing:</strong> <?php echo $appSettings->formatPrice($usageSummary['tier']['price_annual']); ?>/year
                        </p>
                <?php else: ?>
                    <p class="text-muted mb-3">
                        <strong>Billing:</strong> Free Plan
                    </p>
                <?php endif; ?>
                
                <div class="mb-3">
                    <h6><i class="fas fa-list"></i> Plan Features</h6>
                    <ul class="list-unstyled">
                        <?php if (isset($usageSummary['tier']['features']) && is_array($usageSummary['tier']['features'])): ?>
                            <?php 
                                // MODIFICATION START: Dynamically correct the AI generations feature text
                                $tier_data = $membershipModel->getTierByName($usageSummary['tier']['name']);
                                foreach ($tier_data['features'] as $feature):
                                    $display_feature = $feature;
                                    if (stripos($feature, 'ai generation') !== false) {
                                        $limit = $tier_data['max_ai_generations_per_month'] ?? 0;
                                        if ($limit > 0) {
                                            $display_feature = number_format($limit) . ' AI generations/month';
                                        } else {
                                            $display_feature = 'Unlimited AI generations';
                                        }
                                    }
                            ?>
                                <li><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($display_feature); ?></li>
                            <?php endforeach; 
                                // MODIFICATION END
                            ?>
                        <?php else: ?>
                            <li><i class="fas fa-check text-success me-2"></i>Basic prompt management</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6><i class="fas fa-chart-bar"></i> Usage This Month</h6>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Prompts Created</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['prompt_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['prompt_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['prompt_creation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['prompt_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['prompt_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['prompt_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['prompt_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>AI Generations</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['ai_generation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['ai_generation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['ai_generation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['ai_generation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['ai_generation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['ai_generation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['ai_generation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Categories Created</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['category_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['category_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['category_creation']['limit'] ?? 5); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['category_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['category_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['category_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['category_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Bookmarks Created</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['bookmark_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['bookmark_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['bookmark_creation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['bookmark_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['bookmark_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['bookmark_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['bookmark_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                 <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Notes Created (Lifetime)</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['note_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['note_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['note_creation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['note_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['note_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['note_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['note_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                 <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Documents Uploaded (Lifetime)</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['document_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['document_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['document_creation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['document_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['document_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['document_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['document_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                 <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>Videos Added (Lifetime)</strong></small>
                        <small>
                            <?php echo number_format($usageSummary['usage']['video_creation']['used'] ?? 0); ?>
                            <?php if (!($usageSummary['usage']['video_creation']['is_unlimited'] ?? false)): ?>
                                / <?php echo number_format($usageSummary['usage']['video_creation']['limit'] ?? 50); ?>
                            <?php else: ?>
                                / Unlimited
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if (!($usageSummary['usage']['video_creation']['is_unlimited'] ?? false)): ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar <?php echo ($usageSummary['usage']['video_creation']['is_at_limit'] ?? false) ? 'bg-danger' : (($usageSummary['usage']['video_creation']['is_near_limit'] ?? false) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo ($usageSummary['usage']['video_creation']['percentage'] ?? 0); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div class="progress mb-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <small class="text-muted">
                    <i class="fas fa-calendar"></i> Monthly usage resets on <?php echo date('M j, Y', strtotime($usageSummary['next_reset'] ?? '+1 month')); ?>.
                    Bookmarks, Notes, Documents, and Videos have lifetime limits.
                </small>
            </div>
        </div>
        
        <?php if (isset($usageSummary['tier']['name']) && $usageSummary['tier']['name'] === 'free'): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i>
                <strong>Ready to unlock more?</strong> 
                <?php 
                    if (!isset($appSettings)) {
                        require_once __DIR__ . '/../models/AppSettings.php';
                        $appSettings = new AppSettings();
                    }
                ?>
                Upgrade to Premium for unlimited prompts, 300 AI generations per month, and advanced features starting at just <?php echo $appSettings->formatPrice(100); ?>/year!
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4" id="membershipTiers">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-layer-group"></i> Membership Plans</h5>
        <small class="text-muted">Choose the plan that fits your needs</small>
    </div>
    <div class="card-body">
        <div class="row">
            <?php if (is_array($allTiers)): ?>
                <?php foreach ($allTiers as $tier): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 <?php echo ($tier['name'] ?? '') === ($usageSummary['tier']['name'] ?? '') ? 'border-primary' : ''; ?> <?php echo ($tier['is_premium'] ?? false) ? 'border-warning' : ''; ?>">
                            <div class="card-header text-center <?php echo ($tier['is_premium'] ?? false) ? 'bg-gradient-primary text-white' : 'bg-light'; ?>">
                                <?php if ($tier['is_premium'] ?? false): ?>
                                    <i class="fas fa-crown mb-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-user mb-2"></i>
                                <?php endif; ?>
                                <h5 class="mb-0"><?php echo htmlspecialchars($tier['display_name'] ?? 'Unknown Plan'); ?></h5>
                                <?php if (($tier['name'] ?? '') === ($usageSummary['tier']['name'] ?? '')): ?>
                                    <small class="badge bg-success">Current Plan</small>
                                <?php endif; ?>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <?php if ($tier['is_free'] ?? false): ?>
                                        <h3 class="text-primary">Free</h3>
                                        <small class="text-muted">Forever</small>
                                    <?php else: ?>
                                        <?php 
                                            if (!isset($appSettings)) {
                                                require_once __DIR__ . '/../models/AppSettings.php';
                                                $appSettings = new AppSettings();
                                            }
                                        ?>
                                        <h3 class="text-primary">
                                            <?php echo $appSettings->formatPrice($tier['price_annual'] ?? 0); ?>
                                            <small class="text-muted fs-6">/year</small>
                                        </h3>
                                        <?php endif; ?>
                                </div>
                                
                                <p class="text-muted small"><?php echo htmlspecialchars($tier['description'] ?? ''); ?></p>
                                
                                <ul class="list-unstyled text-start small">
                                    <li class="mb-1">
                                        <i class="fas fa-file-text text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['prompts'] ?? 'Unknown'); ?></strong> prompts/month
                                    </li>
                                    <li class="mb-1">
                                        <i class="fas fa-robot text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['ai_generations'] ?? 'Unknown'); ?></strong> AI generations/month
                                    </li>
                                    <li class="mb-1">
                                        <i class="fas fa-folder text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['categories'] ?? 'Unknown'); ?></strong> categories
                                    </li>
                                     <li class="mb-1">
                                        <i class="fas fa-bookmark text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['bookmarks'] ?? 'Unknown'); ?></strong> bookmarks
                                    </li>
                                     <li class="mb-1">
                                        <i class="fas fa-sticky-note text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['notes'] ?? 'Unknown'); ?></strong> notes (Lifetime)
                                    </li>
                                     <li class="mb-1">
                                        <i class="fas fa-folder-open text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['documents'] ?? 'Unknown'); ?></strong> documents (Lifetime)
                                    </li>
                                     <li class="mb-1">
                                        <i class="fas fa-video text-primary me-2"></i>
                                        <strong><?php echo htmlspecialchars($tier['limits']['videos'] ?? 'Unknown'); ?></strong> videos (Lifetime)
                                    </li>
                                </ul>
                                
                                <hr>
                                
                                <ul class="list-unstyled text-start small">
                                    <?php if (isset($tier['features']) && is_array($tier['features'])): ?>
                                        <?php 
                                            // MODIFICATION START: Dynamically correct the AI generations feature text
                                            foreach ($tier['features'] as $feature):
                                                $display_feature = $feature;
                                                if (stripos($feature, 'ai generation') !== false && isset($tier['limits']['ai_generations'])) {
                                                    $limit = $tier['limits']['ai_generations'];
                                                    if (is_numeric($limit)) {
                                                        $display_feature = number_format($limit) . ' AI generations/month';
                                                    } else {
                                                        $display_feature = 'Unlimited AI generations';
                                                    }
                                                }
                                        ?>
                                            <li class="mb-1">
                                                <i class="fas fa-check text-success me-2"></i>
                                                <?php echo htmlspecialchars($display_feature); ?>
                                            </li>
                                        <?php 
                                            endforeach;
                                            // MODIFICATION END
                                        ?>
                                    <?php else: ?>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Basic features included
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <?php if (($tier['name'] ?? '') === ($usageSummary['tier']['name'] ?? '')): ?>
                                    <button class="btn btn-outline-secondary" disabled>
                                        <i class="fas fa-check"></i> Current Plan
                                    </button>
                                <?php elseif ($tier['is_premium'] ?? false): ?>
                                    <a href="index.php?page=upgrade" class="btn btn-primary">
                                        <i class="fas fa-crown"></i> Upgrade Now
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary" disabled>
                                        Free Plan
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Membership information is currently unavailable. Please contact support.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Application Settings</h5>
        <small class="text-muted">Administrator only - Global application configuration</small>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_app_settings">
            
            <input type="hidden" name="ai_enabled" value="<?php echo ($app_settings['ai_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="openai_api_key" value="<?php echo htmlspecialchars($app_settings['openai_api_key'] ?? ''); ?>">
            <input type="hidden" name="selected_openai_model" value="<?php echo htmlspecialchars($app_settings['selected_openai_model'] ?? 'gpt-3.5-turbo'); ?>">
            <input type="hidden" name="paystack_public_key" value="<?php echo htmlspecialchars($app_settings['paystack_public_key'] ?? ''); ?>">
            <input type="hidden" name="paystack_secret_key" value="<?php echo htmlspecialchars($app_settings['paystack_secret_key'] ?? ''); ?>">
            <input type="hidden" name="payment_currency" value="<?php echo htmlspecialchars($app_settings['payment_currency'] ?? 'GHS'); ?>">
            <input type="hidden" name="email_enabled" value="<?php echo ($app_settings['email_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="smtp_host" value="<?php echo htmlspecialchars($app_settings['smtp_host'] ?? ''); ?>">
            <input type="hidden" name="smtp_port" value="<?php echo (int)($app_settings['smtp_port'] ?? 587); ?>">
            <input type="hidden" name="smtp_username" value="<?php echo htmlspecialchars($app_settings['smtp_username'] ?? ''); ?>">
            <input type="hidden" name="smtp_password" value="<?php echo htmlspecialchars($app_settings['smtp_password'] ?? ''); ?>">
            <input type="hidden" name="smtp_encryption" value="<?php echo htmlspecialchars($app_settings['smtp_encryption'] ?? 'tls'); ?>">
            <input type="hidden" name="smtp_from_email" value="<?php echo htmlspecialchars($app_settings['smtp_from_email'] ?? ''); ?>">
            <input type="hidden" name="smtp_from_name" value="<?php echo htmlspecialchars($app_settings['smtp_from_name'] ?? 'Promptash'); ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="app_name" class="form-label"><strong>Application Name</strong></label>
                        <input type="text" class="form-control" id="app_name" name="app_name" 
                               value="<?php echo htmlspecialchars($app_settings['app_name'] ?? ''); ?>" required>
                        <div class="form-text">Displayed in header and browser title</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="app_description" class="form-label"><strong>Application Description</strong></label>
                        <textarea class="form-control" id="app_description" name="app_description" rows="2"><?php echo htmlspecialchars($app_settings['app_description'] ?? ''); ?></textarea>
                        <div class="form-text">Brief description of the application</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="max_prompts_per_user" class="form-label"><strong>Max Prompts Per User</strong></label>
                        <input type="number" class="form-control" id="max_prompts_per_user" name="max_prompts_per_user" 
                               value="<?php echo (int)($app_settings['max_prompts_per_user'] ?? 1000); ?>" min="0">
                        <div class="form-text">0 = unlimited</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="allow_registration" name="allow_registration" value="true"
                                   <?php echo ($app_settings['allow_registration'] ?? true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_registration">
                                <strong>Allow New User Registration</strong>
                            </label>
                        </div>
                        <div class="form-text">When disabled, only admins can create accounts</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="true"
                                   <?php echo ($app_settings['maintenance_mode'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">
                                <strong>Maintenance Mode</strong>
                            </label>
                        </div>
                        <div class="form-text">Restricts access to administrators only</div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> Changes to these settings affect all users. Use caution when enabling maintenance mode.
            </div>
            
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-save"></i> Save Application Settings
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-robot"></i> AI Configuration</h5>
        <small class="text-muted">Administrator only - Global AI service configuration</small>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_app_settings">
            
            <input type="hidden" name="app_name" value="<?php echo htmlspecialchars($app_settings['app_name'] ?? ''); ?>">
            <input type="hidden" name="app_description" value="<?php echo htmlspecialchars($app_settings['app_description'] ?? ''); ?>">
            <input type="hidden" name="max_prompts_per_user" value="<?php echo (int)($app_settings['max_prompts_per_user'] ?? 1000); ?>">
            <input type="hidden" name="allow_registration" value="<?php echo ($app_settings['allow_registration'] ?? true) ? 'true' : 'false'; ?>">
            <input type="hidden" name="maintenance_mode" value="<?php echo ($app_settings['maintenance_mode'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="paystack_public_key" value="<?php echo htmlspecialchars($app_settings['paystack_public_key'] ?? ''); ?>">
            <input type="hidden" name="paystack_secret_key" value="<?php echo htmlspecialchars($app_settings['paystack_secret_key'] ?? ''); ?>">
            <input type="hidden" name="payment_currency" value="<?php echo htmlspecialchars($app_settings['payment_currency'] ?? 'GHS'); ?>">
            <input type="hidden" name="email_enabled" value="<?php echo ($app_settings['email_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="smtp_host" value="<?php echo htmlspecialchars($app_settings['smtp_host'] ?? ''); ?>">
            <input type="hidden" name="smtp_port" value="<?php echo (int)($app_settings['smtp_port'] ?? 587); ?>">
            <input type="hidden" name="smtp_username" value="<?php echo htmlspecialchars($app_settings['smtp_username'] ?? ''); ?>">
            <input type="hidden" name="smtp_password" value="<?php echo htmlspecialchars($app_settings['smtp_password'] ?? ''); ?>">
            <input type="hidden" name="smtp_encryption" value="<?php echo htmlspecialchars($app_settings['smtp_encryption'] ?? 'tls'); ?>">
            <input type="hidden" name="smtp_from_email" value="<?php echo htmlspecialchars($app_settings['smtp_from_email'] ?? ''); ?>">
            <input type="hidden" name="smtp_from_name" value="<?php echo htmlspecialchars($app_settings['smtp_from_name'] ?? 'Promptash'); ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ai_enabled" name="ai_enabled" value="true"
                                   <?php echo ($app_settings['ai_enabled'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ai_enabled">
                                <strong>Enable AI Features</strong>
                            </label>
                        </div>
                        <div class="form-text">When enabled, users can access AI-powered prompt generation</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="openai_api_key" class="form-label">
                            <strong>OpenAI API Key</strong>
                            <small class="text-muted">(Required for AI features)</small>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="openai_api_key" name="openai_api_key" 
                                   value="<?php echo !empty($app_settings['openai_api_key']) ? '********************' : ''; ?>"
                                   placeholder="sk-...">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleOpenAIApiKeyVisibility()">
                                <i class="fas fa-eye" id="toggleOpenAIIcon"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Get your API key from <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI</a>.
                            This key will be used for all AI features across the application.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="selected_openai_model" class="form-label"><strong>AI Model</strong></label>
                        <select class="form-select" id="selected_openai_model" name="selected_openai_model" <?php echo empty($app_settings['openai_api_key']) ? 'disabled' : ''; ?>>
                            <?php
                            require_once __DIR__ . '/../../helpers/AIHelper.php';
                            $aiHelper = AIHelper::fromAdminSettings();
                            $availableModels = $aiHelper->getAvailableModels();
                            
                            if (empty($availableModels)) {
                                echo '<option value="">Enter API key to load models</option>';
                            } else {
                                foreach ($availableModels as $model): ?>
                                    <option value="<?php echo htmlspecialchars($model); ?>"
                                            <?php echo ($app_settings['selected_openai_model'] ?? 'gpt-3.5-turbo') === $model ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($model); ?>
                                    </option>
                                <?php endforeach;
                            }
                            ?>
                        </select>
                        <div class="form-text">Select the AI model to use for prompt generation</div>
                    </div>
                    
                    <?php if (!empty($app_settings['openai_api_key'])): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-info" onclick="testAIConnection()">
                            <i class="fas fa-plug"></i> Test AI Connection
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> OpenAI provides access to multiple AI models through a single API. This configuration applies to all users.
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save AI Configuration
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Configuration</h5>
        <small class="text-muted">Administrator only - Paystack payment gateway settings</small>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_app_settings">
            
            <input type="hidden" name="app_name" value="<?php echo htmlspecialchars($app_settings['app_name'] ?? ''); ?>">
            <input type="hidden" name="app_description" value="<?php echo htmlspecialchars($app_settings['app_description'] ?? ''); ?>">
            <input type="hidden" name="max_prompts_per_user" value="<?php echo (int)($app_settings['max_prompts_per_user'] ?? 1000); ?>">
            <input type="hidden" name="allow_registration" value="<?php echo ($app_settings['allow_registration'] ?? true) ? 'true' : 'false'; ?>">
            <input type="hidden" name="maintenance_mode" value="<?php echo ($app_settings['maintenance_mode'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="ai_enabled" value="<?php echo ($app_settings['ai_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="openai_api_key" value="<?php echo htmlspecialchars($app_settings['openai_api_key'] ?? ''); ?>">
            <input type="hidden" name="selected_openai_model" value="<?php echo htmlspecialchars($app_settings['selected_openai_model'] ?? 'gpt-3.5-turbo'); ?>">
            <input type="hidden" name="email_enabled" value="<?php echo ($app_settings['email_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="smtp_host" value="<?php echo htmlspecialchars($app_settings['smtp_host'] ?? ''); ?>">
            <input type="hidden" name="smtp_port" value="<?php echo (int)($app_settings['smtp_port'] ?? 587); ?>">
            <input type="hidden" name="smtp_username" value="<?php echo htmlspecialchars($app_settings['smtp_username'] ?? ''); ?>">
            <input type="hidden" name="smtp_password" value="<?php echo htmlspecialchars($app_settings['smtp_password'] ?? ''); ?>">
            <input type="hidden" name="smtp_encryption" value="<?php echo htmlspecialchars($app_settings['smtp_encryption'] ?? 'tls'); ?>">
            <input type="hidden" name="smtp_from_email" value="<?php echo htmlspecialchars($app_settings['smtp_from_email'] ?? ''); ?>">
            <input type="hidden" name="smtp_from_name" value="<?php echo htmlspecialchars($app_settings['smtp_from_name'] ?? 'Promptash'); ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="paystack_public_key" class="form-label">
                            <strong>Paystack Public Key</strong>
                        </label>
                        <input type="text" class="form-control" id="paystack_public_key" name="paystack_public_key" 
                               value="<?php echo htmlspecialchars($app_settings['paystack_public_key'] ?? ''); ?>"
                               placeholder="pk_test_...">
                        <div class="form-text">
                            Your Paystack public key for frontend integration.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_currency" class="form-label">
                            <strong>Payment Currency</strong>
                        </label>
                        <select class="form-select" id="payment_currency" name="payment_currency">
                            <option value="GHS" <?php echo ($app_settings['payment_currency'] ?? 'GHS') === 'GHS' ? 'selected' : ''; ?>>Ghana Cedis (GHS)</option>
                            <option value="NGN" <?php echo ($app_settings['payment_currency'] ?? 'GHS') === 'NGN' ? 'selected' : ''; ?>>Nigerian Naira (NGN)</option>
                            <option value="USD" <?php echo ($app_settings['payment_currency'] ?? 'GHS') === 'USD' ? 'selected' : ''; ?>>US Dollars (USD)</option>
                        </select>
                        <div class="form-text">
                            Currency for subscription pricing. Premium plan costs 100 units annually.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="paystack_secret_key" class="form-label">
                            <strong>Paystack Secret Key</strong>
                            <small class="text-muted">(Required for payments)</small>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="paystack_secret_key" name="paystack_secret_key" 
                                   value="<?php echo !empty($app_settings['paystack_secret_key']) ? str_repeat('•', 20) : ''; ?>"
                                   placeholder="sk_test_...">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePaystackSecretKeyVisibility()">
                                <i class="fas fa-eye" id="togglePaystackIcon"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Your Paystack secret key for server-side transactions. Keep this secure!
                        </div>
                    </div>
                    
                    <?php if (!empty($app_settings['paystack_public_key']) && !empty($app_settings['paystack_secret_key'])): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-success" onclick="testPaystackConnection()">
                            <i class="fas fa-credit-card"></i> Test Payment Connection
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Important:</strong> 
                <ul class="mb-0 mt-2">
                    <li>Get your Paystack keys from <a href="https://dashboard.paystack.com/#/settings/developers" target="_blank">Paystack Dashboard</a></li>
                    <li>Use test keys during development (pk_test_... and sk_test_...)</li>
                    <li>Switch to live keys only when ready for production</li>
                    <li>Premium subscription is set to 100 <?php echo $app_settings['payment_currency'] ?? 'GHS'; ?> annually</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Payment Settings
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-envelope"></i> Email Configuration</h5>
        <small class="text-muted">Administrator only - SMTP email settings for notifications</small>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_app_settings">
            
            <input type="hidden" name="app_name" value="<?php echo htmlspecialchars($app_settings['app_name'] ?? 'Promptash'); ?>">
            <input type="hidden" name="app_description" value="<?php echo htmlspecialchars($app_settings['app_description'] ?? ''); ?>">
            <input type="hidden" name="allow_registration" value="<?php echo ($app_settings['allow_registration'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="max_prompts_per_user" value="<?php echo (int)($app_settings['max_prompts_per_user'] ?? 1000); ?>">
            <input type="hidden" name="maintenance_mode" value="<?php echo ($app_settings['maintenance_mode'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="ai_enabled" value="<?php echo ($app_settings['ai_enabled'] ?? false) ? 'true' : 'false'; ?>">
            <input type="hidden" name="openai_api_key" value="<?php echo htmlspecialchars($app_settings['openai_api_key'] ?? ''); ?>">
            <input type="hidden" name="selected_openai_model" value="<?php echo htmlspecialchars($app_settings['selected_openai_model'] ?? 'gpt-3.5-turbo'); ?>">
            <input type="hidden" name="paystack_public_key" value="<?php echo htmlspecialchars($app_settings['paystack_public_key'] ?? ''); ?>">
            <input type="hidden" name="paystack_secret_key" value="<?php echo htmlspecialchars($app_settings['paystack_secret_key'] ?? ''); ?>">
            <input type="hidden" name="payment_currency" value="<?php echo htmlspecialchars($app_settings['payment_currency'] ?? 'GHS'); ?>">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" value="true"
                           <?php echo ($app_settings['email_enabled'] ?? false) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="email_enabled">
                        <strong>Enable Email Notifications</strong>
                    </label>
                </div>
                <div class="form-text">Enable to send email notifications to users (subscription confirmations, password resets, etc.)</div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="smtp_host" class="form-label">
                            <strong>SMTP Host</strong>
                        </label>
                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                               value="<?php echo htmlspecialchars($app_settings['smtp_host'] ?? ''); ?>"
                               placeholder="smtp.gmail.com">
                        <div class="form-text">
                            Your SMTP server hostname.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_port" class="form-label">
                            <strong>SMTP Port</strong>
                        </label>
                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                               value="<?php echo (int)($app_settings['smtp_port'] ?? 587); ?>"
                               placeholder="587">
                        <div class="form-text">
                            Common ports: 587 (TLS), 465 (SSL), 25 (Plain)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_encryption" class="form-label">
                            <strong>Encryption</strong>
                        </label>
                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?php echo ($app_settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($app_settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($app_settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                        <div class="form-text">
                            Recommended: TLS for most modern SMTP servers
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="smtp_username" class="form-label">
                            <strong>SMTP Username</strong>
                        </label>
                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                               value="<?php echo htmlspecialchars($app_settings['smtp_username'] ?? ''); ?>"
                               placeholder="your.email@gmail.com">
                        <div class="form-text">
                            Usually your email address.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_password" class="form-label">
                            <strong>SMTP Password</strong>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                   value="<?php echo !empty($app_settings['smtp_password']) ? str_repeat('•', 20) : ''; ?>"
                                   placeholder="App password or email password">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleSmtpPasswordVisibility()">
                                <i class="fas fa-eye" id="toggleSmtpIcon"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            For Gmail, use an App Password instead of your regular password.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_from_email" class="form-label">
                            <strong>From Email</strong>
                        </label>
                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" 
                               value="<?php echo htmlspecialchars($app_settings['smtp_from_email'] ?? ''); ?>"
                               placeholder="noreply@yourdomain.com">
                        <div class="form-text">
                            Email address that notifications will be sent from.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_from_name" class="form-label">
                            <strong>From Name</strong>
                        </label>
                        <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" 
                               value="<?php echo htmlspecialchars($app_settings['smtp_from_name'] ?? 'Promptash'); ?>"
                               placeholder="Promptash">
                        <div class="form-text">
                            Display name for sent emails.
                        </div>
                    </div>
                    
                    <?php if (!empty($app_settings['smtp_host']) && !empty($app_settings['smtp_username'])): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-info" onclick="testEmailConfiguration()">
                            <i class="fas fa-envelope"></i> Test Email Configuration
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Email Setup Guide:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Gmail:</strong> Use smtp.gmail.com, port 587, TLS, and an App Password</li>
                    <li><strong>Outlook:</strong> Use smtp-mail.outlook.com, port 587, TLS</li>
                    <li><strong>Yahoo:</strong> Use smtp.mail.yahoo.com, port 587, TLS</li>
                    <li><strong>Custom SMTP:</strong> Contact your hosting provider for SMTP settings</li>
                    <li>Test the configuration before enabling email notifications</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Email Settings
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// OpenAI API key visibility toggle
function toggleOpenAIApiKeyVisibility() {
    const input = document.getElementById('openai_api_key');
    const icon = document.getElementById('toggleOpenAIIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Paystack secret key visibility toggle
function togglePaystackSecretKeyVisibility() {
    const input = document.getElementById('paystack_secret_key');
    const icon = document.getElementById('togglePaystackIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// SMTP password visibility toggle
function toggleSmtpPasswordVisibility() {
    const input = document.getElementById('smtp_password');
    const icon = document.getElementById('toggleSmtpIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Test OpenAI API connection
function testAIConnection() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=test_ai_connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'OpenAI API connection successful! Model: ' + (data.model || 'Unknown'));
        } else {
            showAlert('danger', 'Connection failed: ' + data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Test failed: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Test Paystack API connection
function testPaystackConnection() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    button.disabled = true;
    
    fetch('index.php?page=api&action=test_paystack_connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Paystack connection successful! Ready to process payments.');
        } else {
            showAlert('danger', 'Connection failed: ' + data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Test failed: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Test Email Configuration
function testEmailConfiguration() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    button.disabled = true;
    
    // Get test email from user
    const testEmail = prompt('Enter an email address to send a test email to:');
    if (!testEmail) {
        button.innerHTML = originalText;
        button.disabled = false;
        return;
    }
    
    fetch('index.php?page=api&action=test_email_configuration', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'test_email=' + encodeURIComponent(testEmail)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Test email sent successfully to ' + testEmail + '!');
        } else {
            showAlert('danger', 'Email test failed: ' + data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Test failed: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Membership management functions
function refreshUsageStats() {
    fetch('index.php?page=api&action=get_usage_summary', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update usage displays
            location.reload(); // Simple refresh for now
        }
    })
    .catch(error => {
        console.error('Error refreshing usage stats:', error);
    });
}

// Helper function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.d-flex.justify-content-between');
    container.parentNode.insertBefore(alertDiv, container.nextSibling);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>