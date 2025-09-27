<?php

class User {
    private $db;
    private $table_name = "users";

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getAllUsers($limit = 20, $offset = 0) {
        try {
            $query = "SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active, u.created_at,
                            u.current_tier_id, mt.display_name as tier_name,
                            (
                                SELECT us.status 
                                FROM user_subscriptions us 
                                WHERE us.user_id = u.id AND us.status = 'active' 
                                ORDER BY us.created_at DESC 
                                LIMIT 1
                            ) as subscription_status,
                            (
                                SELECT us.expires_at 
                                FROM user_subscriptions us 
                                WHERE us.user_id = u.id AND us.status = 'active' 
                                ORDER BY us.created_at DESC 
                                LIMIT 1
                            ) as expires_at
                     FROM " . $this->table_name . " u
                     LEFT JOIN membership_tiers mt ON mt.id = u.current_tier_id
                     ORDER BY u.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get users error: " . $exception->getMessage());
            return [];
        }
    }

    public function getUserById($id) {
        try {
            $query = "SELECT id, username, email, first_name, last_name, role, is_active, two_factor_enabled, current_tier_id, created_at 
                     FROM " . $this->table_name . " WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get user error: " . $exception->getMessage());
            return null;
        }
    }

    public function updateUser($id, $data) {
        try {
            $fields = [];
            $params = [':id' => $id];

            foreach ($data as $key => $value) {
                if (in_array($key, ['username', 'email', 'first_name', 'last_name', 'role', 'is_active', 'two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'password'])) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (empty($fields)) {
                return false;
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $exception) {
            error_log("Update user error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Update user profile (excludes username and admin-only fields)
     */
    public function updateProfile($id, $data) {
        try {
            $fields = [];
            $params = [':id' => $id];

            // Only allow profile-specific fields (excluding username)
            $allowed_fields = ['email', 'first_name', 'last_name', 'password'];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (empty($fields)) {
                return false;
            }

            $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $exception) {
            error_log("Update profile error: " . $exception->getMessage());
            return false;
        }
    }

    public function deleteUser($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete user error: " . $exception->getMessage());
            return false;
        }
    }

    public function getUserCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'];
        } catch(PDOException $exception) {
            error_log("Get user count error: " . $exception->getMessage());
            return 0;
        }
    }

    public function searchUsers($search, $limit = 20, $offset = 0) {
        try {
            $query = "SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active, u.created_at,
                            u.current_tier_id, mt.display_name as tier_name,
                            (
                                SELECT us.status 
                                FROM user_subscriptions us 
                                WHERE us.user_id = u.id AND us.status = 'active' 
                                ORDER BY us.created_at DESC 
                                LIMIT 1
                            ) as subscription_status,
                            (
                                SELECT us.expires_at 
                                FROM user_subscriptions us 
                                WHERE us.user_id = u.id AND us.status = 'active' 
                                ORDER BY us.created_at DESC 
                                LIMIT 1
                            ) as expires_at
                     FROM " . $this->table_name . " u
                     LEFT JOIN membership_tiers mt ON mt.id = u.current_tier_id
                     WHERE u.username LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search
                     ORDER BY u.created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Search users error: " . $exception->getMessage());
            return [];
        }
    }

    public function createUser($username, $email, $password, $first_name, $last_name, $role = 'user', $is_active = true) {
        try {
            // Check if username or email already exists
            if ($this->usernameExists($username) || $this->emailExists($email)) {
                return false;
            }

            $query = "INSERT INTO " . $this->table_name . " 
                     (username, email, password, first_name, last_name, role, is_active, current_tier_id) 
                     VALUES (:username, :email, :password, :first_name, :last_name, :role, :is_active, 1)";
            
            $stmt = $this->db->prepare($query);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);

            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Create user error: " . $exception->getMessage());
            return false;
        }
    }

    public function usernameExists($username, $exclude_id = null) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            if ($exclude_id) {
                $stmt->bindParam(':exclude_id', $exclude_id);
            }
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $exception) {
            error_log("Username exists check error: " . $exception->getMessage());
            return true; // Assume exists on error for safety
        }
    }

    public function emailExists($email, $exclude_id = null) {
        try {
            $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            if ($exclude_id) {
                $stmt->bindParam(':exclude_id', $exclude_id);
            }
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $exception) {
            error_log("Email exists check error: " . $exception->getMessage());
            return true; // Assume exists on error for safety
        }
    }

    public function resetPassword($user_id, $new_password) {
        try {
            $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Reset password error: " . $exception->getMessage());
            return false;
        }
    }

    public function generateRandomPassword($length = 12) {
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $charset[random_int(0, strlen($charset) - 1)];
        }
        return $password;
    }
    
    public function findByEmail($email) {
        try {
            $query = "SELECT id, username, email, first_name, last_name, role, is_active FROM " . $this->table_name . " WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $exception) {
            error_log("Find by email error: " . $exception->getMessage());
            return null;
        }
    }

    // Two-Factor Authentication Methods
    
    /**
     * Enable 2FA for a user
     */
    public function enable2FA($user_id, $secret, $recovery_codes) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET two_factor_enabled = TRUE, 
                         two_factor_secret = :secret, 
                         two_factor_recovery_codes = :recovery_codes 
                     WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':secret', $secret);
            $recovery_codes_json = json_encode($recovery_codes);
            $stmt->bindParam(':recovery_codes', $recovery_codes_json);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Enable 2FA error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for a user
     */
    public function disable2FA($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET two_factor_enabled = FALSE, 
                         two_factor_secret = NULL, 
                         two_factor_recovery_codes = NULL 
                     WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Disable 2FA error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get 2FA data for a user
     */
    public function get2FAData($user_id) {
        try {
            $query = "SELECT two_factor_enabled, two_factor_secret, two_factor_recovery_codes 
                     FROM " . $this->table_name . " WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            if ($result && $result['two_factor_recovery_codes']) {
                $result['two_factor_recovery_codes'] = json_decode($result['two_factor_recovery_codes'], true);
            }
            
            return $result;
        } catch(PDOException $exception) {
            error_log("Get 2FA data error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Update recovery codes for a user
     */
    public function updateRecoveryCodes($user_id, $recovery_codes) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET two_factor_recovery_codes = :recovery_codes 
                     WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $recovery_codes_json = json_encode($recovery_codes);
            $stmt->bindParam(':recovery_codes', $recovery_codes_json);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update recovery codes error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public function has2FAEnabled($user_id) {
        try {
            $query = "SELECT two_factor_enabled FROM " . $this->table_name . " WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? (bool)$result['two_factor_enabled'] : false;
        } catch(PDOException $exception) {
            error_log("Check 2FA enabled error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get application statistics for admin backup
     */
    public function getApplicationStats() {
        try {
            $stats = [];
            
            // Total users
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name;
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_users'] = $result['count'];
            
            // Active users
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['active_users'] = $result['count'];
            
            // Admin users
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE role = 'admin'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['admin_users'] = $result['count'];
            
            // Users with 2FA enabled
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE two_factor_enabled = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['users_with_2fa'] = $result['count'];
            
            // Total prompts
            $query = "SELECT COUNT(*) as count FROM prompts";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_prompts'] = $result['count'];
            
            // Total categories
            $query = "SELECT COUNT(*) as count FROM categories";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_categories'] = $result['count'];
            
            // Total shared prompts
            $query = "SELECT COUNT(*) as count FROM shared_prompts";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_shared_prompts'] = $result['count'];
            
            return $stats;
        } catch(PDOException $exception) {
            error_log("Get application stats error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Batch create users from backup data (admin only)
     */
    public function batchCreate($users) {
        try {
            $this->db->beginTransaction();
            
            $created_count = 0;
            $errors = [];
            
            foreach ($users as $user_data) {
                // Check if user already exists
                if ($this->usernameExists($user_data['username']) || $this->emailExists($user_data['email'])) {
                    $errors[] = "User already exists: " . $user_data['username'];
                    continue;
                }
                
                // Generate random password for imported users
                $temp_password = $this->generateRandomPassword();
                
                $result = $this->createUser(
                    $user_data['username'],
                    $user_data['email'],
                    $temp_password,
                    $user_data['first_name'],
                    $user_data['last_name'],
                    $user_data['role'] ?? 'user',
                    $user_data['is_active'] ?? true
                );
                
                if ($result) {
                    $created_count++;
                } else {
                    $errors[] = "Failed to create user: " . $user_data['username'];
                }
            }
            
            $this->db->commit();
            return ['created' => $created_count, 'errors' => $errors];
            
        } catch(Exception $exception) {
            $this->db->rollback();
            error_log("Batch create users error: " . $exception->getMessage());
            return ['created' => 0, 'errors' => ['Database error: ' . $exception->getMessage()]];
        }
    }

    /**
     * Generate a temporary login token for a user.
     */
    public function generateLoginToken($user_id) {
        try {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $query = "UPDATE " . $this->table_name . " 
                     SET login_token = :token, login_token_expires_at = :expires_at 
                     WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires_at', $expires_at);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                return $token;
            }
            return null;
        } catch (Exception $e) {
            error_log("Generate login token error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate a login token and return the user if valid.
     */
    public function validateLoginToken($token) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE login_token = :token AND login_token_expires_at > NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            $user = $stmt->fetch();

            if ($user) {
                // Invalidate the token after use
                $this->invalidateLoginToken($user['id']);
                return $user;
            }
            return null;
        } catch (PDOException $e) {
            error_log("Validate login token error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Invalidate the login token for a user.
     */
    private function invalidateLoginToken($user_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET login_token = NULL, login_token_expires_at = NULL 
                     WHERE id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Invalidate login token error: " . $e->getMessage());
        }
    }
    
    // =====================================================
    // MEMBERSHIP TIER METHODS
    // =====================================================
    
    /**
     * Get user with membership tier information
     */
    public function getById($id) {
        try {
            // First check if user_subscriptions table exists
            $tables = $this->db->query("SHOW TABLES LIKE 'user_subscriptions'")->fetchAll();
            $has_subscriptions_table = !empty($tables);
            
            if ($has_subscriptions_table) {
                // Use full query with subscription info
                $query = "SELECT u.*, mt.name as tier_name, mt.display_name as tier_display_name,
                                mt.max_prompts_per_month, mt.max_ai_generations_per_month, mt.max_categories,
                                us.status as subscription_status, us.expires_at, us.billing_cycle
                         FROM " . $this->table_name . " u
                         LEFT JOIN membership_tiers mt ON mt.id = u.current_tier_id
                         LEFT JOIN user_subscriptions us ON us.user_id = u.id AND us.status = 'active'
                         WHERE u.id = :id";
            } else {
                // Use simplified query without subscription info
                $query = "SELECT u.*, mt.name as tier_name, mt.display_name as tier_display_name,
                                mt.max_prompts_per_month, mt.max_ai_generations_per_month, mt.max_categories
                         FROM " . $this->table_name . " u
                         LEFT JOIN membership_tiers mt ON mt.id = u.current_tier_id
                         WHERE u.id = :id";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get user with tier error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Update user's membership tier
     */
    public function updateMembershipTier($user_id, $tier_id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET current_tier_id = :tier_id WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tier_id', $tier_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update membership tier error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's current membership tier
     */
    public function getCurrentTier($user_id) {
        try {
            $query = "SELECT mt.* FROM membership_tiers mt
                     JOIN users u ON u.current_tier_id = mt.id
                     WHERE u.id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get current tier error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Get user's active subscription
     */
    public function getActiveSubscription($user_id) {
        try {
            $query = "SELECT us.*, mt.name as tier_name, mt.display_name as tier_display_name
                     FROM user_subscriptions us
                     JOIN membership_tiers mt ON mt.id = us.tier_id
                     WHERE us.user_id = :user_id AND us.status = 'active'
                     ORDER BY us.created_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch();
        } catch(PDOException $exception) {
            error_log("Get active subscription error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Check if user has premium membership
     */
    public function isPremiumUser($user_id) {
        $tier = $this->getCurrentTier($user_id);
        return $tier && $tier['name'] === 'premium';
    }
    
    /**
     * Check if user's subscription is expiring soon
     */
    public function isSubscriptionExpiringSoon($user_id, $days = 7) {
        $subscription = $this->getActiveSubscription($user_id);
        if (!$subscription || !$subscription['expires_at']) {
            return false;
        }
        
        $expires_at = strtotime($subscription['expires_at']);
        $warning_time = time() + ($days * 24 * 60 * 60);
        
        return $expires_at <= $warning_time;
    }
    
    /**
     * Get membership statistics for admin dashboard
     */
    public function getMembershipStats() {
        try {
            $stats = [];
            
            // Users by tier
            $query = "SELECT mt.name, mt.display_name, COUNT(u.id) as user_count
                     FROM membership_tiers mt
                     LEFT JOIN users u ON u.current_tier_id = mt.id
                     WHERE mt.is_active = 1
                     GROUP BY mt.id, mt.name, mt.display_name
                     ORDER BY mt.sort_order";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['users_by_tier'] = $stmt->fetchAll();
            
            // Active subscriptions
            $query = "SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['active_subscriptions'] = $result['count'];
            
            // Expiring subscriptions (next 30 days)
            $query = "SELECT COUNT(*) as count FROM user_subscriptions 
                     WHERE status = 'active' AND expires_at IS NOT NULL 
                     AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['expiring_subscriptions'] = $result['count'];
            
            // Total revenue this month
            $query = "SELECT SUM(amount) as revenue FROM payment_transactions 
                     WHERE status = 'success' 
                     AND DATE_FORMAT(processed_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['monthly_revenue'] = $result['revenue'] ?: 0;
            
            return $stats;
        } catch(PDOException $exception) {
            error_log("Get membership stats error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Get users who need subscription renewal notifications
     */
    public function getUsersNeedingRenewalNotification($days_before = 7) {
        try {
            $query = "SELECT u.id, u.email, u.first_name, u.last_name,
                            us.expires_at, mt.display_name as tier_name
                     FROM users u
                     JOIN user_subscriptions us ON us.user_id = u.id
                     JOIN membership_tiers mt ON mt.id = us.tier_id
                     WHERE us.status = 'active' 
                     AND us.expires_at IS NOT NULL
                     AND us.expires_at <= DATE_ADD(NOW(), INTERVAL :days DAY)
                     AND us.expires_at > NOW()
                     AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days_before);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get users needing renewal notification error: " . $exception->getMessage());
            return [];
        }
    }
}
?>
