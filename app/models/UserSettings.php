<?php
/**
 * User Settings Model
 */
class UserSettings {
    private $db;
    private $table_name = "user_settings";
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get a setting value for a user
     */
    public function getSetting($user_id, $setting_key, $default = null) {
        try {
            $query = "SELECT setting_value FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND setting_key = :setting_key";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                return $row['setting_value'];
            }
            
            return $default;
        } catch(PDOException $exception) {
            error_log("Get setting error: " . $exception->getMessage());
            return $default;
        }
    }
    
    /**
     * Set a setting value for a user
     */
    public function setSetting($user_id, $setting_key, $setting_value) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (user_id, setting_key, setting_value) 
                     VALUES (:user_id, :setting_key, :setting_value)
                     ON DUPLICATE KEY UPDATE 
                     setting_value = :setting_value_update, 
                     updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->bindParam(':setting_value', $setting_value);
            $stmt->bindParam(':setting_value_update', $setting_value);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Set setting error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get all settings for a user
     */
    public function getAllSettings($user_id) {
        try {
            $query = "SELECT setting_key, setting_value FROM " . $this->table_name . " 
                     WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
        } catch(PDOException $exception) {
            error_log("Get all settings error: " . $exception->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a setting for a user
     */
    public function deleteSetting($user_id, $setting_key) {
        try {
            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND setting_key = :setting_key";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':setting_key', $setting_key);
            
            return $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Delete setting error: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Get OpenAI API key for user (encrypted storage recommended in production)
     */
    public function getOpenAIKey($user_id) {
        return $this->getSetting($user_id, 'openai_api_key');
    }
    
    /**
     * Set OpenAI API key for user
     */
    public function setOpenAIKey($user_id, $api_key) {
        // In production, consider encrypting the API key
        return $this->setSetting($user_id, 'openai_api_key', $api_key);
    }
    
    /**
     * Get AI preferences for user
     */
    public function getAIPreferences($user_id) {
        $defaults = [
            'ai_enabled' => 'true',
            'default_enhancement_type' => 'clarity',
            'max_tags' => '8',
            'ai_temperature' => '0.7'
        ];
        
        $settings = $this->getAllSettings($user_id);
        return array_merge($defaults, $settings);
    }
    
    /**
     * Set multiple settings at once
     */
    public function setMultipleSettings($user_id, $settings) {
        $success_count = 0;
        $total_count = count($settings);
        
        foreach ($settings as $key => $value) {
            if ($this->setSetting($user_id, $key, $value)) {
                $success_count++;
            }
        }
        
        return [
            'success' => $success_count > 0,
            'total' => $total_count,
            'saved' => $success_count,
            'message' => "Saved {$success_count} of {$total_count} settings"
        ];
    }
}
?>
