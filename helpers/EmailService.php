<?php
/**
 * EmailService - Handles SMTP email functionality
 * Supports sending notifications, password resets, and other system emails
 */
class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $from_email;
    private $from_name;
    private $enabled;
    
    public function __construct() {
        require_once __DIR__ . '/../app/models/AppSettings.php';
        $appSettings = new AppSettings();
        
        // Load SMTP configuration from app settings
        $this->smtp_host = $appSettings->getSetting('smtp_host', '');
        $this->smtp_port = (int)$appSettings->getSetting('smtp_port', 587);
        $this->smtp_username = $appSettings->getSetting('smtp_username', '');
        $this->smtp_password = $appSettings->getSetting('smtp_password', '');
        $this->smtp_encryption = $appSettings->getSetting('smtp_encryption', 'tls');
        $this->from_email = $appSettings->getSetting('smtp_from_email', '');
        $this->from_name = $appSettings->getSetting('smtp_from_name', 'Promptash');
        $this->enabled = $appSettings->getSetting('email_enabled', 'false') === 'true';
    }
    
    /**
     * Check if email service is configured and enabled
     */
    public function isConfigured() {
        return $this->enabled && 
               !empty($this->smtp_host) && 
               !empty($this->smtp_username) && 
               !empty($this->smtp_password) && 
               !empty($this->from_email);
    }
    
    /**
     * Send email using PHP's built-in mail function with SMTP
     */
    public function sendEmail($to_email, $to_name, $subject, $body, $is_html = true) {
        if (!$this->isConfigured()) {
            error_log("EmailService: Email not configured, skipping send");
            return false;
        }
        
        try {
            // Prepare headers
            $headers = [];
            $headers[] = "From: {$this->from_name} <{$this->from_email}>";
            $headers[] = "Reply-To: {$this->from_email}";
            $headers[] = "X-Mailer: Promptash";
            $headers[] = "MIME-Version: 1.0";
            
            if ($is_html) {
                $headers[] = "Content-Type: text/html; charset=UTF-8";
            } else {
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
            }
            
            // Configure SMTP settings
            ini_set('SMTP', $this->smtp_host);
            ini_set('smtp_port', $this->smtp_port);
            ini_set('sendmail_from', $this->from_email);
            
            // Send email
            $success = mail($to_email, $subject, $body, implode("\r\n", $headers));
            
            if ($success) {
                error_log("EmailService: Email sent successfully to {$to_email}");
                return true;
            } else {
                error_log("EmailService: Failed to send email to {$to_email}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("EmailService error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer (alternative method for better SMTP support)
     * This method can be used if PHPMailer is available
     */
    public function sendEmailWithPHPMailer($to_email, $to_name, $subject, $body, $is_html = true) {
        if (!$this->isConfigured()) {
            return false;
        }
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            // Fall back to basic mail function
            return $this->sendEmail($to_email, $to_name, $subject, $body, $is_html);
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->smtp_encryption;
            $mail->Port = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);
            
            // Content
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if (!$is_html) {
                $mail->AltBody = $body;
            }
            
            $mail->send();
            error_log("EmailService: PHPMailer email sent successfully to {$to_email}");
            return true;
            
        } catch (Exception $e) {
            error_log("EmailService PHPMailer error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email to new users
     */
    public function sendWelcomeEmail($user) {
        $subject = "Welcome to Promptash!";
        $body = $this->getWelcomeEmailTemplate($user);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, $reset_token) {
        $subject = "Reset Your Password - Promptash";
        $body = $this->getPasswordResetEmailTemplate($user, $reset_token);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Send subscription confirmation email
     */
    public function sendSubscriptionConfirmationEmail($user, $tier_name, $amount) {
        $subject = "Subscription Confirmed - Welcome to {$tier_name}!";
        $body = $this->getSubscriptionConfirmationTemplate($user, $tier_name, $amount);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Send payment due reminder email
     */
    public function sendPaymentDueReminderEmail($user, $tier_name, $due_date, $amount) {
        $subject = "Payment Reminder - {$tier_name} Subscription";
        $body = $this->getPaymentDueReminderTemplate($user, $tier_name, $due_date, $amount);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Send subscription expiry warning email
     */
    public function sendSubscriptionExpiryWarningEmail($user, $tier_name, $expiry_date) {
        $subject = "Subscription Expiring Soon - {$tier_name}";
        $body = $this->getSubscriptionExpiryWarningTemplate($user, $tier_name, $expiry_date);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Send prompt shared notification email
     */
    public function sendPromptSharedEmail($recipient, $sharer, $prompt_title) {
        $subject = "Prompt Shared with You - {$prompt_title}";
        $body = $this->getPromptSharedTemplate($recipient, $sharer, $prompt_title);
        
        return $this->sendEmail($recipient['email'], $recipient['first_name'] . ' ' . $recipient['last_name'], $subject, $body, true);
    }
    
    /**
     * Send usage limit warning email
     */
    public function sendUsageLimitWarningEmail($user, $usage_type, $percentage, $used, $limit) {
        $subject = "Usage Alert: {$percentage}% of {$usage_type} limit reached";
        $body = $this->getUsageLimitWarningTemplate($user, $usage_type, $percentage, $used, $limit);
        
        return $this->sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $body, true);
    }
    
    /**
     * Test email configuration
     */
    public function testEmailConfiguration($test_email) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Email service is not configured'
            ];
        }
        
        $subject = "Email Configuration Test - Promptash";
        $body = "<h2>Email Test Successful!</h2><p>This is a test email to verify your SMTP configuration is working correctly.</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
        
        $success = $this->sendEmail($test_email, 'Test User', $subject, $body, true);
        
        return [
            'success' => $success,
            'message' => $success ? 'Test email sent successfully' : 'Failed to send test email'
        ];
    }
    
    // Email Templates
    
    private function getWelcomeEmailTemplate($user) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Promptash!</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <p>Welcome to Promptash, your ultimate prompt management platform!</p>
                    <p>You can now:</p>
                    <ul>
                        <li>Create and organize your prompts</li>
                        <li>Use AI-powered prompt generation</li>
                        <li>Share prompts with others</li>
                        <li>Upgrade to Premium for unlimited features</li>
                    </ul>
                    <a href='" . $this->getBaseUrl() . "' class='button'>Get Started</a>
                </div>
                <div class='footer'>
                    <p>Need help? Contact our support team</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPasswordResetEmailTemplate($user, $reset_token) {
        $reset_url = $this->getBaseUrl() . "/index.php?page=reset_password&token=" . $reset_token;
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <p>You requested a password reset for your Promptash account.</p>
                    <div class='warning'>
                        <strong>Security Notice:</strong> If you didn't request this reset, please ignore this email and your password will remain unchanged.
                    </div>
                    <p>Click the button below to reset your password:</p>
                    <a href='{$reset_url}' class='button'>Reset Password</a>
                    <p>This link will expire in 1 hour for security reasons.</p>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p><a href='{$reset_url}'>{$reset_url}</a></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email from Promptash. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getSubscriptionConfirmationTemplate($user, $tier_name, $amount) {
        require_once __DIR__ . '/../app/models/AppSettings.php';
        $appSettings = new AppSettings();
        $formatted_amount = $appSettings->formatPrice($amount);
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Subscription Confirmed!</h1>
                </div>
                <div class='content'>
                    <h2>Thank you, {$user['first_name']}!</h2>
                    <div class='success'>
                        <strong>Payment Received:</strong> {$formatted_amount} for {$tier_name}
                    </div>
                    <p>Your subscription has been activated successfully! You now have access to:</p>
                    <ul>
                        <li>Unlimited prompt creation</li>
                        <li>300 AI generations per month</li>
                        <li>Unlimited categories</li>
                        <li>Priority support</li>
                        <li>Advanced features</li>
                    </ul>
                    <a href='" . $this->getBaseUrl() . "' class='button'>Access Your Account</a>
                </div>
                <div class='footer'>
                    <p>Questions? Contact our support team</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPaymentDueReminderTemplate($user, $tier_name, $due_date, $amount) {
        require_once __DIR__ . '/../app/models/AppSettings.php';
        $appSettings = new AppSettings();
        $formatted_amount = $appSettings->formatPrice($amount);
        $formatted_date = date('F j, Y', strtotime($due_date));
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .reminder { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⏰ Payment Reminder</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <div class='reminder'>
                        <strong>Subscription:</strong> {$tier_name}<br>
                        <strong>Amount Due:</strong> {$formatted_amount}<br>
                        <strong>Due Date:</strong> {$formatted_date}
                    </div>
                    <p>Your subscription payment is due soon. Please renew to continue enjoying premium features.</p>
                    <a href='" . $this->getBaseUrl() . "/index.php?page=upgrade' class='button'>Renew Subscription</a>
                </div>
                <div class='footer'>
                    <p>Questions about billing? Contact our support team</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getSubscriptionExpiryWarningTemplate($user, $tier_name, $expiry_date) {
        $formatted_date = date('F j, Y', strtotime($expiry_date));
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Subscription Expiring Soon</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <div class='warning'>
                        <strong>Your {$tier_name} subscription expires on {$formatted_date}</strong>
                    </div>
                    <p>Don't lose access to your premium features! Renew now to continue enjoying:</p>
                    <ul>
                        <li>Unlimited prompt creation</li>
                        <li>AI-powered generation</li>
                        <li>Advanced organization tools</li>
                        <li>Priority support</li>
                    </ul>
                    <a href='" . $this->getBaseUrl() . "/index.php?page=upgrade' class='button'>Renew Now</a>
                </div>
                <div class='footer'>
                    <p>Need help with renewal? Contact our support team</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPromptSharedTemplate($recipient, $sharer, $prompt_title) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .share-info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📝 Prompt Shared with You</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$recipient['first_name']}!</h2>
                    <div class='share-info'>
                        <strong>Shared by:</strong> {$sharer['first_name']} {$sharer['last_name']}<br>
                        <strong>Prompt:</strong> {$prompt_title}
                    </div>
                    <p>{$sharer['first_name']} has shared a prompt with you on Promptash!</p>
                    <a href='" . $this->getBaseUrl() . "/index.php?page=shared_prompts' class='button'>View Shared Prompt</a>
                </div>
                <div class='footer'>
                    <p>This prompt was shared through Promptash</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getUsageLimitWarningTemplate($user, $usage_type, $percentage, $used, $limit) {
        $type_labels = [
            'prompt_creation' => 'Prompt Creation',
            'ai_generation' => 'AI Generation',
            'category_creation' => 'Category Creation'
        ];
        $type_label = $type_labels[$usage_type] ?? $usage_type;
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
                .usage-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .progress-bar { background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
                .progress { background: #ffc107; height: 100%; width: {$percentage}%; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Usage Alert</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$user['first_name']}!</h2>
                    <div class='usage-info'>
                        <strong>{$type_label} Usage:</strong> {$used} of {$limit} ({$percentage}%)
                        <div class='progress-bar'>
                            <div class='progress'></div>
                        </div>
                    </div>
                    <p>You've used {$percentage}% of your monthly {$type_label} allowance.</p>
                    <p>Upgrade to Premium for unlimited access to all features!</p>
                    <a href='" . $this->getBaseUrl() . "/index.php?page=upgrade' class='button'>Upgrade to Premium</a>
                </div>
                <div class='footer'>
                    <p>Questions about your usage? Contact our support team</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_path = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $script_path;
    }
}
?>