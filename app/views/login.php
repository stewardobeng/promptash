<?php
$error = '';
$success = '';
$show_2fa = false;

// Handle URL parameters for messages
if (isset($_GET['registration']) && $_GET['registration'] === 'success' && isset($_GET['message'])) {
    $success = urldecode($_GET['message']);
} elseif (isset($_GET['registration']) && $_GET['registration'] === 'disabled') {
    $error = 'Registration is currently disabled.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
        Security::logSecurityEvent('csrf_token_invalid', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    } else {
        if (isset($_POST['action']) && $_POST['action'] === '2fa_verify') {
            // Handle 2FA verification
            $username = $_SESSION['temp_username'] ?? '';
            $two_factor_code = $_POST['two_factor_code'] ?? null;
            $recovery_code = $_POST['recovery_code'] ?? null;
            
            if ($two_factor_code) {
                $result = $auth->login($username, '', $two_factor_code);
            } elseif ($recovery_code) {
                $result = $auth->login($username, '', null, $recovery_code);
            } else {
                $result = ['success' => false, 'error' => 'Please enter a verification code or recovery code.'];
            }
            
            if ($result['success']) {
                if (isset($result['recovery_used'])) {
                    $success = 'Login successful using recovery code. Please generate new recovery codes.';
                }
                header('Location: index.php?page=dashboard');
                exit();
            } else {
                $error = $result['error'];
                $show_2fa = true;
            }
        } else {
            // Handle initial login
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if (empty($username) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $result = $auth->login($username, $password);
                
                if ($result['success']) {
                    if ($result['requires_2fa']) {
                        $show_2fa = true;
                    } else {
                        header('Location: index.php?page=dashboard');
                        exit();
                    }
                } else {
                    $error = $result['error'];
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

// Check if user is in 2FA verification state
if ($auth->requiresTwoFactor()) {
    $show_2fa = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Promptash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
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
        .btn-login {
            background: linear-gradient(135deg, #0066ab 0%, #004d85 50%, #0052a3 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
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
        <div class="login-container">
            <?php if (isset($appSettings) && $appSettings->isMaintenanceMode()): ?>
                <div class="alert alert-warning text-center" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Under Maintenance</h5>
                    <p class="mb-0">The site is currently undergoing scheduled maintenance. Only administrators can log in at this time.</p>
                </div>
            <?php endif; ?>
            <div class="login-card">
                <div class="login-header">
                    <h1><i class="fas fa-magic"></i> Promptash</h1>
                    <p class="mb-0">Welcome back!</p>
                </div>
                
                <div class="login-body">
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

                    <?php if ($show_2fa): ?>
                        <div class="text-center mb-3">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-2"></i>
                            <h5>Two-Factor Authentication</h5>
                            <p class="text-muted">Enter the verification code from your authenticator app</p>
                        </div>
                        
                        <form method="POST" id="twoFactorForm">
                            <input type="hidden" name="action" value="2fa_verify">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="mb-3">
                                <label for="two_factor_code" class="form-label">Verification Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="text" class="form-control text-center" id="two_factor_code" name="two_factor_code" 
                                           placeholder="000000" maxlength="6" pattern="[0-9]{6}" autocomplete="off">
                                </div>
                                <small class="form-text text-muted">Enter the 6-digit code from your authenticator app</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                                <i class="fas fa-check"></i> Verify Code
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-link btn-sm" onclick="toggleRecoveryCode()">
                                <i class="fas fa-life-ring"></i> Use recovery code instead
                            </button>
                        </div>
                        
                        <form method="POST" id="recoveryForm" style="display: none;">
                            <input type="hidden" name="action" value="2fa_verify">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="mb-3">
                                <label for="recovery_code" class="form-label">Recovery Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-life-ring"></i></span>
                                    <input type="text" class="form-control" id="recovery_code" name="recovery_code" 
                                           placeholder="Enter recovery code" maxlength="8" autocomplete="off">
                                </div>
                                <small class="form-text text-muted">Enter one of your backup recovery codes</small>
                            </div>
                            
                            <button type="submit" class="btn btn-warning btn-login w-100 mb-3">
                                <i class="fas fa-unlock"></i> Use Recovery Code
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-link btn-sm" onclick="goBackToLogin()">
                                <i class="fas fa-arrow-left"></i> Back to login
                            </button>
                        </div>
                        
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group position-relative">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt"></i> Login with Password
                            </button>
                        </form>

                        <div class="text-center my-3">OR</div>
    
                        <button type="button" class="btn btn-outline-secondary w-100 mb-4" id="loginWithPasskeyBtn">
                            <i class="fas fa-fingerprint"></i> Login with a passkey
                        </button>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account?</p>
                            <a href="index.php?page=register" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script>
        function toggleRecoveryCode() {
            const twoFactorForm = document.getElementById('twoFactorForm');
            const recoveryForm = document.getElementById('recoveryForm');
            
            if (recoveryForm.style.display === 'none') {
                twoFactorForm.style.display = 'none';
                recoveryForm.style.display = 'block';
                document.getElementById('recovery_code').focus();
            } else {
                recoveryForm.style.display = 'none';
                twoFactorForm.style.display = 'block';
                document.getElementById('two_factor_code').focus();
            }
        }
        
        function goBackToLogin() {
            window.location.href = 'index.php?page=login';
        }
        
        // Auto-format 2FA code input
        document.addEventListener('DOMContentLoaded', function() {
            const twoFactorInput = document.getElementById('two_factor_code');
            if (twoFactorInput) {
                twoFactorInput.addEventListener('input', function(e) {
                    // Remove non-digits
                    this.value = this.value.replace(/\D/g, '');
                    // Limit to 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
                
                // Auto-focus on page load if showing 2FA form
                twoFactorInput.focus();
            }
            
            const recoveryInput = document.getElementById('recovery_code');
            if (recoveryInput) {
                recoveryInput.addEventListener('input', function(e) {
                    // Convert to uppercase and remove non-alphanumeric
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>
