<?php
require_once __DIR__ . '/Security.php';
// --- MODIFICATION START: Added User model requirement ---
require_once __DIR__ . '/../app/models/User.php';
// --- MODIFICATION END ---

class Auth {
    private $db;
    // --- MODIFICATION START: Added userModel property ---
    private $userModel;
    // --- MODIFICATION END ---

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        // --- MODIFICATION START: Initialized User model ---
        $this->userModel = new User();
        // --- MODIFICATION END ---
    }

    public function login($username, $password, $two_factor_code = null, $recovery_code = null) {
        try {
            // Check rate limiting
            $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $username;
            $rateCheck = Security::checkRateLimit($identifier);
            
            if (!$rateCheck['allowed']) {
                $remainingMinutes = ceil($rateCheck['remaining_time'] / 60);
                Security::logSecurityEvent('rate_limit_exceeded', [
                    'username' => $username,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'remaining_time' => $rateCheck['remaining_time']
                ]);
                return [
                    'success' => false, 
                    'error' => "Too many failed attempts. Try again in {$remainingMinutes} minutes."
                ];
            }
            $query = "SELECT id, username, email, password, first_name, last_name, role, is_active, 
                            two_factor_enabled, two_factor_secret, two_factor_recovery_codes
                      FROM users WHERE (username = :username OR email = :username) AND is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                // Skip password verification if we're doing 2FA verification with temp session
                $skip_password = ($two_factor_code !== null || $recovery_code !== null) && isset($_SESSION['temp_user_id']);
                
                if ($skip_password || password_verify($password, $user['password'])) {
                    
                    // --- MODIFICATION START: Maintenance Mode Check ---
                    // Check for maintenance mode *before* setting the session.
                    require_once __DIR__ . '/../app/models/AppSettings.php';
                    $appSettings = new AppSettings();
                    
                    if ($appSettings->isMaintenanceMode() && $user['role'] !== 'admin') {
                        // Log the attempt
                        Security::logSecurityEvent('login_denied_maintenance', [
                            'username' => $username,
                            'ip' => $_SERVER['REMOTE_ADDR']
                        ]);
                        // Prevent non-admin login during maintenance
                        return ['success' => false, 'error' => 'The site is under maintenance. Only administrators can log in.'];
                    }
                    // --- MODIFICATION END ---

                    // Check if 2FA is enabled
                    if ($user['two_factor_enabled']) {
                        // 2FA is enabled, verify the code
                        if ($two_factor_code !== null) {
                            // Verify TOTP code
                            if (TOTP::verifyCode($user['two_factor_secret'], $two_factor_code)) {
                                $this->setUserSession($user);
                                return ['success' => true, 'requires_2fa' => false];
                            } else {
                                return ['success' => false, 'error' => 'Invalid verification code.'];
                            }
                        } elseif ($recovery_code !== null) {
                            // Verify recovery code
                            $recovery_codes = json_decode($user['two_factor_recovery_codes'], true);
                            if (TOTP::verifyRecoveryCode($recovery_code, $recovery_codes)) {
                                // Remove used recovery code
                                $updated_codes = TOTP::removeRecoveryCode($recovery_code, $recovery_codes);
                                $this->updateRecoveryCodes($user['id'], $updated_codes);
                                $this->setUserSession($user);
                                return ['success' => true, 'requires_2fa' => false, 'recovery_used' => true];
                            } else {
                                return ['success' => false, 'error' => 'Invalid recovery code.'];
                            }
                        } else {
                            // Store user data temporarily for 2FA verification
                            $_SESSION['temp_user_id'] = $user['id'];
                            $_SESSION['temp_username'] = $username;
                            return ['success' => true, 'requires_2fa' => true];
                        }
                    } else {
                        // No 2FA required
                        $this->setUserSession($user);
                        
                        // Clear rate limit on successful login
                        Security::clearRateLimit($identifier);
                        
                        Security::logSecurityEvent('login_success', [
                            'username' => $username,
                            'user_id' => $user['id']
                        ]);
                        
                        return ['success' => true, 'requires_2fa' => false];
                    }
                }
            }
            
            // Record failed attempt
            Security::recordFailedAttempt($identifier);
            
            Security::logSecurityEvent('login_failed', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return ['success' => false, 'error' => 'Invalid username or password.'];
        } catch(PDOException $exception) {
            error_log("Login error: " . $exception->getMessage());
            return ['success' => false, 'error' => 'Login failed. Please try again.'];
        }
    }

    // ** NEW ** Method to log in a user after successful passkey verification
    public function loginWithPasskey($userId) {
        try {
            $query = "SELECT id, username, email, password, first_name, last_name, role, is_active, 
                            two_factor_enabled, two_factor_secret, two_factor_recovery_codes
                      FROM users WHERE id = :user_id AND is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                $this->setUserSession($user);
                Security::logSecurityEvent('passkey_login_success', [
                    'username' => $user['username'],
                    'user_id' => $user['id']
                ]);
                return true;
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Passkey login error: " . $exception->getMessage());
            return false;
        }
    }

    public function register($username, $email, $password, $first_name, $last_name) {
        try {
            // Check if username or email already exists
            $query = "SELECT id FROM users WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return false; // User already exists
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $query = "INSERT INTO users (username, email, password, first_name, last_name) 
                      VALUES (:username, :email, :password, :first_name, :last_name)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);

            if ($stmt->execute()) {
                $user_id = (int)$this->db->lastInsertId();

                // Send welcome notification
                require_once __DIR__ . '/NotificationService.php';
                $notificationService = new NotificationService();

                $new_user = [
                    'id' => $user_id,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ];

                $notificationService->sendWelcomeNotification($new_user);

                error_log("Welcome notification sent to new user: {$email}");

                return $user_id;
            }

            return false;
        } catch(PDOException $exception) {
            error_log("Registration error: " . $exception->getMessage());
            return false;
        }
    }

    public function logout() {
        // Clear individual session variables first
        $_SESSION = [];
        
        // Destroy the session if it exists
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        return true;
    }

    public function enforceMembershipAccess($currentPage = '') {
        if (!$this->isLoggedIn()) {
            return;
        }

        $exemptPages = ['checkout', 'payment_callback', 'logout', 'upgrade', 'settings', 'register', 'login', 'landing', 'api'];
        if (in_array($currentPage, $exemptPages, true)) {
            return;
        }

        require_once __DIR__ . '/../app/models/User.php';
        $userModel = new User();
        $user = $this->getCurrentUser();

        if (!$user) {
            $this->logout();
            return;
        }

        // Administrators always retain full access
        if ($this->isOriginallyAdmin() || ($user['role'] ?? '') === 'admin') {
            if (empty($user['current_tier_id'])) {
                require_once __DIR__ . '/../app/models/MembershipTier.php';
                $membershipModel = new MembershipTier();
                $premium = $membershipModel->getPremiumTier();
                if ($premium) {
                    $userModel->updateMembershipTier($user['id'], $premium['id']);
                }
            }
            return;
        }

        $userModel->expireUserSubscriptions($user['id']);
        $activeSubscription = $userModel->getActiveSubscription($user['id']);
        if ($activeSubscription) {
            return;
        }

        $latestSubscription = $userModel->getLatestSubscription($user['id']);
        $message = 'Your subscription is inactive. Please choose a plan to continue.';
        if ($latestSubscription) {
            if ($latestSubscription['status'] === 'trial') {
                $message = 'Your Personal trial has ended. Please upgrade to continue.';
            } elseif ($latestSubscription['status'] === 'expired') {
                $message = 'Your subscription has expired. Please renew to continue.';
            }
        }

        $this->logout();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['membership_required'] = $message;
        header('Location: index.php?page=checkout');
        exit();
    }

    public function isLoggedIn() {
        // Check basic session status
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }

        // Validate session integrity - ensure required fields exist
        $required_fields = ['user_id', 'username', 'email', 'first_name', 'last_name', 'role'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($_SESSION[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        // Only logout if critical fields are missing
        if (in_array('user_id', $missing_fields) || in_array('username', $missing_fields)) {
            error_log('Critical session fields missing: ' . implode(', ', $missing_fields));
            $this->logout();
            return false;
        }
        
        return true;
    }

    public function isAdmin() {
        // --- MODIFICATION START: Reverted isAdmin logic ---
        // This now ONLY checks the role of the currently active user in the session.
        // This is the correct behavior for hiding/showing UI elements.
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
        // --- MODIFICATION END ---
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            // Get fresh user data from database to include current_tier_id
            if (isset($_SESSION['user_id'])) {
                try {
                    // User model is now initialized in constructor
                    $freshUser = $this->userModel->getById($_SESSION['user_id']);
                    if ($freshUser) {
                        return [
                            'id' => $_SESSION['user_id'] ?? null,
                            'username' => $_SESSION['username'] ?? '',
                            'email' => $_SESSION['email'] ?? '',
                            'first_name' => $_SESSION['first_name'] ?? '',
                            'last_name' => $_SESSION['last_name'] ?? '',
                            'role' => $_SESSION['role'] ?? 'user',
                            'current_tier_id' => $freshUser['current_tier_id'] ?? null
                        ];
                    }
                } catch (Exception $e) {
                    error_log("Get current user fresh data error: " . $e->getMessage());
                }
            }
            
            // Fallback to session data
            return [
                'id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'first_name' => $_SESSION['first_name'] ?? '',
                'last_name' => $_SESSION['last_name'] ?? '',
                'role' => $_SESSION['role'] ?? 'user',
                'current_tier_id' => $_SESSION['current_tier_id'] ?? null
            ];
        }
        return null;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // --- MODIFICATION START: Updated redirect to use index.php router ---
            header('Location: index.php?page=login');
            // --- MODIFICATION END ---
            exit();
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        // --- MODIFICATION START: Use a new method for checking original admin status ---
        // We check if the original user was an admin, not the current one.
        if (!isset($_SESSION['original_role']) || $_SESSION['original_role'] !== 'admin') {
             // If not in a "login as" session, check the current role
             if (!$this->isAdmin()) {
                header('Location: index.php?page=dashboard');
                exit();
             }
        }
        // --- MODIFICATION END ---
    }

    // ===================================================================
    // --- LOGIN AS USER METHODS ---
    // ===================================================================

    /**
     * Initiate a "login as" session for an admin.
     */
    public function loginAs($user_id) {
        // We use a new method to check if the original user is admin.
        if (!$this->isOriginallyAdmin()) {
            return false;
        }

        $userToLoginAs = $this->userModel->getById($user_id);
        if ($userToLoginAs) {
            // Store original admin session if not already in "login as" mode
            if (!isset($_SESSION['original_user_id'])) {
                $_SESSION['original_user_id'] = $_SESSION['user_id'];
                $_SESSION['original_username'] = $_SESSION['username'];
                $_SESSION['original_role'] = $_SESSION['role'];
            }

            // Switch to the new user's session using the existing session handler
            $this->setUserSession($userToLoginAs);
            return true;
        }
        return false;
    }

    /**
     * Revert from a "login as" session back to the original admin.
     */
    public function revertLoginAs() {
        if (isset($_SESSION['original_user_id'])) {
            $originalAdmin = $this->userModel->getById($_SESSION['original_user_id']);
            if ($originalAdmin) {
                // Restore the original admin's session
                $this->setUserSession($originalAdmin);
                
                // Clear the "login as" session data
                unset($_SESSION['original_user_id']);
                unset($_SESSION['original_username']);
                unset($_SESSION['original_role']);
                return true;
            }
        }
        return false;
    }

    /**
     * Check if currently in a "login as" session.
     */
    public function isLoginAs() {
        return isset($_SESSION['original_user_id']);
    }

    /**
     * Generate a magic link for an admin to log in as another user.
     */
    public function generateLoginAsLink($user_id) {
        // Only an actual admin can generate a link.
        if ($this->isAdmin()) {
            $token = $this->userModel->generateLoginToken($user_id);
            if ($token) {
                return "index.php?page=login_as&token=" . $token;
            }
        }
        return '#';
    }

    // --- MODIFICATION START: New helper function ---
    /**
     * Checks if the user is an admin, either currently or originally.
     * This is useful for backend permissions checks where an admin should retain their power.
     */
    public function isOriginallyAdmin() {
        if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'admin') {
            return true;
        }
        return $this->isAdmin();
    }
    // --- MODIFICATION END ---
    
    /**
     * Set user session variables
     */
    private function setUserSession($user) {
        // Regenerate session ID on login to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['role'] = $this->normalizeRole($user['role'] ?? 'user');
        $_SESSION['logged_in'] = true;
        $_SESSION['two_factor_verified'] = true;
        $_SESSION['last_activity'] = time(); // Add activity tracking
        
        // Auto-upgrade admin users to premium tier
        if ($this->normalizeRole($user['role'] ?? 'user') === 'admin') {
            $this->ensureAdminHasPremiumTier($user['id']);
        }
        
        // Clear temporary session data
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
    }
    
    /**
     * Normalize role values so application logic only has to reason
     * about "admin" and "user".
     */
    private function normalizeRole($role) {
        $normalized = strtolower(trim((string)$role));
        if (in_array($normalized, ['admin', 'administrator', 'super_admin'], true)) {
            return 'admin';
        }
        return 'user';
    }
    
    /**
     * Update recovery codes for a user
     */
    private function updateRecoveryCodes($user_id, $recovery_codes) {
        try {
            $query = "UPDATE users SET two_factor_recovery_codes = :codes WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':codes', json_encode($recovery_codes));
            $stmt->bindParam(':user_id', $user_id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update recovery codes error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Ensure admin users have premium tier
     */
    private function ensureAdminHasPremiumTier($user_id) {
        try {
            require_once __DIR__ . '/../app/models/MembershipTier.php';
            
            $tierModel = new MembershipTier();
            
            // Get current user data
            $user = $this->userModel->getById($user_id);
            if (!$user) {
                return false;
            }
            
            // Get premium tier
            $premiumTier = $tierModel->getPremiumTier();
            if (!$premiumTier) {
                error_log("Premium tier not found when trying to upgrade admin user {$user_id}");
                return false;
            }
            
            // Check if user already has premium tier
            if ($user['current_tier_id'] && $user['current_tier_id'] == $premiumTier['id']) {
                return true; // Already has premium tier
            }
            
            // Upgrade admin to premium tier
            $result = $this->userModel->updateMembershipTier($user_id, $premiumTier['id']);
            if ($result) {
                error_log("Admin user {$user_id} automatically upgraded to premium tier");
                // Update session with new tier information
                $_SESSION['current_tier_id'] = $premiumTier['id'];
            } else {
                error_log("Failed to upgrade admin user {$user_id} to premium tier");
            }
            
            return $result;
            
        } catch(Exception $exception) {
            error_log("Ensure admin premium tier error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Verify password for current user
     */
    public function verifyPassword($username, $password) {
        try {
            $query = "SELECT password FROM users WHERE (username = :username OR email = :username) AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                return password_verify($password, $user['password']);
            }
            return false;
        } catch(PDOException $exception) {
            error_log("Verify password error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is in 2FA verification state
     */
    public function requiresTwoFactor() {
        return isset($_SESSION['temp_user_id']) && !isset($_SESSION['logged_in']);
    }
    
    /**
     * Clear 2FA verification state
     */
    public function clearTwoFactorState() {
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
    }
}
?>
