<?php
/**
 * App Settings Model - Global application configuration
 */
class AppSettings {
    private $db;
    private $table_name = "app_settings";
    
    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            error_log("AppSettings database connection failed: " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Get a setting value
     */
    public function getSetting($setting_key, $default = null) {
        // Return default value if database connection is not available
        if ($this->db === null) {
            return $default;
        }
        
        try {
            $query = "SELECT setting_value, setting_type FROM " . $this->table_name . " 
                     WHERE setting_key = :setting_key";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                return $this->castValue($row['setting_value'], $row['setting_type']);
            }
            
            return $default;
        } catch(PDOException $exception) {
            error_log("Get app setting error: " . $exception->getMessage());
            return $default;
        }
    }
    
    /**
     * Set a setting value
     */
    public function setSetting($setting_key, $setting_value, $setting_type = 'string') {
        // Return false if database connection is not available
        if ($this->db === null) {
            return false;
        }
        
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (setting_key, setting_value, setting_type) 
                     VALUES (:setting_key, :setting_value, :setting_type)
                     ON DUPLICATE KEY UPDATE 
                     setting_value = :setting_value_update, 
                     updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->bindParam(':setting_value', $setting_value);
            $stmt->bindParam(':setting_type', $setting_type);
            $stmt->bindParam(':setting_value_update', $setting_value);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Set app setting error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get all settings
     */
    public function getAllSettings() {
        // Return empty array if database connection is not available
        if ($this->db === null) {
            return [];
        }
        
        try {
            $query = "SELECT setting_key, setting_value, setting_type, description FROM " . $this->table_name . " 
                     ORDER BY setting_key";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = [
                    'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                    'type' => $row['setting_type'],
                    'description' => $row['description']
                ];
            }
            
            return $settings;
        } catch(PDOException $exception) {
            error_log("Get all app settings error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Cast value based on type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'boolean':
                // Handle string boolean values correctly
                if (is_string($value)) {
                    // Explicitly check for 'true' and '1' string values
                    // as filter_var treats any non-empty string as true
                    return $value === 'true' || $value === '1';
                }
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (int)$value : 0;
            case 'json':
                $decoded = json_decode($value, true);
                return $decoded !== null ? $decoded : [];
            default:
                return $value;
        }
    }
    
    /**
     * Get app name
     */
    public function getAppName() {
        return $this->getSetting('app_name', 'Promptash');
    }
    
    /**
     * Get app description
     */
    public function getAppDescription() {
        return $this->getSetting('app_description', 'Professional prompt management made simple');
    }
    
    /**
     * Check if registration is allowed
     */
    public function isRegistrationAllowed() {
        return $this->getSetting('allow_registration', true);
    }
    
    /**
     * Check if app is in maintenance mode
     */
    public function isMaintenanceMode() {
        return $this->getSetting('maintenance_mode', false);
    }
    
    /**
     * Get maximum prompts per user
     */
    public function getMaxPromptsPerUser() {
        return $this->getSetting('max_prompts_per_user', 1000);
    }
    
    /**
     * Check if user has reached prompt limit
     */
    public function hasUserReachedPromptLimit($user_id, $current_count = null) {
        $maxPrompts = $this->getMaxPromptsPerUser();
        
        // 0 means unlimited
        if ($maxPrompts === 0) {
            return false;
        }
        
        // If current count not provided, get it from database
        if ($current_count === null) {
            try {
                $query = "SELECT COUNT(*) as count FROM prompts WHERE user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $result = $stmt->fetch();
                $current_count = $result['count'] ?? 0;
            } catch(PDOException $exception) {
                error_log("Count prompts error: " . $exception->getMessage());
                return false; // Allow creation if we can't check
            }
        }
        
        return $current_count >= $maxPrompts;
    }
    
    /**
     * Get OpenAI API key (admin-controlled)
     */
    public function getOpenAIApiKey() {
        return $this->getSetting('openai_api_key', '');
    }
    
    /**
     * Get payment currency
     */
    public function getPaymentCurrency() {
        return $this->getSetting('payment_currency', 'GHS');
    }
    
    /**
     * Get currency symbol based on payment currency
     */
    public function getCurrencySymbol() {
        $currency = $this->getPaymentCurrency();
        switch ($currency) {
            case 'GHS':
                return 'GHS';
            case 'NGN':
                return '₦';
            case 'USD':
                return '$';
            default:
                return $currency;
        }
    }
    
    /**
     * Format price with currency
     */
    public function formatPrice($amount) {
        $currency = $this->getPaymentCurrency();
        $symbol = $this->getCurrencySymbol();
        
        if ($currency === 'GHS') {
            return $symbol . ' ' . number_format($amount, 0);
        } else {
            return $symbol . number_format($amount, 0);
        }
    }
    
    /**
     * Set OpenAI API key
     */
    public function setOpenAIApiKey($api_key) {
        return $this->setSetting('openai_api_key', $api_key, 'string');
    }
    
    /**
     * Get selected AI model
     */
    public function getSelectedAiModel() {
        return $this->getSetting('selected_openai_model', 'gpt-3.5-turbo');
    }
    
    /**
     * Set selected AI model
     */
    public function setSelectedAiModel($model) {
        return $this->setSetting('selected_openai_model', $model, 'string');
    }
    
    /**
     * Check if AI is enabled (admin-controlled)
     */
    public function isAiEnabled() {
        return $this->getSetting('ai_enabled', false);
    }
    
    /**
     * Set AI enabled status
     */
    public function setAiEnabled($enabled) {
        return $this->setSetting('ai_enabled', $enabled ? 'true' : 'false', 'boolean');
    }
}
?>