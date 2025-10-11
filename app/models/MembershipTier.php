<?php
/**
 * MembershipTier Model - Handles membership tier definitions and limits
 */
class MembershipTier {
    private $db;
    private $table_name = "membership_tiers";
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get all active membership tiers
     */
    public function getAllTiers($active_only = true) {
        try {
            $query = "SELECT * FROM " . $this->table_name;
            if ($active_only) {
                $query .= " WHERE is_active = 1";
            }
            $query .= " ORDER BY sort_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $tiers = [];
            while ($row = $stmt->fetch()) {
                $tiers[] = $this->formatTierData($row);
            }
            
            return $tiers;
        } catch(PDOException $exception) {
            error_log("Get membership tiers error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Get tier by ID
     */
    public function getTierById($tier_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :tier_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tier_id', $tier_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                return $this->formatTierData($row);
            }
            
            return null;
        } catch(PDOException $exception) {
            error_log("Get tier by ID error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Get tier by name
     */
    public function getTierByName($name) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE name = :name AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                return $this->formatTierData($row);
            }
            
            return null;
        } catch(PDOException $exception) {
            error_log("Get tier by name error: " . $exception->getMessage());
            return null;
        }
    }
    
    /**
     * Get personal tier (default entry-level plan)
     */
    public function getPersonalTier() {
        return $this->getTierByName('personal');
    }
    
    /**
     * @deprecated Retained for backward compatibility. Use getPersonalTier().
     */
    public function getFreeTier() {
        return $this->getPersonalTier();
    }
    
    /**
     * Get premium tier
     */
    public function getPremiumTier() {
        return $this->getTierByName('premium');
    }
    
    /**
     * Check if tier has unlimited feature
     */
    public function hasUnlimitedFeature($tier_id, $feature_type) {
        $tier = $this->getTierById($tier_id);
        if (!$tier) return false;
        
        switch ($feature_type) {
            case 'prompts':
            case 'prompt_creation':
                return $tier['max_prompts_per_month'] == 0;
            case 'ai_generations':
            case 'ai_generation':
                return $tier['max_ai_generations_per_month'] == 0;
            case 'categories':
            case 'category_creation':
                return $tier['max_categories'] == 0;
            case 'bookmarks':
            case 'bookmark_creation':
                return $tier['max_bookmarks'] == 0;
            case 'notes':
            case 'note_creation':
                return $tier['max_notes'] == 0;
            case 'documents':
            case 'document_creation':
                return $tier['max_documents'] == 0;
            case 'videos':
            case 'video_creation':
                return $tier['max_videos'] == 0;
            default:
                return false;
        }
    }
    
    /**
     * Get tier limit for specific feature
     */
    public function getTierLimit($tier_id, $limit_type) {
        $tier = $this->getTierById($tier_id);
        if (!$tier) return 0;
        
        switch ($limit_type) {
            case 'prompts':
            case 'prompt_creation':
                return $tier['max_prompts_per_month'];
            case 'ai_generations':
            case 'ai_generation':
                return $tier['max_ai_generations_per_month'];
            case 'categories':
            case 'category_creation':
                return $tier['max_categories'];
            case 'bookmarks':
            case 'bookmark_creation':
                return $tier['max_bookmarks'];
            case 'notes':
            case 'note_creation':
                return $tier['max_notes'];
            case 'documents':
            case 'document_creation':
                return $tier['max_documents'];
            case 'videos':
            case 'video_creation':
                return $tier['max_videos'];
            default:
                return 0;
        }
    }
    
    /**
     * Check if feature is available for tier
     */
    public function hasFeature($tier_id, $feature_name) {
        $tier = $this->getTierById($tier_id);
        if (!$tier || empty($tier['features'])) return false;
        
        return in_array($feature_name, $tier['features']);
    }
    
    /**
     * Create new membership tier (admin only)
     */
    public function createTier($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (name, display_name, description, price_annual, price_monthly, 
                      max_prompts_per_month, max_ai_generations_per_month, max_categories, max_bookmarks,
                      max_notes, max_documents, max_videos,
                      features, sort_order) 
                     VALUES (:name, :display_name, :description, :price_annual, :price_monthly,
                             :max_prompts_per_month, :max_ai_generations_per_month, :max_categories, :max_bookmarks,
                             :max_notes, :max_documents, :max_videos,
                             :features, :sort_order)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':display_name', $data['display_name']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price_annual', $data['price_annual']);
            $stmt->bindParam(':price_monthly', $data['price_monthly']);
            $stmt->bindParam(':max_prompts_per_month', $data['max_prompts_per_month']);
            $stmt->bindParam(':max_ai_generations_per_month', $data['max_ai_generations_per_month']);
            $stmt->bindParam(':max_categories', $data['max_categories']);
            $stmt->bindParam(':max_bookmarks', $data['max_bookmarks']);
            $stmt->bindParam(':max_notes', $data['max_notes']);
            $stmt->bindParam(':max_documents', $data['max_documents']);
            $stmt->bindParam(':max_videos', $data['max_videos']);
            $stmt->bindParam(':features', json_encode($data['features'] ?? []));
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch(PDOException $exception) {
            error_log("Create tier error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Update membership tier (admin only)
     */
    public function updateTier($tier_id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET display_name = :display_name, description = :description,
                         price_annual = :price_annual, price_monthly = :price_monthly,
                         max_prompts_per_month = :max_prompts_per_month,
                         max_ai_generations_per_month = :max_ai_generations_per_month,
                         max_categories = :max_categories, max_bookmarks = :max_bookmarks, 
                         max_notes = :max_notes, max_documents = :max_documents, max_videos = :max_videos,
                         features = :features,
                         sort_order = :sort_order, is_active = :is_active,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :tier_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tier_id', $tier_id);
            $stmt->bindParam(':display_name', $data['display_name']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price_annual', $data['price_annual']);
            $stmt->bindParam(':price_monthly', $data['price_monthly']);
            $stmt->bindParam(':max_prompts_per_month', $data['max_prompts_per_month']);
            $stmt->bindParam(':max_ai_generations_per_month', $data['max_ai_generations_per_month']);
            $stmt->bindParam(':max_categories', $data['max_categories']);
            $stmt->bindParam(':max_bookmarks', $data['max_bookmarks']);
            $stmt->bindParam(':max_notes', $data['max_notes']);
            $stmt->bindParam(':max_documents', $data['max_documents']);
            $stmt->bindParam(':max_videos', $data['max_videos']);
            $stmt->bindParam(':features', json_encode($data['features'] ?? []));
            $stmt->bindParam(':sort_order', $data['sort_order'] ?? 0);
            $stmt->bindParam(':is_active', $data['is_active'] ?? true ? 1 : 0);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update tier error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get tier comparison data for pricing page
     */
    public function getTierComparison() {
        $tiers = $this->getAllTiers(true);
        $comparison = [];
        
        foreach ($tiers as $tier) {
            $comparison[] = [
                'id' => $tier['id'],
                'name' => $tier['name'],
                'display_name' => $tier['display_name'],
                'description' => $tier['description'],
                'price_annual' => $tier['price_annual'],
                'price_monthly' => $tier['price_monthly'],
                'features' => $tier['features'],
                'limits' => [
                    'prompts' => $tier['max_prompts_per_month'] == 0 ? 'Unlimited' : number_format($tier['max_prompts_per_month']),
                    'ai_generations' => $tier['max_ai_generations_per_month'] == 0 ? 'Unlimited' : number_format($tier['max_ai_generations_per_month']),
                    'categories' => $tier['max_categories'] == 0 ? 'Unlimited' : number_format($tier['max_categories']),
                    'bookmarks' => $tier['max_bookmarks'] == 0 ? 'Unlimited' : number_format($tier['max_bookmarks']),
                    'notes' => $tier['max_notes'] == 0 ? 'Unlimited' : number_format($tier['max_notes']),
                    'documents' => $tier['max_documents'] == 0 ? 'Unlimited' : number_format($tier['max_documents']),
                    'videos' => $tier['max_videos'] == 0 ? 'Unlimited' : number_format($tier['max_videos']),
                ],
                'is_free' => $tier['price_annual'] == 0,
                'is_premium' => $tier['name'] === 'premium'
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Calculate prorated upgrade cost
     */
    public function calculateUpgradeCost($from_tier_id, $to_tier_id, $current_subscription_end = null) {
        $from_tier = $this->getTierById($from_tier_id);
        $to_tier = $this->getTierById($to_tier_id);
        
        if (!$from_tier || !$to_tier) {
            return ['error' => 'Invalid tier IDs'];
        }
        
        // Simple calculation - full price for now
        // In production, you'd implement prorated billing
        return [
            'from_tier' => $from_tier['display_name'],
            'to_tier' => $to_tier['display_name'],
            'annual_cost' => $to_tier['price_annual'],
            'monthly_cost' => $to_tier['price_monthly'],
            'savings_annual' => max(0, ($to_tier['price_monthly'] * 12) - $to_tier['price_annual'])
        ];
    }
    
    /**
     * Format tier data for API responses
     */
    private function formatTierData($row) {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'display_name' => $row['display_name'],
            'description' => $row['description'],
            'price_annual' => (float)$row['price_annual'],
            'price_monthly' => (float)$row['price_monthly'],
            'max_prompts_per_month' => (int)$row['max_prompts_per_month'],
            'max_ai_generations_per_month' => (int)$row['max_ai_generations_per_month'],
            'max_categories' => (int)$row['max_categories'],
            'max_bookmarks' => (int)$row['max_bookmarks'],
            'max_notes' => (int)($row['max_notes'] ?? 0),
            'max_documents' => (int)($row['max_documents'] ?? 0),
            'max_videos' => (int)($row['max_videos'] ?? 0),
            'features' => json_decode($row['features'] ?? '[]', true),
            'is_active' => (bool)$row['is_active'],
            'sort_order' => (int)$row['sort_order'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    /**
     * Get tier statistics (admin)
     */
    public function getTierStatistics() {
        try {
            $query = "SELECT 
                        mt.id, mt.name, mt.display_name,
                        COUNT(u.id) as user_count,
                        COUNT(CASE WHEN us.status = 'active' THEN 1 END) as active_subscriptions,
                        SUM(CASE WHEN pt.status = 'success' AND pt.transaction_type IN ('subscription', 'renewal') 
                                 THEN pt.amount ELSE 0 END) as total_revenue
                      FROM " . $this->table_name . " mt
                      LEFT JOIN users u ON u.current_tier_id = mt.id
                      LEFT JOIN user_subscriptions us ON us.tier_id = mt.id AND us.status = 'active'
                      LEFT JOIN payment_transactions pt ON pt.subscription_id = us.id
                      WHERE mt.is_active = 1
                      GROUP BY mt.id, mt.name, mt.display_name
                      ORDER BY mt.sort_order";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $exception) {
            error_log("Get tier statistics error: " . $exception->getMessage());
            return [];
        }
    }
}
?>

