<?php
/**
 * AI Helper Class for OpenAI API Integration
 * Supports multiple OpenAI models with admin-controlled configuration
 */
class AIHelper {
    private $api_key;
    private $base_url = 'https://api.openai.com/v1';
    private $model;
    private $app_settings;
    
    public function __construct($app_settings = null) {
        if ($app_settings) {
            $this->app_settings = $app_settings;
            $this->api_key = $app_settings->getSetting('openai_api_key');
            $this->model = $app_settings->getSetting('selected_openai_model');
        }
    }
    
    /**
     * Initialize from admin settings
     */
    public static function fromAdminSettings() {
        require_once __DIR__ . '/../app/models/AppSettings.php';
        $appSettings = new AppSettings();
        return new self($appSettings);
    }
    
    /**
     * Set API key manually
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Set model manually
     */
    public function setModel($model) {
        $this->model = $model;
    }
    
    /**
     * Check if API key is configured
     */
    public function hasApiKey() {
        return !empty($this->api_key);
    }
    
    /**
     * Check if AI is enabled and properly configured
     */
    public function isAiAvailable() {
        return $this->hasApiKey() && 
               !empty($this->model) && 
               ($this->app_settings ? $this->app_settings->isAiEnabled() : true);
    }
    
    /**
     * Make API request to OpenAI
     */
    private function makeRequest($endpoint, $data = null, $method = 'POST') {
        if (!$this->hasApiKey()) {
            throw new Exception('OpenAI API key not configured');
        }
        
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        $decoded_response = json_decode($response, true);
        
        if ($http_code !== 200) {
            $error_message = 'Unknown API error';
            if (isset($decoded_response['error']['message'])) {
                $error_message = $decoded_response['error']['message'];
            }
            throw new Exception('OpenAI API Error: ' . $error_message);
        }
        
        return $decoded_response;
    }
    
    /**
     * Get available models from OpenAI API
     */
    public function getAvailableModels() {
        if (!$this->hasApiKey()) {
            return [];
        }
        
        try {
            $response = $this->makeRequest('/models', null, 'GET');
            $models = [];
            
            if (isset($response['data']) && is_array($response['data'])) {
                foreach ($response['data'] as $model) {
                    // Filter for relevant chat models (GPT) and DALL-E
                    if (strpos($model['id'], 'gpt') === 0 || strpos($model['id'], 'dall-e') === 0) {
                        $models[] = $model['id'];
                    }
                }
            }
            
            sort($models);
            return $models;
        } catch (Exception $e) {
            error_log("Failed to fetch OpenAI models: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate a prompt using AI
     */
    public function generatePromptFromDescription($description, $category = null, $style = 'professional', $target_audience = 'general') {
        $system_message = "You are an expert AI prompt engineer specializing in creating high-quality, effective prompts. Create prompts that are clear, specific, actionable, and optimized for AI language models.";
        
        $user_message = "Create a detailed, effective AI prompt based on this description: {$description}";
        
        if ($category) {
            $user_message .= "\nCategory: {$category}";
        }
        
        $user_message .= "\nStyle: {$style}";
        $user_message .= "\nTarget audience: {$target_audience}";
        
        $user_message .= "\n\nRequirements:";
        $user_message .= "\n- Make the prompt clear and specific";
        $user_message .= "\n- Include relevant context and instructions";
        $user_message .= "\n- Ensure it will produce consistent results";
        $user_message .= "\n- Make it engaging and effective";
        $user_message .= "\n\nReturn only the generated prompt text, no explanations or formatting.";
        
        $data = [
            'model' => $this->model ?: 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $user_message]
            ],
            'max_tokens' => 800,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Enhance an existing prompt
     */
    public function enhancePrompt($prompt, $enhancement_type = 'clarity') {
        $enhancement_instructions = [
            'clarity' => 'Make this prompt clearer and more specific',
            'creativity' => 'Make this prompt more creative and engaging',
            'professional' => 'Make this prompt more professional and polished',
            'detailed' => 'Add more detail and context to this prompt',
            'concise' => 'Make this prompt more concise while keeping its effectiveness'
        ];
        
        $instruction = $enhancement_instructions[$enhancement_type] ?? $enhancement_instructions['clarity'];
        
        $system_message = "You are an expert prompt engineer. Enhance prompts to make them more effective while maintaining their original intent.";
        
        $user_message = "{$instruction}:\n\n{$prompt}\n\nReturn only the enhanced prompt text, no explanations.";
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $user_message]
            ],
            'max_tokens' => 600,
            'temperature' => 0.6
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Generate tags for a prompt
     */
    public function generateTags($prompt, $max_tags = 8) {
        $system_message = "You are an expert at categorizing and tagging content. Generate relevant, specific tags for prompts.";
        
        $user_message = "Generate {$max_tags} relevant tags for this prompt:\n\n{$prompt}\n\nReturn only the tags separated by commas, no explanations.";
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $user_message]
            ],
            'max_tokens' => 100,
            'temperature' => 0.5
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Generate a title for a prompt
     */
    public function generateTitle($prompt) {
        $system_message = "You are an expert at creating concise, descriptive titles. Create titles that clearly describe the purpose of prompts.";
        
        $user_message = "Create a concise, descriptive title for this prompt:\n\n{$prompt}\n\nReturn only the title, no quotes or explanations.";
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $user_message]
            ],
            'max_tokens' => 50,
            'temperature' => 0.6
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        return trim($response['choices'][0]['message']['content']);
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            if (!$this->hasApiKey()) {
                return ['success' => false, 'message' => 'No API key configured'];
            }
            
            if (!$this->isAiAvailable()) {
                return ['success' => false, 'message' => 'AI service is not available'];
            }
            
            $data = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello, this is a test. Please respond with "API connection successful".']
                ],
                'max_tokens' => 20,
                'temperature' => 0.1
            ];
            
            $response = $this->makeRequest('/chat/completions', $data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                return [
                    'success' => true, 
                    'message' => 'API connection successful',
                    'model' => $this->model,
                    'response' => trim($response['choices'][0]['message']['content'])
                ];
            } else {
                return ['success' => false, 'message' => 'Unexpected response format'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get current model information
     */
    public function getCurrentModel() {
        return $this->model;
    }
}
?>