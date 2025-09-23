<?php
$page_title = 'Password Reset';

// Initialize models
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../helpers/NotificationService.php';

$userModel = new User();
$notificationService = new NotificationService();

$error = '';
$success = '';
$step = 'request'; // 'request' or 'reset'

// Check if this is a reset token validation
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $step = 'reset';
    $token = $_GET['token'];
    
    // Verify token
    if (!$userModel->validatePasswordResetToken($token)) {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
        $step = 'request';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'request_reset':
                $email = trim($_POST['email'] ?? '');
                
                if (empty($email)) {
                    $error = 'Please enter your email address.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    // Check if user exists
                    $user = $userModel->getUserByEmail($email);
                    if ($user) {
                        // Generate reset token
                        $token = $userModel->createPasswordResetToken($user['id']);
                        if ($token) {
                            // Send reset email
                            $emailSent = $notificationService->sendPasswordResetNotification($user, $token);
                            if ($emailSent) {
                                $success = 'A password reset link has been sent to your email address. Please check your inbox and spam folder.';
                            } else {
                                $error = 'Failed to send reset email. Email service may not be configured. Please contact support.';
                            }
                        } else {
                            $error = 'Failed to generate reset token. Please try again.';
                        }
                    } else {
                        // Don't reveal if email exists for security
                        $success = 'If an account with that email exists, a password reset link has been sent.';
                    }
                }
                break;
                
            case 'reset_password':
                $token = $_POST['token'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($new_password)) {
                    $error = 'Please enter a new password.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    // Reset password
                    $reset_result = $userModel->resetPasswordWithToken($token, $new_password);
                    if ($reset_result) {
                        $success = 'Your password has been reset successfully. You can now log in with your new password.';
                        $step = 'complete';
                    } else {
                        $error = 'Failed to reset password. The token may be invalid or expired.';
                    }
                }
                break;
        }
    }
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-key fa-3x text-primary mb-3"></i>
                    <h3>Password Reset</h3>
                    <?php if ($step === 'request'): ?>
                        <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>
                    <?php elseif ($step === 'reset'): ?>
                        <p class="text-muted">Enter your new password below.</p>
                    <?php else: ?>
                        <p class="text-muted">Password reset complete.</p>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'request'): ?>
                    <!-- Request Reset Form -->
                    <form method="POST">
                        <input type="hidden" name="action" value="request_reset">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                            <a href="index.php?page=login" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                    
                <?php elseif ($step === 'reset'): ?>
                    <!-- Reset Password Form -->
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="Enter new password" required minlength="8">
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required minlength="8">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Reset Password
                            </button>
                            <a href="index.php?page=login" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <!-- Complete -->
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <h4 class="text-success">Password Reset Complete!</h4>
                        <p class="text-muted mb-4">Your password has been successfully changed.</p>
                        <a href="index.php?page=login" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fas fa-shield-alt"></i> 
                Password reset links expire after 1 hour for security.
            </small>
        </div>
    </div>
</div>

<script>
// Add password strength indicator
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let strengthText = '';
            let strengthClass = '';
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
            
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Very Weak';
                    strengthClass = 'text-danger';
                    break;
                case 2:
                    strengthText = 'Weak';
                    strengthClass = 'text-warning';
                    break;
                case 3:
                    strengthText = 'Fair';
                    strengthClass = 'text-info';
                    break;
                case 4:
                    strengthText = 'Good';
                    strengthClass = 'text-primary';
                    break;
                case 5:
                    strengthText = 'Strong';
                    strengthClass = 'text-success';
                    break;
            }
            
            // Update or create strength indicator
            let indicator = document.getElementById('password-strength');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'password-strength';
                indicator.className = 'form-text';
                newPasswordInput.parentNode.appendChild(indicator);
            }
            
            if (password.length > 0) {
                indicator.innerHTML = `<span class="${strengthClass}">Password strength: ${strengthText}</span>`;
            } else {
                indicator.innerHTML = 'Password must be at least 8 characters long.';
            }
        });
    }
    
    if (confirmPasswordInput && newPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = newPasswordInput.value;
            const confirm = this.value;
            
            let indicator = document.getElementById('password-match');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'password-match';
                indicator.className = 'form-text';
                this.parentNode.appendChild(indicator);
            }
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    indicator.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
                } else {
                    indicator.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
                }
            } else {
                indicator.innerHTML = '';
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>