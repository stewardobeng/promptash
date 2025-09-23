<?php
/**
 * PaymentProcessor - Handles Paystack integration for subscription payments
 */
class PaymentProcessor {
    private $secret_key;
    private $public_key;
    private $base_url = 'https://api.paystack.co';
    
    public function __construct() {
        // Get Paystack keys from app settings
        require_once __DIR__ . '/../app/models/AppSettings.php';
        $appSettings = new AppSettings();
        
        $this->secret_key = trim($appSettings->getSetting('paystack_secret_key', ''));
        $this->public_key = trim($appSettings->getSetting('paystack_public_key', ''));
    }
    
    /**
     * Check if payment processor is configured
     */
    public function isConfigured() {
        $hasKeys = !empty($this->secret_key) && !empty($this->public_key);
        $validFormat = $this->validateKeyFormats();
        return $hasKeys && $validFormat;
    }
    
    /**
     * Validate Paystack key formats
     */
    private function validateKeyFormats() {
        // Check public key format
        $validPublic = (strpos($this->public_key, 'pk_test_') === 0 || 
                       strpos($this->public_key, 'pk_live_') === 0) && 
                       strlen($this->public_key) > 20;
        
        // Check secret key format  
        $validSecret = (strpos($this->secret_key, 'sk_test_') === 0 || 
                       strpos($this->secret_key, 'sk_live_') === 0) && 
                       strlen($this->secret_key) > 20;
        
        // Check key environment match (both test or both live)
        $publicIsTest = strpos($this->public_key, 'pk_test_') === 0;
        $secretIsTest = strpos($this->secret_key, 'sk_test_') === 0;
        $environmentMatch = $publicIsTest === $secretIsTest;
        
        if (!$validPublic) {
            error_log("Invalid Paystack public key format: " . substr($this->public_key, 0, 15) . "...");
        }
        if (!$validSecret) {
            error_log("Invalid Paystack secret key format: " . substr($this->secret_key, 0, 15) . "...");
        }
        if (!$environmentMatch) {
            error_log("Paystack key environment mismatch - public is " . ($publicIsTest ? 'test' : 'live') . ", secret is " . ($secretIsTest ? 'test' : 'live'));
        }
        
        return $validPublic && $validSecret && $environmentMatch;
    }
    
    /**
     * Get public key for frontend integration
     */
    public function getPublicKey() {
        return $this->public_key;
    }
    
    /**
     * Test Paystack API connection
     */
    public function testConnection() {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Paystack keys not configured or invalid format'
            ];
        }
        
        try {
            // Test with a simple API call (list banks)
            $response = $this->makeRequest('/bank', null, 'GET');
            
            if ($response && isset($response['status']) && $response['status'] === true) {
                return [
                    'success' => true,
                    'message' => 'Paystack connection successful',
                    'data' => [
                        'environment' => strpos($this->secret_key, 'sk_test_') === 0 ? 'test' : 'live',
                        'banks_count' => isset($response['data']) ? count($response['data']) : 0
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Unexpected response from Paystack API'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize payment transaction
     */
    public function initializePayment($email, $amount, $currency = 'GHS', $metadata = []) {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to smallest currency unit (pesewas for GHS, kobo for NGN, cents for USD)
            'currency' => $currency,
            'callback_url' => $this->getCallbackUrl(),
            'metadata' => $metadata
        ];
        
        $response = $this->makeRequest('/transaction/initialize', $data);
        
        if (!$response['status']) {
            throw new Exception('Failed to initialize payment: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Verify payment transaction
     */
    public function verifyPayment($reference) {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $response = $this->makeRequest('/transaction/verify/' . $reference, null, 'GET');
        
        if (!$response['status']) {
            throw new Exception('Failed to verify payment: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Create subscription plan
     */
    public function createPlan($name, $amount, $interval = 'annually', $currency = 'GHS') {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $data = [
            'name' => $name,
            'amount' => $amount * 100, // Convert to smallest currency unit (pesewas for GHS, kobo for NGN, cents for USD)
            'interval' => $interval,
            'currency' => $currency
        ];
        
        $response = $this->makeRequest('/plan', $data);
        
        if (!$response['status']) {
            throw new Exception('Failed to create plan: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Create subscription
     */
    public function createSubscription($customer_email, $plan_code, $authorization_code = null) {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $data = [
            'customer' => $customer_email,
            'plan' => $plan_code
        ];
        
        if ($authorization_code) {
            $data['authorization'] = $authorization_code;
        }
        
        $response = $this->makeRequest('/subscription', $data);
        
        if (!$response['status']) {
            throw new Exception('Failed to create subscription: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription($subscription_code, $token) {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $data = [
            'code' => $subscription_code,
            'token' => $token
        ];
        
        $response = $this->makeRequest('/subscription/disable', $data);
        
        if (!$response['status']) {
            throw new Exception('Failed to cancel subscription: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Get subscription details
     */
    public function getSubscription($subscription_code) {
        if (!$this->isConfigured()) {
            throw new Exception('Payment processor is not configured');
        }
        
        $response = $this->makeRequest('/subscription/' . $subscription_code, null, 'GET');
        
        if (!$response['status']) {
            throw new Exception('Failed to get subscription: ' . $response['message']);
        }
        
        return $response['data'];
    }
    
    /**
     * Process premium upgrade
     */
    public function processPremiumUpgrade($user_id, $email, $tier_id) {
        require_once __DIR__ . '/../app/models/MembershipTier.php';
        require_once __DIR__ . '/../app/models/User.php';
        require_once __DIR__ . '/../app/models/AppSettings.php';
        
        $tierModel = new MembershipTier();
        $userModel = new User();
        $appSettings = new AppSettings();
        
        $tier = $tierModel->getTierById($tier_id);
        if (!$tier) {
            throw new Exception('Invalid membership tier');
        }
        
        // Get currency from app settings
        $currency = $appSettings->getPaymentCurrency();
        
        // Initialize payment
        $metadata = [
            'user_id' => $user_id,
            'tier_id' => $tier_id,
            'upgrade_type' => 'premium_subscription',
            'currency' => $currency
        ];
        
        $payment = $this->initializePayment(
            $email,
            $tier['price_annual'],
            $currency,
            $metadata
        );
        
        // Log transaction as pending
        $this->logTransaction($user_id, null, 'subscription', $payment['reference'], $tier['price_annual'], 'pending');
        
        return [
            'payment_url' => $payment['authorization_url'],
            'reference' => $payment['reference'],
            'access_code' => $payment['access_code']
        ];
    }
    
    /**
     * Handle payment webhook/callback
     */
    public function handlePaymentCallback($reference) {
        try {
            $payment = $this->verifyPayment($reference);
            
            if ($payment['status'] === 'success') {
                $metadata = $payment['metadata'];
                $user_id = $metadata['user_id'] ?? null;
                $tier_id = $metadata['tier_id'] ?? null;
                
                if ($user_id && $tier_id) {
                    return $this->activatePremiumMembership($user_id, $tier_id, $payment);
                }
            }
            
            return ['success' => false, 'message' => 'Payment verification failed'];
        } catch (Exception $e) {
            error_log('Payment callback error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Activate premium membership after successful payment
     */
    private function activatePremiumMembership($user_id, $tier_id, $payment_data) {
        require_once __DIR__ . '/../app/models/User.php';
        require_once __DIR__ . '/../app/models/MembershipTier.php';
        require_once __DIR__ . '/NotificationService.php';
        
        $userModel = new User();
        $tierModel = new MembershipTier();
        $notificationService = new NotificationService();
        
        try {
            // Update user tier
            $userModel->updateMembershipTier($user_id, $tier_id);
            
            // Create subscription record
            $subscription_id = $this->createSubscriptionRecord($user_id, $tier_id, $payment_data);
            
            // Log successful transaction
            $this->logTransaction(
                $user_id,
                $subscription_id,
                'subscription',
                $payment_data['reference'],
                $payment_data['amount'] / 100, // Convert back from smallest currency unit
                'success',
                $payment_data
            );
            
            // Send subscription confirmation notifications
            $user = $userModel->getById($user_id);
            $tier = $tierModel->getTierById($tier_id);
            
            if ($user && $tier) {
                // Send both in-app and email notifications
                $notificationService->sendSubscriptionConfirmation(
                    $user,
                    $tier['name'],
                    $payment_data['amount'] / 100
                );
                
                error_log("PaymentProcessor: Sent subscription confirmation notifications to user {$user_id}");
            }
            
            return [
                'success' => true,
                'message' => 'Premium membership activated successfully',
                'subscription_id' => $subscription_id
            ];
        } catch (Exception $e) {
            error_log('Failed to activate premium membership: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to activate membership'];
        }
    }
    
    /**
     * Create subscription record in database
     */
    private function createSubscriptionRecord($user_id, $tier_id, $payment_data) {
        $database = new Database();
        $db = $database->getConnection();
        
        // First, deactivate any existing active subscriptions for this user
        $deactivate_query = "UPDATE user_subscriptions 
                           SET status = 'expired', cancelled_at = NOW() 
                           WHERE user_id = :user_id AND status = 'active'";
        
        $deactivate_stmt = $db->prepare($deactivate_query);
        $deactivate_stmt->bindParam(':user_id', $user_id);
        $deactivate_stmt->execute();
        
        // Log how many subscriptions were deactivated
        $deactivated_count = $deactivate_stmt->rowCount();
        if ($deactivated_count > 0) {
            error_log("PaymentProcessor: Deactivated {$deactivated_count} existing active subscription(s) for user {$user_id}");
        }
        
        // Now create the new subscription record
        $query = "INSERT INTO user_subscriptions 
                 (user_id, tier_id, status, billing_cycle, started_at, expires_at, 
                  external_subscription_id, last_payment_at, next_payment_at) 
                 VALUES (:user_id, :tier_id, 'active', 'annual', NOW(), 
                         DATE_ADD(NOW(), INTERVAL 1 YEAR), :external_id, NOW(), 
                         DATE_ADD(NOW(), INTERVAL 1 YEAR))";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':tier_id', $tier_id);
        $stmt->bindParam(':external_id', $payment_data['reference']);
        
        if ($stmt->execute()) {
            $subscription_id = $db->lastInsertId();
            error_log("PaymentProcessor: Created new subscription {$subscription_id} for user {$user_id}");
            return $subscription_id;
        }
        
        throw new Exception('Failed to create subscription record');
    }
    
    /**
     * Log payment transaction
     */
    private function logTransaction($user_id, $subscription_id, $type, $reference, $amount, $status, $gateway_response = null) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO payment_transactions 
                 (user_id, subscription_id, transaction_type, external_transaction_id, 
                  amount, status, gateway_response, processed_at) 
                 VALUES (:user_id, :subscription_id, :type, :reference, :amount, :status, 
                         :gateway_response, :processed_at)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':subscription_id', $subscription_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':reference', $reference);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':status', $status);
        $stmt->bindValue(':gateway_response', json_encode($gateway_response));
        $stmt->bindValue(':processed_at', $status === 'pending' ? null : date('Y-m-d H:i:s'));
        
        return $stmt->execute();
    }
    
    /**
     * Make HTTP request to Paystack API
     */
    private function makeRequest($endpoint, $data = null, $method = 'POST') {
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        $decoded_response = json_decode($response, true);
        
        if ($http_code >= 400) {
            $error_message = 'Unknown API error';
            
            if (isset($decoded_response['message'])) {
                $error_message = $decoded_response['message'];
            } elseif (isset($decoded_response['error'])) {
                $error_message = is_array($decoded_response['error']) ? 
                    implode(', ', $decoded_response['error']) : $decoded_response['error'];
            }
            
            // Enhanced error context
            if ($http_code === 401) {
                $error_message = "Invalid API key. Please verify your Paystack secret key is correct and active.";
            }
            
            throw new Exception('Paystack API Error: ' . $error_message);
        }
        
        return $decoded_response;
    }
    
    /**
     * Get callback URL for payment completion
     */
    private function getCallbackUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $script_path . '/index.php?page=payment_callback';
    }
}
?>