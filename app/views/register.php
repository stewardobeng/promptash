<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../helpers/CheckoutHelper.php';
require_once __DIR__ . '/../models/MembershipTier.php';

$checkoutHelper = new CheckoutHelper();
$membershipModel = new MembershipTier();

$token = $_GET['token'] ?? ($_SESSION['registration_token'] ?? null);
if (!$token) {
    $_SESSION['checkout_error'] = 'Please choose a plan to continue your registration.';
    header('Location: index.php?page=checkout');
    exit();
}

try {
    $pendingCheckout = $checkoutHelper->getByToken($token);
} catch (Exception $e) {
    $_SESSION['checkout_error'] = 'Unable to verify your checkout session. Please select a plan again.';
    header('Location: index.php?page=checkout');
    exit();
}

if (!$pendingCheckout) {
    $_SESSION['checkout_error'] = 'Registration link expired or invalid. Please choose a plan again.';
    header('Location: index.php?page=checkout');
    exit();
}

// Ensure token is still valid
if (!empty($pendingCheckout['expires_at']) && strtotime($pendingCheckout['expires_at']) < time()) {
    $checkoutHelper->expireToken($token);
    $_SESSION['checkout_error'] = 'Your registration link has expired. Please choose a plan again.';
    header('Location: index.php?page=checkout');
    exit();
}

if ($pendingCheckout['status'] === 'pending') {
    $_SESSION['checkout_error'] = 'Payment is still pending. Please complete checkout before registering.';
    header('Location: index.php?page=checkout');
    exit();
}

if ($pendingCheckout['status'] === 'completed') {
    $_SESSION['checkout_error'] = 'This registration link has already been used. Please sign in or choose a new plan.';
    header('Location: index.php?page=login');
    exit();
}

if ($pendingCheckout['status'] === 'expired') {
    $_SESSION['checkout_error'] = 'Your registration link has expired. Please choose a plan again.';
    header('Location: index.php?page=checkout');
    exit();
}

$isTrial = (int)$pendingCheckout['is_trial'] === 1;
if ($isTrial && $pendingCheckout['status'] !== 'authorized') {
    $_SESSION['checkout_error'] = 'The free trial could not be confirmed. Please start a new trial.';
    header('Location: index.php?page=checkout&plan=personal&trial=1');
    exit();
}

if (!$isTrial && $pendingCheckout['status'] !== 'paid') {
    $_SESSION['checkout_error'] = 'Payment could not be verified. Please try checkout again.';
    header('Location: index.php?page=checkout');
    exit();
}

$_SESSION['registration_token'] = $token;

$selectedTier = $membershipModel->getTierByName($pendingCheckout['plan_name']);
if (!$selectedTier) {
    $_SESSION['checkout_error'] = 'Selected plan is no longer available. Please choose a plan again.';
    header('Location: index.php?page=checkout');
    exit();
}

$billingCycle = $pendingCheckout['billing_cycle'];

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
        Security::logSecurityEvent('csrf_token_invalid', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'action' => 'registration'
        ]);
    } else {
        $postedToken = $_POST['checkout_token'] ?? '';
        if ($postedToken !== $token) {
            $error = 'Registration session mismatch. Please restart the checkout process.';
        } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = 'Please fill in all fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } else {
            // Enhanced password validation
            $passwordValidation = Security::validatePasswordStrength($password);
            if (!$passwordValidation['valid']) {
                $error = 'Password requirements: ' . implode(', ', $passwordValidation['errors']);
            } else {
                $userId = $auth->register($username, $email, $password, $first_name, $last_name);
                if ($userId) {
                    try {
                        $checkoutHelper->completeRegistration($token, $userId);

                        // Log successful registration
                        Security::logSecurityEvent('user_registered', [
                            'username' => $username,
                            'email' => $email,
                            'ip' => $_SERVER['REMOTE_ADDR'],
                            'plan' => $pendingCheckout['plan_name'],
                            'billing_cycle' => $billingCycle,
                            'trial' => $isTrial
                        ]);

                        unset($_SESSION['registration_token']);

                        $message = $isTrial
                            ? 'Registration successful! Your 7-day Personal trial is active. Please login to get started.'
                            : 'Registration successful! Please login to access your account.';

                        header('Location: index.php?page=login&registration=success&message=' . urlencode($message));
                        exit();
                    } catch (Exception $e) {
                        require_once __DIR__ . '/../models/User.php';
                        $userModel = new User();
                        $userModel->deleteUser($userId);
                        $error = 'We could not finalise your plan. Please contact support or try again. (' . htmlspecialchars($e->getMessage()) . ')';
                    }
                } else {
                    $error = 'Username or email already exists.';
                    Security::logSecurityEvent('registration_failed', [
                        'username' => $username,
                        'email' => $email,
                        'reason' => 'duplicate_credentials'
                    ]);
                }
            }
        }
        }
    }
}

// Generate CSRF token for forms
$csrfToken = Security::generateCSRFToken();

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php?page=dashboard');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Promptash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #0052a3;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 171, 0.15);
        }
        .btn-register {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 102, 171, 0.4);
            background: linear-gradient(135deg, #0052a3 0%, #004d85 50%, #0066ab 100%);
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-card">
                <div class="register-header">
                    <h1><i class="fas fa-magic"></i> Promptash</h1>
                    <p class="mb-0">Create your account</p>
                </div>
                
                <div class="register-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="checkout_token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
                            <i class="fas fa-layer-group fa-lg"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($selectedTier['display_name']); ?></strong>
                                <?php if (!$isTrial): ?>
                                    <div class="small mb-0">
                                        Billing: <?php echo ucfirst($billingCycle); ?> &nbsp;•&nbsp; Amount: GHS <?php echo number_format($billingCycle === 'annual' ? $selectedTier['price_annual'] : $selectedTier['price_monthly'], 0); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="small mb-0">7-day Personal Trial · No payment required</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                            </div>
                            <div class="form-text">Only letters, numbers, and underscores allowed.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group position-relative">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                            </div>
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group position-relative">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-register w-100">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account?</p>
                        <a href="index.php?page=login" class="btn btn-outline-primary">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
