<?php
$page_title = "Upgrade to Premium";
$user = $auth->getCurrentUser();

// Initialize models
require_once __DIR__ . '/../models/MembershipTier.php';
require_once __DIR__ . '/../models/UsageTracker.php';
require_once __DIR__ . '/../../helpers/PaymentProcessor.php';
require_once __DIR__ . '/../models/AppSettings.php';

$tierModel = new MembershipTier();
$usageTracker = new UsageTracker();
$paymentProcessor = new PaymentProcessor();
$appSettings = new AppSettings();

// Get current user's tier and usage data
$currentTier = $tierModel->getTierById($user['current_tier_id']);
$premiumTier = $tierModel->getPremiumTier();
$usageSummary = $usageTracker->getUserUsageSummary($user['id']);

// Handle upgrade request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade') {
    try {
        if (!$paymentProcessor->isConfigured()) {
            throw new Exception('Payment system is not configured. Please contact support.');
        }
        
        if (!$premiumTier) {
            throw new Exception('Premium tier not found. Please contact support.');
        }
        
        // Initialize payment with Paystack
        $upgrade_result = $paymentProcessor->processPremiumUpgrade(
            $user['id'], 
            $user['email'], 
            $premiumTier['id']
        );
        
        // Redirect to Paystack payment page
        header('Location: ' . $upgrade_result['payment_url']);
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle callback messages
$success_message = '';
$error_message = '';

if (isset($_GET['success']) && isset($_SESSION['payment_success'])) {
    $success_message = $_SESSION['payment_message'] ?? 'Payment successful!';
    unset($_SESSION['payment_success'], $_SESSION['payment_message']);
    
    // Refresh user data after successful payment
    $user = $auth->getCurrentUser();
    $currentTier = $tierModel->getTierById($user['current_tier_id']);
    $usageSummary = $usageTracker->getUserUsageSummary($user['id']);
}

if (isset($_GET['error']) && isset($_SESSION['payment_error'])) {
    $error_message = $_SESSION['payment_message'] ?? 'Payment failed. Please try again.';
    unset($_SESSION['payment_error'], $_SESSION['payment_message']);
}

$page_title = "Upgrade to Premium";
ob_start();
?>


            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-cog"></i> Your Current Plan</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary"><?php echo htmlspecialchars($currentTier['display_name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($currentTier['description']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Current Usage This Month:</h6>
                            <?php foreach ($usageSummary['usage'] as $type => $data): ?>
                                <?php 
                                $type_labels = [
                                    'prompt_creation' => 'Prompts Created',
                                    'ai_generation' => 'AI Generations',
                                    'category_creation' => 'Categories Created',
                                    'bookmark_creation' => 'Bookmarks Created',
                                    'note_creation' => 'Notes Created',
                                    'document_creation' => 'Documents Uploaded',
                                    'video_creation' => 'Videos Added'
                                ];
                                $label = $type_labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
                                ?>
                                <div class="mb-2">
                                    <small class="text-muted"><?php echo $label; ?>:</small>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?php echo $data['is_at_limit'] ? 'bg-danger' : ($data['is_near_limit'] ? 'bg-warning' : 'bg-success'); ?>" 
                                             style="width: <?php echo $data['percentage']; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $data['used']; ?><?php echo $data['is_unlimited'] ? '' : ' / ' . number_format($data['limit']); ?>
                                        <?php echo $data['is_unlimited'] ? ' (Unlimited)' : ''; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error ?? false): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($currentTier['name'] === 'premium'): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-crown text-warning fa-4x mb-3"></i>
                        <h3 class="text-success">You're Already Premium!</h3>
                        <p class="text-muted">You have access to all premium features. Thank you for your support!</p>
                        <a href="index.php?page=settings" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Manage Account
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0 text-center"><?php echo htmlspecialchars($currentTier['display_name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h3 class="text-primary">
                                        <?php echo $appSettings->formatPrice($currentTier['price_monthly']); ?>
                                        <small class="text-muted">/month</small>
                                    </h3>
                                    <small class="text-muted">
                                        <?php echo $appSettings->formatPrice($currentTier['price_annual']); ?> billed yearly
                                    </small>
                                </div>
                                <ul class="list-unstyled">
                                    <?php foreach ($currentTier['features'] as $feature): ?>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <hr>
                                <h6>Usage Limits:</h6>
                                <ul class="list-unstyled text-muted small">
                                    <li>• <?php echo $currentTier['max_prompts_per_month']; ?> prompts per month</li>
                                    <li>• <?php echo $currentTier['max_ai_generations_per_month']; ?> AI generations per month</li>
                                    <li>• <?php echo $currentTier['max_categories']; ?> categories</li>
                                    <li>• <?php echo $currentTier['max_bookmarks']; ?> bookmarks</li>
                                    <li>• <?php echo $currentTier['max_notes']; ?> notes (Lifetime)</li>
                                    <li>• <?php echo $currentTier['max_documents']; ?> documents (Lifetime)</li>
                                    <li>• <?php echo $currentTier['max_videos']; ?> videos (Lifetime)</li>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <span class="badge badge-secondary">Current Plan</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0 text-center">
                                    <i class="fas fa-crown"></i> <?php echo htmlspecialchars($premiumTier['display_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h3 class="text-warning">
                                        <?php echo $appSettings->formatPrice($premiumTier['price_annual']); ?>
                                        <small class="text-muted">/year</small>
                                    </h3>
                                    <small class="text-muted">
                                        Save <?php echo $appSettings->formatPrice(($premiumTier['price_monthly'] * 12) - $premiumTier['price_annual']); ?> 
                                        compared to monthly billing
                                    </small>
                                </div>
                                <ul class="list-unstyled">
                                    <?php foreach ($premiumTier['features'] as $feature): ?>
                                        <li class="mb-2"><i class="fas fa-check text-warning"></i> <?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <hr>
                                <h6>Usage Limits:</h6>
                                <ul class="list-unstyled text-success small">
                                    <li>• Unlimited prompts</li>
                                    <li>• <?php echo number_format($premiumTier['max_ai_generations_per_month']); ?> AI generations per month</li>
                                    <li>• Unlimited categories</li>
                                    <li>• Unlimited bookmarks</li>
                                    <li>• <?php echo number_format($premiumTier['max_notes']); ?> notes (Lifetime)</li>
                                    <li>• <?php echo number_format($premiumTier['max_documents']); ?> documents (Lifetime)</li>
                                    <li>• <?php echo number_format($premiumTier['max_videos']); ?> videos (Lifetime)</li>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <?php if ($paymentProcessor->isConfigured()): ?>
                                    <button type="button" class="btn btn-warning btn-lg btn-upgrade" id="upgradeBtn">
                                        <i class="fas fa-crown"></i> Upgrade Now - <?php echo $appSettings->formatPrice($premiumTier['price_annual']); ?>/year
                                    </button>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-shield-alt"></i> Secure payment powered by Paystack
                                    </small>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg" disabled>
                                        <i class="fas fa-cog"></i> Payment Not Configured
                                    </button>
                                    <small class="text-muted d-block mt-2">
                                        Contact support to enable payments
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> What You Get with Premium</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-infinity text-primary fa-3x mb-3"></i>
                                <h6>Unlimited Prompts</h6>
                                <p class="text-muted small">Create as many prompts as you need without monthly limits</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-robot text-info fa-3x mb-3"></i>
                                <h6>300 AI Generations</h6>
                                <p class="text-muted small">More AI-powered prompt generation and enhancement</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-tags text-success fa-3x mb-3"></i>
                                <h6>Unlimited Categories</h6>
                                <p class="text-muted small">Organize your prompts with unlimited custom categories</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-download text-warning fa-3x mb-3"></i>
                                <h6>Export Functionality</h6>
                                <p class="text-muted small">Export your prompts and data in various formats</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-headset text-danger fa-3x mb-3"></i>
                                <h6>Priority Support</h6>
                                <p class="text-muted small">Get faster response times and dedicated assistance</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-chart-line text-purple fa-3x mb-3"></i>
                                <h6>Advanced Analytics</h6>
                                <p class="text-muted small">Detailed insights into your prompt usage and performance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="card">
                                <div class="card-header" id="faq1">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse1">
                                            Can I cancel my subscription anytime?
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapse1" class="collapse" data-parent="#faqAccordion">
                                    <div class="card-body">
                                        Yes, you can cancel your premium subscription at any time. You'll continue to have access to premium features until the end of your billing period.
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header" id="faq2">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse2">
                                            What happens to my data if I downgrade?
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapse2" class="collapse" data-parent="#faqAccordion">
                                    <div class="card-body">
                                        Your existing prompts and categories remain intact. You'll simply be subject to the Personal plan usage limits for new creations.
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header" id="faq3">
                                    <h6 class="mb-0">
                                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse3">
                                            Is my payment information secure?
                                        </button>
                                    </h6>
                                </div>
                                <div id="collapse3" class="collapse" data-parent="#faqAccordion">
                                    <div class="card-body">
                                        Absolutely. We use Paystack, a trusted payment processor that handles all payment data securely. We never store your payment information on our servers.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
// Initialize Paystack
const paystackPublicKey = '<?php echo $paymentProcessor->getPublicKey(); ?>';

function payWithPaystack(email, amount, reference, metadata, currency = 'GHS') {
    const handler = PaystackPop.setup({
        key: paystackPublicKey,
        email: email,
        amount: amount * 100, // Convert to pesewas/kobo
        currency: currency,
        ref: reference,
        metadata: metadata,
        callback: function(response) {
            // Payment successful
            window.location.href = 'index.php?page=payment_callback&reference=' + response.reference;
        },
        onClose: function() {
            alert('Payment cancelled');
        }
    });
    handler.openIframe();
}

// Handle upgrade button clicks
document.addEventListener('DOMContentLoaded', function() {
    const upgradeButton = document.getElementById('upgradeBtn');
    if (upgradeButton) {
        upgradeButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const email = '<?php echo htmlspecialchars($user['email']); ?>';
            const amount = <?php echo $premiumTier['price_annual'] ?? 100; ?>;
            const currency = '<?php echo $appSettings->getPaymentCurrency(); ?>';
            const reference = 'promptash_' + Date.now();
            const metadata = {
                user_id: <?php echo $user['id']; ?>,
                tier_id: <?php echo $premiumTier['id'] ?? 2; ?>,
                upgrade_type: 'premium_subscription',
                currency: currency
            };
            
            payWithPaystack(email, amount, reference, metadata, currency);
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
