<?php
$page_title = 'Two-Factor Authentication';
$user = $auth->getCurrentUser();

require_once __DIR__ . '/../../helpers/PasskeyHelper.php';

// Initialize models
$passkeyHelper = new PasskeyHelper();
$registeredPasskeys = $passkeyHelper->getPasskeysForUser($user['id']);
$hasPasskeys = !empty($registeredPasskeys);

$userModel = new User();
$twoFactorData = $userModel->get2FAData($user['id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'enable_2fa':
                if (isset($_POST['verification_code']) && isset($_POST['secret'])) {
                    $secret = $_POST['secret'];
                    $code = $_POST['verification_code'];
                    
                    if (TOTP::verifyCode($secret, $code)) {
                        $recovery_codes = TOTP::generateRecoveryCodes();
                        if ($userModel->enable2FA($user['id'], $secret, $recovery_codes)) {
                            $success_message = "Two-factor authentication has been enabled successfully!";
                            $twoFactorData = $userModel->get2FAData($user['id']);
                        } else {
                            $error_message = "Failed to enable two-factor authentication. Please try again.";
                        }
                    } else {
                        $error_message = "Invalid verification code. Please try again.";
                    }
                }
                break;
                
            case 'disable_2fa':
                if (isset($_POST['current_password'])) {
                    // Verify current password before disabling 2FA
                    $auth_helper = new Auth();
                    if ($auth_helper->verifyPassword($user['username'], $_POST['current_password'])) {
                        if ($userModel->disable2FA($user['id'])) {
                            $success_message = "Two-factor authentication has been disabled.";
                            $twoFactorData = $userModel->get2FAData($user['id']);
                        } else {
                            $error_message = "Failed to disable two-factor authentication. Please try again.";
                        }
                    } else {
                        $error_message = "Invalid password. Please enter your current password to disable 2FA.";
                    }
                }
                break;
                
            case 'regenerate_codes':
                if (isset($_POST['current_password'])) {
                    $auth_helper = new Auth();
                    if ($auth_helper->verifyPassword($user['username'], $_POST['current_password'])) {
                        $new_recovery_codes = TOTP::generateRecoveryCodes();
                        if ($userModel->updateRecoveryCodes($user['id'], $new_recovery_codes)) {
                            $success_message = "Recovery codes have been regenerated successfully!";
                            $twoFactorData = $userModel->get2FAData($user['id']);
                        } else {
                            $error_message = "Failed to regenerate recovery codes. Please try again.";
                        }
                    } else {
                        $error_message = "Invalid password. Please enter your current password to regenerate codes.";
                    }
                }
                break;
        }
    }
}

// Generate new secret for setup if 2FA is not enabled
$setup_secret = null;
if (!$twoFactorData['two_factor_enabled']) {
    $setup_secret = TOTP::generateSecret();
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-shield-alt"></i> Two-Factor Authentication (2FA)</h4>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($twoFactorData['two_factor_enabled']): ?>
                    <!-- 2FA is enabled -->
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Two-factor authentication is <strong>enabled</strong> for your account.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-key"></i> Recovery Codes</h5>
                            <p class="text-muted small">
                                Save these recovery codes in a safe place. You can use them to access your account if you lose your authenticator device.
                            </p>
                            <div class="recovery-codes bg-light p-3 rounded">
                                <?php foreach ($twoFactorData['two_factor_recovery_codes'] as $code): ?>
                                    <code class="d-block mb-1"><?php echo $code; ?></code>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="copyRecoveryCodes()">
                                <i class="fas fa-copy"></i> Copy Codes
                            </button>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-cog"></i> Manage 2FA</h5>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#regenerateCodesModal">
                                    <i class="fas fa-sync"></i> Regenerate Recovery Codes
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#disable2FAModal">
                                    <i class="fas fa-times"></i> Disable 2FA
                                </button>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- 2FA is not enabled -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Two-factor authentication is <strong>not enabled</strong> for your account.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-mobile-alt"></i> Step 1: Install Authenticator App</h5>
                            <p class="text-muted">
                                Install an authenticator app on your mobile device:
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fab fa-google"></i> Google Authenticator</li>
                                <li><i class="fas fa-shield-alt"></i> Authy</li>
                                <li><i class="fab fa-microsoft"></i> Microsoft Authenticator</li>
                                <li><i class="fas fa-lock"></i> 1Password</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-qrcode"></i> Step 2: Scan QR Code</h5>
                            <p class="text-muted">
                                Scan this QR code with your authenticator app:
                            </p>
                            <div class="text-center mb-3">
                                <img id="qrcode" src="<?php echo TOTP::getQRCodeUrl($setup_secret, $user['email']); ?>" 
                                     alt="QR Code" class="img-fluid" style="max-width: 200px;">
                            </div>
                            <div class="text-center">
                                <small class="text-muted">
                                    Manual entry key: <code id="manual-key"><?php echo $setup_secret; ?></code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="copyManualKey()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h5><i class="fas fa-check"></i> Step 3: Verify Setup</h5>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="enable_2fa">
                        <input type="hidden" name="secret" value="<?php echo $setup_secret; ?>">
                        <div class="col-md-6">
                            <label for="verification_code" class="form-label">Enter verification code from your app:</label>
                            <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Enable 2FA
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ** NEW ** Passkey Management Section -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-fingerprint"></i> Passkeys</h4>
            </div>
            <div class="card-body">
                <div id="passkeyStatus" class="alert <?php echo $hasPasskeys ? 'alert-success' : 'alert-secondary'; ?>">
                    <?php if ($hasPasskeys): ?>
                        <i class="fas fa-check-circle"></i> Passkeys are <strong>enabled</strong> for your account.
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i> No passkeys are registered yet.
                    <?php endif; ?>
                </div>

                                <ul class="list-group mb-3<?php echo $hasPasskeys ? '' : ' d-none'; ?>" id="passkeyList">
                    <?php if ($hasPasskeys): ?>
                        <?php foreach ($registeredPasskeys as $registeredPasskey): ?>
                            <?php
                                $displayName = $registeredPasskey['display_name'] ?? 'Passkey';
                                $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
                                $addedOn = $registeredPasskey['added_on_formatted'] ?? '';
                                $safeAdded = htmlspecialchars($addedOn, ENT_QUOTES, 'UTF-8');
                                $passkeyId = isset($registeredPasskey['id']) ? (int)$registeredPasskey['id'] : 0;
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2"
                                data-passkey-id="<?php echo $passkeyId; ?>"
                                data-passkey-name="<?php echo $safeName; ?>">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-fingerprint me-2"></i>
                                    <span class="passkey-name"><?php echo $safeName; ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($safeAdded)): ?>
                                        <span class="text-muted small"><?php echo $safeAdded; ?></span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-passkey-action="rename"
                                            data-passkey-id="<?php echo $passkeyId; ?>"
                                            data-passkey-name="<?php echo $safeName; ?>">
                                        <i class="fas fa-edit"></i> Rename
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-passkey-action="delete"
                                            data-passkey-id="<?php echo $passkeyId; ?>"
                                            data-passkey-name="<?php echo $safeName; ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <p class="text-muted small">
                    Register a passkey to sign in with your device's fingerprint, face recognition, or a hardware security key.
                </p>
                <button type="button" class="btn btn-primary" id="registerPasskeyBtn">
                    <i class="fas fa-plus"></i> Register a New Passkey
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Disable 2FA Modal -->
<div class="modal fade" id="disable2FAModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> Disable Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="disable_2fa">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Disabling two-factor authentication will make your account less secure.
                    </div>
                    <div class="mb-3">
                        <label for="disable_password" class="form-label">Enter your current password to confirm:</label>
                        <input type="password" class="form-control" id="disable_password" name="current_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Disable 2FA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Regenerate Recovery Codes Modal -->
<div class="modal fade" id="regenerateCodesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sync"></i> Regenerate Recovery Codes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="regenerate_codes">
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will invalidate your current recovery codes and generate new ones.
                    </div>
                    <div class="mb-3">
                        <label for="regenerate_password" class="form-label">Enter your current password to confirm:</label>
                        <input type="password" class="form-control" id="regenerate_password" name="current_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Regenerate Codes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyRecoveryCodes() {
    const codes = <?php echo json_encode($twoFactorData['two_factor_recovery_codes'] ?? []); ?>;
    const text = codes.join('\n');
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Recovery codes copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Recovery codes copied to clipboard!', 'success');
    }
}

function copyManualKey() {
    const key = document.getElementById('manual-key').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(key).then(() => {
            showToast('Manual key copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = key;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Manual key copied to clipboard!', 'success');
    }
}

// Auto-format verification code input
document.addEventListener('DOMContentLoaded', function() {
    const verificationInput = document.getElementById('verification_code');
    if (verificationInput) {
        verificationInput.addEventListener('input', function(e) {
            // Remove non-digits
            this.value = this.value.replace(/\D/g, '');
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
