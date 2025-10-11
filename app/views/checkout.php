<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../helpers/PaymentProcessor.php';
require_once __DIR__ . '/../../helpers/CheckoutHelper.php';

$checkoutHelper = new CheckoutHelper();
$paymentProcessor = new PaymentProcessor();
$paystackConfigured = $paymentProcessor->isConfigured();
$paystackPublicKey = $paymentProcessor->getPublicKey();

$tiersByName = [];
foreach ($publicTiers as $tier) {
    $tiersByName[$tier['name']] = $tier;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'start_trial':
                $plan = $_POST['plan'] ?? 'personal';
                $trial = $checkoutHelper->createTrialToken($plan);
                $_SESSION['registration_token'] = $trial['token'];
                echo json_encode([
                    'success' => true,
                    'token' => $trial['token'],
                    'redirect' => 'index.php?page=register&token=' . urlencode($trial['token'])
                ]);
                break;
            case 'init_payment':
                $plan = $_POST['plan'] ?? 'personal';
                $billing = $_POST['billing_cycle'] ?? 'monthly';
                $email = trim($_POST['email'] ?? '');
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address to continue.');
                }
                $payment = $checkoutHelper->initializePayment($plan, $billing, $email);
                $_SESSION['registration_token'] = $payment['token'];
                echo json_encode(array_merge(['success' => true], $payment));
                break;
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
    exit();
}

$page_title = 'Choose Your Plan';
$personalTier = $tiersByName['personal'] ?? null;
$premiumTier = $tiersByName['premium'] ?? null;

$alertMessage = '';
if (isset($_SESSION['membership_required'])) {
    $alertMessage = $_SESSION['membership_required'];
    unset($_SESSION['membership_required']);
}

$checkoutError = '';
if (isset($_SESSION['checkout_error'])) {
    $checkoutError = $_SESSION['checkout_error'];
    unset($_SESSION['checkout_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Plan - Promptash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0a1f33, #05111c);
            min-height: 100vh;
            padding: 40px 0;
            font-family: 'Inter', sans-serif;
            color: #fff;
        }
        .checkout-container {
            max-width: 1080px;
            margin: 0 auto;
        }
        .plan-card {
            background: #0f1f30;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 18px 40px rgba(5, 17, 28, 0.35);
            height: 100%;
        }
        .plan-card.premium {
            border-image: linear-gradient(135deg, #ffb347, #ffcc33) 1;
        }
        .plan-title {
            font-weight: 700;
            font-size: 1.4rem;
        }
        .price-tag {
            font-size: 2.4rem;
            font-weight: 800;
            margin: 12px 0;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            color: rgba(255,255,255,0.7);
        }
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .cta-button {
            display: block;
            width: 100%;
            border-radius: 999px;
            padding: 12px 16px;
            font-weight: 600;
            border: none;
        }
        .cta-button.primary {
            background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
            color: #fff;
        }
        .cta-button.secondary {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .cta-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.2);
        }
        .email-input {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .email-input:focus {
            box-shadow: none;
            border-color: #00c6ff;
        }
        .badge-premium {
            background: linear-gradient(135deg, #ffb347, #ffcc33);
            color: #1a1206;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="mb-4 text-center">
            <h1 class="display-5 fw-bold">Choose the plan that fits your workflow</h1>
            <p class="text-muted">Select a plan to continue. Registration unlocks after you pick a plan or start the personal trial.</p>
        </div>

        <?php if ($alertMessage): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($alertMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($checkoutError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($checkoutError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$paystackConfigured): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-shield-alt"></i> Online payments are temporarily unavailable. You can start a Personal trial now and upgrade later when payments are restored.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if ($personalTier): ?>
            <div class="col-lg-6">
                <div class="plan-card h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-user fa-lg text-info"></i>
                        <span class="plan-title">Personal Plan</span>
                    </div>
                    <p class="text-muted">Perfect for individual creators who want to stay organised.</p>
                    <div class="price-tag text-info">
                        GHS <?php echo number_format($personalTier['price_monthly'], 0); ?><span class="fs-6">/month</span>
                    </div>
                    <p class="text-muted">or GHS <?php echo number_format($personalTier['price_annual'], 0); ?> billed yearly</p>
                    <hr class="border-secondary">
                    <ul class="feature-list mb-4">
                        <?php foreach (($personalTier['features'] ?? []) as $feature): ?>
                            <li><i class="fas fa-check text-info"></i> <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                        <li><i class="fas fa-check text-info"></i> 50 prompts/month, 50 AI generations/month</li>
                        <li><i class="fas fa-check text-info"></i> 5 categories, 50 bookmarks &amp; notes</li>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label text-muted">Email for trial or payment</label>
                        <input type="email" class="form-control email-input" id="personalEmail" placeholder="you@example.com">
                    </div>
                    <div class="d-grid gap-2">
                        <button class="cta-button secondary" data-plan="personal" id="personalTrialButton">
                            <i class="fas fa-clock"></i> Start 7-day Personal Trial
                        </button>
                        <button class="cta-button primary" data-plan="personal" data-billing="monthly">
                            <i class="fas fa-wallet"></i> Pay Monthly (GHS <?php echo number_format($personalTier['price_monthly'], 0); ?>)
                        </button>
                        <button class="cta-button primary" data-plan="personal" data-billing="annual">
                            <i class="fas fa-credit-card"></i> Pay Yearly (GHS <?php echo number_format($personalTier['price_annual'], 0); ?>)
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($premiumTier): ?>
            <div class="col-lg-6">
                <div class="plan-card premium h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge badge-premium rounded-pill">Popular</span>
                        <span class="plan-title">Premium Plan</span>
                    </div>
                    <p class="text-muted">Unlimited prompts, AI boosts, and advanced workflow automation for teams.</p>
                    <div class="price-tag text-warning">
                        GHS <?php echo number_format($premiumTier['price_monthly'], 0); ?><span class="fs-6">/month</span>
                    </div>
                    <p class="text-muted">or GHS <?php echo number_format($premiumTier['price_annual'], 0); ?> billed yearly</p>
                    <hr class="border-secondary">
                    <ul class="feature-list mb-4">
                        <?php foreach (($premiumTier['features'] ?? []) as $feature): ?>
                            <li><i class="fas fa-crown text-warning"></i> <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mb-3">
                        <label class="form-label text-muted">Email for payment</label>
                        <input type="email" class="form-control email-input" id="premiumEmail" placeholder="team@example.com">
                    </div>
                    <div class="d-grid gap-2">
                        <button class="cta-button primary" data-plan="premium" data-billing="monthly">
                            <i class="fas fa-wallet"></i> Pay Monthly (GHS <?php echo number_format($premiumTier['price_monthly'], 0); ?>)
                        </button>
                        <button class="cta-button primary" data-plan="premium" data-billing="annual">
                            <i class="fas fa-credit-card"></i> Pay Yearly (GHS <?php echo number_format($premiumTier['price_annual'], 0); ?>)
                        </button>
                    </div>
                    <div class="small text-muted mt-3">
                        <i class="fas fa-shield-alt"></i> Secure payments handled by Paystack
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-5 p-4" style="background: rgba(255,255,255,0.07); border-radius: 18px;">
            <h4 class="fw-bold mb-3"><i class="fas fa-clipboard-check"></i> How registration works</h4>
            <ol class="text-muted">
                <li>Choose your plan or start the 7-day Personal trial.</li>
                <li>Complete checkout (trial skips payment) to unlock the registration form.</li>
                <li>Create your Promptash account using the secured registration link.</li>
                <li>When your trial ends, log back in to upgrade and keep everything active.</li>
            </ol>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        const paystackConfigured = <?php echo $paystackConfigured ? 'true' : 'false'; ?>;
        const checkoutUrl = 'index.php?page=checkout';

        function showToast(message, type = 'danger') {
            const alertBox = document.createElement('div');
            alertBox.className = 'alert alert-' + type;
            alertBox.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
            document.querySelector('.checkout-container').prepend(alertBox);
            setTimeout(function () { alertBox.remove(); }, 6000);
        }

        function postForm(data) {
            return fetch(checkoutUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            }).then(async function(response) {
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Unable to complete request.');
                }
                return payload;
            });
        }

        function handleTrial(plan) {
            const emailField = document.getElementById(plan + 'Email');
            if (emailField && emailField.value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(emailField.value)) {
                showToast('Please provide a valid email address.', 'warning');
                return;
            }

            postForm({ action: 'start_trial', plan: plan })
                .then(function(data) {
                    window.location.href = data.redirect;
                })
                .catch(function(err) { showToast(err.message); });
        }

        function handlePayment(plan, billingCycle) {
            if (!paystackConfigured) {
                showToast('Payments are temporarily unavailable. Please contact support.', 'warning');
                return;
            }

            const emailField = document.getElementById(plan + 'Email');
            const email = emailField ? emailField.value.trim() : '';
            if (!email) {
                showToast('Please enter an email to continue.', 'warning');
                return;
            }

            postForm({ action: 'init_payment', plan: plan, billing_cycle: billingCycle, email: email })
                .then(function(data) {
                    payWithPaystack(email, data.amount, data.reference, data.token, data.currency);
                })
                .catch(function(err) { showToast(err.message); });
        }

        function payWithPaystack(email, amount, reference, token, currency) {
            const handler = PaystackPop.setup({
                key: '<?php echo $paystackPublicKey; ?>',
                email: email,
                amount: Math.round(parseFloat(amount) * 100),
                currency: currency,
                reference: reference,
                metadata: {
                    checkout_token: token
                },
                callback: function(response) {
                    window.location.href = 'index.php?page=payment_callback&reference=' + response.reference;
                },
                onClose: function() {
                    showToast('Payment window closed before completion.', 'warning');
                }
            });

            handler.openIframe();
        }

        document.querySelectorAll('.cta-button.primary').forEach(function(button) {
            button.addEventListener('click', function() {
                const plan = button.getAttribute('data-plan');
                const billing = button.getAttribute('data-billing');
                handlePayment(plan, billing);
            });
        });

        const trialButton = document.getElementById('personalTrialButton');
        if (trialButton) {
            trialButton.addEventListener('click', function() { handleTrial('personal'); });
        }
    </script>
</body>
</html>
