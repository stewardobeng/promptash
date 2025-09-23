<?php
/**
 * UsageTracker Model - Handles user usage tracking and limit enforcement
 */
class UsageTracker {
    private $db;
    private $table_name = "usage_tracking";
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Track usage for a user (for monthly limits)
     */
    public function trackUsage($user_id, $usage_type, $count = 1) {
        try {
            $current_month = $this->getUserCurrentMonth($user_id); // Use personalized month
            
            $query = "INSERT INTO " . $this->table_name . " 
                     (user_id, usage_type, usage_month, usage_count, last_reset_at) 
                     VALUES (:user_id, :usage_type, :usage_month, :count, NOW())
                     ON DUPLICATE KEY UPDATE 
                     usage_count = usage_count + :count_update,
                     updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':usage_type', $usage_type);
            $stmt->bindParam(':usage_month', $current_month);
            $stmt->bindParam(':count', $count);
            $stmt->bindParam(':count_update', $count);
            
            $result = $stmt->execute();
            
            // Check for notifications after tracking usage
            if ($result) {
                $this->checkUsageNotifications($user_id, $usage_type);
            }
            
            return $result;
        } catch(PDOException $exception) {
            error_log("Track usage error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get current month usage for user (personalized by registration date)
     */
    public function getCurrentUsage($user_id, $usage_type = null) {
        try {
            $current_month = $this->getUserCurrentMonth($user_id); // Use personalized month
            
            $query = "SELECT usage_type, usage_count FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND usage_month = :usage_month";
            
            if ($usage_type) {
                $query .= " AND usage_type = :usage_type";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':usage_month', $current_month);
            
            if ($usage_type) {
                $stmt->bindParam(':usage_type', $usage_type);
            }
            
            $stmt->execute();
            
            if ($usage_type) {
                $result = $stmt->fetch();
                return $result ? (int)$result['usage_count'] : 0;
            } else {
                $usage = [];
                while ($row = $stmt->fetch()) {
                    $usage[$row['usage_type']] = (int)$row['usage_count'];
                }
                return $usage;
            }
        } catch(PDOException $exception) {
            error_log("Get current usage error: " . $exception->getMessage());
            return $usage_type ? 0 : [];
        }
    }
    
    /**
     * Get lifetime usage for a user for a specific type from the tracking table
     */
    public function getLifetimeUsage($user_id, $usage_type) {
        try {
            $query = "SELECT SUM(usage_count) as total_usage FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND usage_type = :usage_type";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':usage_type', $usage_type);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? (int)$result['total_usage'] : 0;
            
        } catch(PDOException $exception) {
            error_log("Get lifetime usage error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if user can perform action (hasn't exceeded limit)
     */
    public function canPerformAction($user_id, $usage_type, $count = 1) {
        try {
            // Get user's current tier
            require_once __DIR__ . '/MembershipTier.php';
            require_once __DIR__ . '/User.php';
            
            $userModel = new User();
            $tierModel = new MembershipTier();
            
            $user = $userModel->getById($user_id);
            if (!$user) {
                error_log("Can perform action error: User not found with ID {$user_id}");
                return false;
            }
            
            // Handle missing current_tier_id by setting default to free tier (ID: 1)
            $tier_id = $user['current_tier_id'];
            if (!$tier_id) {
                error_log("User {$user_id} has no current_tier_id, setting to free tier (1)");
                $userModel->updateMembershipTier($user_id, 1);
                $tier_id = 1;
            }
            
            $tier = $tierModel->getTierById($tier_id);
            if (!$tier) {
                error_log("Can perform action error: Tier not found with ID {$tier_id} for user {$user_id}");
                // Default to free tier limits as fallback
                $freeTier = $tierModel->getFreeTier();
                if (!$freeTier) {
                    error_log("Fatal error: Free tier not found in database");
                    return false; // No tiers exist, deny action
                }
                $tier = $freeTier;
            }
            
            // Get tier limit
            $limit = $tierModel->getTierLimit($tier['id'], $usage_type);
            
            // 0 means unlimited
            if ($limit == 0) return true;
            
            // Define which types have lifetime limits
            $lifetime_limits = [
                'note_creation', 
                'document_creation', 
                'video_creation'
            ];
            $is_lifetime = in_array($usage_type, $lifetime_limits);
            
            $current_usage = 0;
            if ($is_lifetime) {
                // Handle lifetime limits by getting total count from respective models
                switch ($usage_type) {
                    case 'note_creation':
                        require_once __DIR__ . '/Note.php';
                        $model = new Note();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                    case 'document_creation':
                        require_once __DIR__ . '/Document.php';
                        $model = new Document();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                    case 'video_creation':
                        require_once __DIR__ . '/Video.php';
                        $model = new Video();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                }
            } else {
                // Handle monthly limits from the usage_tracking table
                $current_usage = $this->getCurrentUsage($user_id, $usage_type);
            }
            
            // Check if adding this count would exceed limit
            $would_exceed = ($current_usage + $count) > $limit;
            
            if ($would_exceed) {
                error_log("Usage limit check: User {$user_id} would exceed {$usage_type} limit. Current: {$current_usage}, Limit: {$limit}, Attempted: {$count}");
            }
            
            return !$would_exceed;
            
        } catch(Exception $exception) {
            error_log("Can perform action error: " . $exception->getMessage());
            return false; // Fail safe - deny action if we can't check
        }
    }
    
    /**
     * Get usage percentage for user
     */
    public function getUsagePercentage($user_id, $usage_type) {
        try {
            require_once __DIR__ . '/MembershipTier.php';
            require_once __DIR__ . '/User.php';
            
            $userModel = new User();
            $tierModel = new MembershipTier();
            
            $user = $userModel->getById($user_id);
            if (!$user) {
                error_log("Get usage percentage error: User not found with ID {$user_id}");
                return 0;
            }
            
            // Handle missing current_tier_id by setting default to free tier (ID: 1)
            $tier_id = $user['current_tier_id'];
            if (!$tier_id) {
                error_log("User {$user_id} has no current_tier_id, setting to free tier (1)");
                $userModel->updateMembershipTier($user_id, 1);
                $tier_id = 1;
            }
            
            $tier = $tierModel->getTierById($tier_id);
            if (!$tier) {
                error_log("Get usage percentage error: Tier not found with ID {$tier_id} for user {$user_id}");
                // Default to free tier as fallback
                $freeTier = $tierModel->getFreeTier();
                if (!$freeTier) {
                    error_log("Fatal error: Free tier not found in database");
                    return 0;
                }
                $tier = $freeTier;
            }
            
            $limit = $tierModel->getTierLimit($tier['id'], $usage_type);
            
            // Unlimited usage
            if ($limit == 0) return 0;
            
            // Define which types have lifetime limits
            $lifetime_limits = [
                'note_creation', 
                'document_creation', 
                'video_creation'
            ];
            $is_lifetime = in_array($usage_type, $lifetime_limits);

            $current_usage = 0;
            if ($is_lifetime) {
                // Handle lifetime limits by getting total count
                switch ($usage_type) {
                    case 'note_creation':
                        require_once __DIR__ . '/Note.php';
                        $model = new Note();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                    case 'document_creation':
                        require_once __DIR__ . '/Document.php';
                        $model = new Document();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                    case 'video_creation':
                        require_once __DIR__ . '/Video.php';
                        $model = new Video();
                        $current_usage = $model->getCountByUserId($user_id);
                        break;
                }
            } else {
                // Handle monthly limits
                $current_usage = $this->getCurrentUsage($user_id, $usage_type);
            }
            
            return min(100, round(($current_usage / $limit) * 100, 1));
            
        } catch(Exception $exception) {
            error_log("Get usage percentage error: " . $exception->getMessage());
            return 0;
        }
    }
    
    /**
     * Get detailed usage summary for user
     */
    public function getUserUsageSummary($user_id) {
        try {
            require_once __DIR__ . '/MembershipTier.php';
            require_once __DIR__ . '/User.php';
            require_once __DIR__ . '/Bookmark.php'; 
            require_once __DIR__ . '/Category.php'; 
            require_once __DIR__ . '/Note.php';
            require_once __DIR__ . '/Document.php';
            require_once __DIR__ . '/Video.php';
            
            $userModel = new User();
            $tierModel = new MembershipTier();
            $bookmarkModel = new Bookmark(); 
            $categoryModel = new Category(); 
            $noteModel = new Note();
            $documentModel = new Document();
            $videoModel = new Video();
            
            $user = $userModel->getById($user_id);
            if (!$user) {
                error_log("Get usage summary error: User not found with ID {$user_id}");
                return [];
            }
            
            // Handle missing current_tier_id by setting default to free tier (ID: 1)
            $tier_id = $user['current_tier_id'];
            if (!$tier_id) {
                error_log("User {$user_id} has no current_tier_id, setting to free tier (1)");
                $userModel->updateMembershipTier($user_id, 1);
                $tier_id = 1;
            }
            
            $tier = $tierModel->getTierById($tier_id);
            if (!$tier) {
                error_log("Get usage summary error: Tier not found with ID {$tier_id} for user {$user_id}");
                // Default to free tier as fallback
                $freeTier = $tierModel->getFreeTier();
                if (!$freeTier) {
                    error_log("Fatal error: Free tier not found in database");
                    return [];
                }
                $tier = $freeTier;
                // Update user to use free tier
                $userModel->updateMembershipTier($user_id, $tier['id']);
            }
            
            $monthly_usage = $this->getCurrentUsage($user_id);
            
            $usage_types = ['prompt_creation', 'ai_generation', 'category_creation', 'bookmark_creation', 'note_creation', 'document_creation', 'video_creation'];
            $summary = [];
            
            // Define which types have lifetime limits
            $lifetime_limits = ['note_creation', 'document_creation', 'video_creation', 'bookmark_creation', 'category_creation'];

            foreach ($usage_types as $type) {
                $limit = $tierModel->getTierLimit($tier['id'], $type);
                $is_lifetime = in_array($type, $lifetime_limits);
                
                $used = 0;
                if ($is_lifetime) {
                    switch ($type) {
                        case 'bookmark_creation':
                            $used = $bookmarkModel->getCountByUserId($user_id);
                            break;
                        case 'category_creation':
                            $used = $categoryModel->getCountByUserId($user_id);
                            break;
                        case 'note_creation':
                            $used = $noteModel->getCountByUserId($user_id);
                            break;
                        case 'document_creation':
                            $used = $documentModel->getCountByUserId($user_id);
                            break;
                        case 'video_creation':
                            $used = $videoModel->getCountByUserId($user_id);
                            break;
                    }
                } else {
                    // Monthly usage
                    $used = isset($monthly_usage[$type]) ? $monthly_usage[$type] : 0;
                }
                
                $summary[$type] = [
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => $limit > 0 ? max(0, $limit - $used) : -1, // -1 for unlimited
                    'percentage' => $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0,
                    'is_unlimited' => $limit == 0,
                    'is_at_limit' => $limit > 0 && $used >= $limit,
                    'is_near_limit' => $limit > 0 && ($used / $limit) >= 0.9,
                    'is_lifetime' => $is_lifetime
                ];
            }
            
            return [
                'user_id' => $user_id,
                'tier' => $tier,
                'current_month' => $this->getUserCurrentMonth($user_id),
                'usage' => $summary,
                'next_reset' => $this->getUserNextResetDate($user_id)
            ];
            
        } catch(Exception $exception) {
            error_log("Get user usage summary error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Reset usage for all users (run monthly via cron)
     */
    public function resetMonthlyUsage() {
        try {
            $last_month = date('Y-m-01', strtotime('-1 month'));
            
            // Archive last month's data (optional - for analytics)
            $query = "UPDATE " . $this->table_name . " 
                     SET last_reset_at = NOW() 
                     WHERE usage_month = :last_month";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':last_month', $last_month);
            $stmt->execute();
            
            return true;
        } catch(PDOException $exception) {
            error_log("Reset monthly usage error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get usage history for user (analytics)
     */
    public function getUserUsageHistory($user_id, $months = 6) {
        try {
            $query = "SELECT usage_type, usage_month, usage_count 
                     FROM " . $this->table_name . " 
                     WHERE user_id = :user_id 
                     AND usage_month >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                     ORDER BY usage_month DESC, usage_type";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':months', $months);
            $stmt->execute();
            
            $history = [];
            while ($row = $stmt->fetch()) {
                $month = $row['usage_month'];
                if (!isset($history[$month])) {
                    $history[$month] = [];
                }
                $history[$month][$row['usage_type']] = (int)$row['usage_count'];
            }
            
            return $history;
        } catch(PDOException $exception) {
            error_log("Get user usage history error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Get system-wide usage statistics (admin)
     */
    public function getSystemUsageStats($month = null) {
        try {
            if (!$month) {
                $month = date('Y-m-01');
            }
            
            $query = "SELECT 
                        usage_type,
                        COUNT(DISTINCT user_id) as active_users,
                        SUM(usage_count) as total_usage,
                        AVG(usage_count) as avg_usage_per_user,
                        MAX(usage_count) as max_usage
                      FROM " . $this->table_name . " 
                      WHERE usage_month = :month
                      GROUP BY usage_type";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':month', $month);
            $stmt->execute();
            
            $stats = [];
            while ($row = $stmt->fetch()) {
                $stats[$row['usage_type']] = [
                    'active_users' => (int)$row['active_users'],
                    'total_usage' => (int)$row['total_usage'],
                    'avg_usage_per_user' => round((float)$row['avg_usage_per_user'], 2),
                    'max_usage' => (int)$row['max_usage']
                ];
            }
            
            return [
                'month' => $month,
                'stats' => $stats
            ];
        } catch(PDOException $exception) {
            error_log("Get system usage stats error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Get users approaching limits (for notifications)
     */
    public function getUsersApproachingLimits($threshold_percentage = 90) {
        try {
            $current_month = date('Y-m-01');
            
            $query = "SELECT DISTINCT ut.user_id, u.email, u.first_name, u.last_name,
                            ut.usage_type, ut.usage_count,
                            mt.display_name as tier_name,
                            CASE ut.usage_type
                                WHEN 'prompt_creation' THEN mt.max_prompts_per_month
                                WHEN 'ai_generation' THEN mt.max_ai_generations_per_month  
                                WHEN 'category_creation' THEN mt.max_categories
                                WHEN 'bookmark_creation' THEN mt.max_bookmarks
                                WHEN 'note_creation' THEN mt.max_notes
                                WHEN 'document_creation' THEN mt.max_documents
                                WHEN 'video_creation' THEN mt.max_videos
                            END as usage_limit
                      FROM " . $this->table_name . " ut
                      JOIN users u ON u.id = ut.user_id
                      JOIN membership_tiers mt ON mt.id = u.current_tier_id
                      WHERE ut.usage_month = :current_month
                      AND CASE ut.usage_type
                          WHEN 'prompt_creation' THEN 
                              mt.max_prompts_per_month > 0 AND 
                              (ut.usage_count / mt.max_prompts_per_month * 100) >= :threshold
                          WHEN 'ai_generation' THEN 
                              mt.max_ai_generations_per_month > 0 AND 
                              (ut.usage_count / mt.max_ai_generations_per_month * 100) >= :threshold
                          WHEN 'category_creation' THEN 
                              mt.max_categories > 0 AND 
                              (ut.usage_count / mt.max_categories * 100) >= :threshold
                          WHEN 'bookmark_creation' THEN
                              mt.max_bookmarks > 0 AND
                              (ut.usage_count / mt.max_bookmarks * 100) >= :threshold
                          WHEN 'note_creation' THEN
                              mt.max_notes > 0 AND
                              (ut.usage_count / mt.max_notes * 100) >= :threshold
                          WHEN 'document_creation' THEN
                              mt.max_documents > 0 AND
                              (ut.usage_count / mt.max_documents * 100) >= :threshold
                          WHEN 'video_creation' THEN
                              mt.max_videos > 0 AND
                              (ut.usage_count / mt.max_videos * 100) >= :threshold
                      END
                      ORDER BY ut.user_id, ut.usage_type";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':current_month', $current_month);
            $stmt->bindParam(':threshold', $threshold_percentage);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get users approaching limits error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user should be notified about usage
     */
    public function shouldNotifyUser($user_id, $usage_type) {
        $percentage = $this->getUsagePercentage($user_id, $usage_type);
        
        // Notify at 75%, 90%, and 100%
        $notification_thresholds = [75, 90, 100];
        
        foreach ($notification_thresholds as $threshold) {
            if ($percentage >= $threshold) {
                // Check if we already sent this notification this month
                if (!$this->hasNotificationBeenSent($user_id, $usage_type, $threshold)) {
                    return $threshold;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if notification has been sent
     */
    private function hasNotificationBeenSent($user_id, $usage_type, $threshold) {
        try {
            $current_month = $this->getUserCurrentMonth($user_id); // Use personalized month
            $notification_type = 'warning_' . $threshold;
            
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
     * Check and trigger notifications for a specific user and usage type
     */
    private function checkUsageNotifications($user_id, $usage_type) {
        try {
            $percentage = $this->getUsagePercentage($user_id, $usage_type);
            
            // Skip if unlimited (percentage will be 0)
            if ($percentage == 0) return;
            
            // Check thresholds and send notifications
            $notification_triggered = false;
            
            if ($percentage >= 100 && !$this->hasNotificationBeenSent($user_id, $usage_type, 100)) {
                $this->triggerUsageNotification($user_id, $usage_type, 'limit_reached');
                $notification_triggered = true;
            } elseif ($percentage >= 90 && !$this->hasNotificationBeenSent($user_id, $usage_type, 90)) {
                $this->triggerUsageNotification($user_id, $usage_type, 'warning_90');
                $notification_triggered = true;
            } elseif ($percentage >= 75 && !$this->hasNotificationBeenSent($user_id, $usage_type, 75)) {
                $this->triggerUsageNotification($user_id, $usage_type, 'warning_75');
                $notification_triggered = true;
            }
            
            return $notification_triggered;
        } catch(Exception $exception) {
            error_log("Check usage notifications error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Trigger a usage notification
     */
    private function triggerUsageNotification($user_id, $usage_type, $notification_type) {
        try {
            // Use NotificationService if available
            if (class_exists('NotificationService')) {
                require_once __DIR__ . '/../../helpers/NotificationService.php';
                $notificationService = new NotificationService();
                $notificationService->checkAndSendUsageNotifications($user_id);
            }
        } catch(Exception $exception) {
            error_log("Trigger usage notification error: " . $exception->getMessage());
        }
    }
    
    /**
     * Get user's personalized current month based on registration date
     */
    public function getUserCurrentMonth($user_id) {
        try {
            require_once __DIR__ . '/User.php';
            $userModel = new User();
            $user = $userModel->getById($user_id);
            
            if (!$user || !$user['created_at']) {
                // Fallback to standard month if user not found
                return date('Y-m-01');
            }
            
            $registration_date = new DateTime($user['created_at']);
            $registration_day = (int)$registration_date->format('d');
            
            $current_date = new DateTime();
            $current_day = (int)$current_date->format('d');
            $current_year = (int)$current_date->format('Y');
            $current_month = (int)$current_date->format('m');
            
            // If we're past the user's registration anniversary day this month, 
            // we're in their current billing cycle
            if ($current_day >= $registration_day) {
                // Current cycle started on registration day of current month
                $cycle_start = new DateTime(sprintf('%d-%02d-%02d', $current_year, $current_month, $registration_day));
            } else {
                // Current cycle started on registration day of previous month
                $cycle_start = new DateTime(sprintf('%d-%02d-%02d', $current_year, $current_month, $registration_day));
                $cycle_start->modify('-1 month');
                
                // Handle edge case where previous month doesn't have enough days
                while ($cycle_start->format('d') != $registration_day && $cycle_start->format('m') == $current_month - 1) {
                    $cycle_start->modify('-1 day');
                }
            }
            
            return $cycle_start->format('Y-m-d');
            
        } catch(Exception $exception) {
            error_log("Get user current month error: " . $exception->getMessage());
            // Fallback to standard month
            return date('Y-m-01');
        }
    }
    
    /**
     * Get user's next reset date based on registration anniversary
     */
    public function getUserNextResetDate($user_id) {
        try {
            require_once __DIR__ . '/User.php';
            $userModel = new User();
            $user = $userModel->getById($user_id);
            
            if (!$user || !$user['created_at']) {
                // Fallback to next month start
                return date('Y-m-01', strtotime('+1 month'));
            }
            
            $registration_date = new DateTime($user['created_at']);
            $registration_day = (int)$registration_date->format('d');
            
            $current_date = new DateTime();
            $current_day = (int)$current_date->format('d');
            $current_year = (int)$current_date->format('Y');
            $current_month = (int)$current_date->format('m');
            
            // If we're past the user's registration anniversary day this month,
            // next reset is registration day of next month
            if ($current_day >= $registration_day) {
                $next_reset = new DateTime(sprintf('%d-%02d-%02d', $current_year, $current_month, $registration_day));
                $next_reset->modify('+1 month');
            } else {
                // Next reset is registration day of current month
                $next_reset = new DateTime(sprintf('%d-%02d-%02d', $current_year, $current_month, $registration_day));
            }
            
            // Handle edge case for months with fewer days
            $max_day = (int)$next_reset->format('t'); // Last day of the month
            if ($registration_day > $max_day) {
                $next_reset->setDate($next_reset->format('Y'), $next_reset->format('m'), $max_day);
            }
            
            return $next_reset->format('Y-m-d');
            
        } catch(Exception $exception) {
            error_log("Get user next reset date error: " . $exception->getMessage());
            // Fallback to next month start
            return date('Y-m-01', strtotime('+1 month'));
        }
    }
}
?>