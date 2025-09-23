<?php
/**
 * NotificationService - Handles user notifications for usage limits and other events
 * Supports both in-app notifications and email notifications
 */
class NotificationService {
    private $db;
    private $emailService;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Initialize email service
        require_once __DIR__ . '/EmailService.php';
        $this->emailService = new EmailService();
    }
    
    /**
     * Check and send usage notifications for a specific user
     */
    public function checkAndSendUsageNotifications($user_id) {
        require_once __DIR__ . '/../app/models/UsageTracker.php';
        require_once __DIR__ . '/../app/models/User.php';
        
        $usageTracker = new UsageTracker();
        $userModel = new User();
        
        $user = $userModel->getById($user_id);
        if (!$user) return false;
        
        $usageSummary = $usageTracker->getUserUsageSummary($user_id);
        $notifications_sent = 0;
        
        foreach ($usageSummary['usage'] as $usage_type => $usage_data) {
            // Skip unlimited tiers
            if ($usage_data['is_unlimited']) continue;
            
            $percentage = $usage_data['percentage'];
            $notification_type = null;
            
            // Determine notification threshold
            if ($percentage >= 100) {
                $notification_type = 'limit_reached';
            } elseif ($percentage >= 90) {
                $notification_type = 'warning_90';
            } elseif ($percentage >= 75) {
                $notification_type = 'warning_75';
            }
            
            if ($notification_type && !$this->hasNotificationBeenSent($user_id, $usage_type, $notification_type)) {
                $this->sendUsageNotification($user, $usage_type, $usage_data, $notification_type);
                $this->logNotification($user_id, $usage_type, $notification_type);
                $notifications_sent++;
            }
        }
        
        return $notifications_sent;
    }
    
    /**
     * Send usage notification to user
     */
    private function sendUsageNotification($user, $usage_type, $usage_data, $notification_type) {
        $type_labels = [
            'prompt_creation' => 'prompt creation',
            'ai_generation' => 'AI generation',
            'category_creation' => 'category creation'
        ];
        
        $type_label = $type_labels[$usage_type] ?? $usage_type;
        $used = $usage_data['used'];
        $limit = $usage_data['limit'];
        $percentage = $usage_data['percentage'];
        
        // Create notification content based on type
        switch ($notification_type) {
            case 'warning_75':
                $subject = "Usage Alert: 75% of {$type_label} limit reached";
                $message = "You've used {$used} out of {$limit} {$type_label} allowances this month ({$percentage}%). Consider upgrading to Premium for unlimited access.";
                break;
                
            case 'warning_90':
                $subject = "Usage Alert: 90% of {$type_label} limit reached";
                $message = "You've used {$used} out of {$limit} {$type_label} allowances this month ({$percentage}%). You're approaching your limit. Upgrade to Premium for unlimited access.";
                break;
                
            case 'limit_reached':
                $subject = "Usage Limit Reached: {$type_label}";
                $message = "You've reached your monthly limit of {$limit} {$type_label} allowances. Upgrade to Premium to continue using this feature.";
                break;
                
            default:
                return false;
        }
        
        // Create in-app notification
        $this->createInAppNotification($user['id'], $subject, $message, $notification_type);
        
        // Send email notification if email service is configured
        if ($this->emailService->isConfigured()) {
            $this->emailService->sendUsageLimitWarningEmail($user, $type_label, $percentage, $used, $limit);
        }
        
        return true;
    }
    
    /**
     * Create in-app notification
     */
    private function createInAppNotification($user_id, $subject, $message, $type) {
        try {
            $query = "INSERT INTO user_notifications 
                     (user_id, subject, message, type, created_at) 
                     VALUES (:user_id, :subject, :message, :type, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':type', $type);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Create notification error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Log that a notification has been sent
     */
    private function logNotification($user_id, $usage_type, $notification_type) {
        try {
            $current_month = date('Y-m-01');
            
            $query = "INSERT INTO usage_notifications 
                     (user_id, notification_type, usage_type, usage_month, sent_at) 
                     VALUES (:user_id, :notification_type, :usage_type, :usage_month, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':notification_type', $notification_type);
            $stmt->bindParam(':usage_type', $usage_type);
            $stmt->bindParam(':usage_month', $current_month);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Log notification error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Check if notification has already been sent
     */
    private function hasNotificationBeenSent($user_id, $usage_type, $notification_type) {
        try {
            $current_month = date('Y-m-01');
            
            $query = "SELECT id FROM usage_notifications 
                     WHERE user_id = :user_id 
                     AND usage_type = :usage_type 
                     AND notification_type = :notification_type
                     AND usage_month = :usage_month";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':usage_type', $usage_type);
            $stmt->bindParam(':notification_type', $notification_type);
            $stmt->bindParam(':usage_month', $current_month);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $exception) {
            error_log("Check notification sent error: " . $exception->getMessage());
            return true; // Assume sent to avoid spam
        }
    }
    
    /**
     * Get user's notifications
     */
    public function getUserNotifications($user_id, $limit = 10, $offset = 0, $unread_only = false) {
        try {
            $query = "SELECT * FROM user_notifications 
                     WHERE user_id = :user_id";
            
            if ($unread_only) {
                $query .= " AND read_at IS NULL";
            }
            
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get user notifications error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $query = "UPDATE user_notifications 
                     SET read_at = NOW() 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $notification_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Mark notification as read error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($user_id) {
        try {
            $query = "UPDATE user_notifications 
                     SET read_at = NOW() 
                     WHERE user_id = :user_id AND read_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch(PDOException $exception) {
            error_log("Mark all notifications as read error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM user_notifications 
                     WHERE user_id = :user_id AND read_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int)$result['count'];
        } catch(PDOException $exception) {
            error_log("Get unread count error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Send subscription expiry notifications
     */
    public function sendSubscriptionExpiryNotifications() {
        require_once __DIR__ . '/../app/models/User.php';
        
        $userModel = new User();
        $users = $userModel->getUsersNeedingRenewalNotification(7); // 7 days before expiry
        
        foreach ($users as $user) {
            $subject = "Subscription Expiring Soon";
            $expires_date = date('F j, Y', strtotime($user['expires_at']));
            $message = "Your {$user['tier_name']} subscription expires on {$expires_date}. Renew now to continue enjoying premium features.";
            
            $this->createInAppNotification($user['id'], $subject, $message, 'subscription_expiry');
        }
        
        return count($users);
    }
    
    /**
     * Process usage notifications for all users (for cron job)
     */
    public function processAllUsageNotifications() {
        require_once __DIR__ . '/../app/models/User.php';
        
        $userModel = new User();
        $users = $userModel->getAllUsers(1000, 0); // Get all active users
        
        $total_notifications = 0;
        foreach ($users as $user) {
            if ($user['is_active']) {
                $notifications_sent = $this->checkAndSendUsageNotifications($user['id']);
                $total_notifications += $notifications_sent;
            }
        }
        
        return $total_notifications;
    }
    
    /**
     * Clean up old notifications (older than 30 days)
     */
    public function cleanupOldNotifications() {
        try {
            $query = "DELETE FROM user_notifications 
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch(PDOException $exception) {
            error_log("Cleanup old notifications error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Send subscription confirmation notification
     */
    public function sendSubscriptionConfirmation($user, $tier_name, $amount) {
        $subject = "Subscription Confirmed - Welcome to {$tier_name}!";
        $message = "Your {$tier_name} subscription has been activated successfully! Thank you for upgrading.";
        
        // Create in-app notification
        $this->createInAppNotification($user['id'], $subject, $message, 'subscription_confirmation');
        
        // Send email notification
        if ($this->emailService->isConfigured()) {
            $this->emailService->sendSubscriptionConfirmationEmail($user, $tier_name, $amount);
        }
        
        return true;
    }
    
    /**
     * Send payment due reminder notification
     */
    public function sendPaymentDueReminder($user, $tier_name, $due_date, $amount) {
        $subject = "Payment Reminder - {$tier_name} Subscription";
        $formatted_date = date('F j, Y', strtotime($due_date));
        $message = "Your {$tier_name} subscription payment is due on {$formatted_date}. Please renew to continue accessing premium features.";
        
        // Create in-app notification
        $this->createInAppNotification($user['id'], $subject, $message, 'payment_reminder');
        
        // Send email notification
        if ($this->emailService->isConfigured()) {
            $this->emailService->sendPaymentDueReminderEmail($user, $tier_name, $due_date, $amount);
        }
        
        return true;
    }
    
    /**
     * Send subscription expiry warning notification
     */
    public function sendSubscriptionExpiryWarning($user, $tier_name, $expiry_date) {
        $subject = "Subscription Expiring Soon - {$tier_name}";
        $formatted_date = date('F j, Y', strtotime($expiry_date));
        $message = "Your {$tier_name} subscription expires on {$formatted_date}. Renew now to avoid losing access to premium features.";
        
        // Create in-app notification
        $this->createInAppNotification($user['id'], $subject, $message, 'subscription_expiry');
        
        // Send email notification
        if ($this->emailService->isConfigured()) {
            $this->emailService->sendSubscriptionExpiryWarningEmail($user, $tier_name, $expiry_date);
        }
        
        return true;
    }
    
    /**
     * Send prompt shared notification
     */
    public function sendPromptSharedNotification($recipient, $sharer, $prompt_title, $prompt_id) {
        try {
            $subject = "Prompt Shared with You - {$prompt_title}";
            $message = "{$sharer['first_name']} {$sharer['last_name']} has shared a prompt '{$prompt_title}' with you.";
            
            // Create in-app notification with action URL
            $action_url = "/index.php?page=shared_prompts&prompt_id={$prompt_id}";
            $result = $this->createInAppNotificationWithAction($recipient['id'], $subject, $message, 'prompt_shared', $action_url);
            
            if ($result) {
                error_log("NotificationService: Created in-app notification for prompt share to user {$recipient['id']}");
            } else {
                error_log("NotificationService: Failed to create in-app notification for prompt share to user {$recipient['id']}");
            }
            
            // Send email notification
            if ($this->emailService->isConfigured()) {
                $email_result = $this->emailService->sendPromptSharedEmail($recipient, $sharer, $prompt_title);
                if ($email_result) {
                    error_log("NotificationService: Sent email notification for prompt share to {$recipient['email']}");
                } else {
                    error_log("NotificationService: Failed to send email notification for prompt share to {$recipient['email']}");
                }
            } else {
                error_log("NotificationService: SMTP not configured, skipping email for prompt share to {$recipient['email']}");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("NotificationService: Exception in sendPromptSharedNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome notification to new users
     */
    public function sendWelcomeNotification($user) {
        try {
            $subject = "Welcome to Promptash!";
            $message = "Welcome to Promptash! You can now create and organize your prompts, use AI-powered generation, and upgrade to Premium for unlimited features.";
            
            // Create in-app notification
            $result = $this->createInAppNotification($user['id'], $subject, $message, 'welcome');
            
            if ($result) {
                error_log("NotificationService: Created welcome notification for user {$user['id']}");
            } else {
                error_log("NotificationService: Failed to create welcome notification for user {$user['id']}");
            }
            
            // Send welcome email
            if ($this->emailService->isConfigured()) {
                $email_result = $this->emailService->sendWelcomeEmail($user);
                if ($email_result) {
                    error_log("NotificationService: Sent welcome email to {$user['email']}");
                } else {
                    error_log("NotificationService: Failed to send welcome email to {$user['email']}");
                }
            } else {
                error_log("NotificationService: SMTP not configured, skipping welcome email for {$user['email']}");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("NotificationService: Exception in sendWelcomeNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification($user, $reset_token) {
        // Only send email for password reset - no in-app notification for security
        if ($this->emailService->isConfigured()) {
            return $this->emailService->sendPasswordResetEmail($user, $reset_token);
        }
        
        return false;
    }
    
    /**
     * Create in-app notification with action URL
     */
    private function createInAppNotificationWithAction($user_id, $subject, $message, $type, $action_url = null) {
        try {
            $query = "INSERT INTO user_notifications 
                     (user_id, subject, message, type, action_url, created_at) 
                     VALUES (:user_id, :subject, :message, :type, :action_url, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':action_url', $action_url);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Create notification with action error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Process subscription expiry notifications (for cron job)
     */
    public function processSubscriptionExpiryNotifications() {
        try {
            // Find subscriptions expiring in 7 days
            $query = "SELECT u.id, u.first_name, u.last_name, u.email, 
                            us.expires_at, mt.display_name as tier_name
                     FROM users u
                     JOIN user_subscriptions us ON us.user_id = u.id
                     JOIN membership_tiers mt ON mt.id = us.tier_id
                     WHERE us.status = 'active'
                     AND us.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                     AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $expiring_subscriptions = $stmt->fetchAll();
            
            $notifications_sent = 0;
            foreach ($expiring_subscriptions as $user) {
                // Check if we've already sent expiry warning this week
                if (!$this->hasExpiryWarningBeenSent($user['id'], $user['expires_at'])) {
                    $this->sendSubscriptionExpiryWarning($user, $user['tier_name'], $user['expires_at']);
                    $this->logExpiryWarning($user['id'], $user['expires_at']);
                    $notifications_sent++;
                }
            }
            
            return $notifications_sent;
        } catch(PDOException $exception) {
            error_log("Process subscription expiry notifications error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if expiry warning has been sent for this subscription period
     */
    private function hasExpiryWarningBeenSent($user_id, $expires_at) {
        try {
            $query = "SELECT id FROM user_notifications 
                     WHERE user_id = :user_id 
                     AND type = 'subscription_expiry'
                     AND created_at > DATE_SUB(:expires_at, INTERVAL 14 DAY)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':expires_at', $expires_at);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $exception) {
            error_log("Check expiry warning error: " . $exception->getMessage());
            return true; // Assume sent to avoid spam
        }
    }
    
    /**
     * Log that expiry warning has been sent
     */
    private function logExpiryWarning($user_id, $expires_at) {
        // This is already logged by createInAppNotification, so no additional logging needed
        return true;
    }
    

    
    /**
     * Delete notification
     */
    public function deleteNotification($notification_id, $user_id) {
        try {
            $query = "DELETE FROM user_notifications 
                     WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $notification_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete notification error: " . $exception->getMessage());
            return false;
        }
    }
}
?>