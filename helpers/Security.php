<?php
/**
 * Security Helper Class
 * Provides CSRF protection, rate limiting, and other security utilities
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Clean old tokens (older than 1 hour)
        self::cleanOldCSRFTokens();
        
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            return false;
        }
        
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        // Check if token is not older than 1 hour
        $tokenTime = $_SESSION['csrf_tokens'][$token];
        if (time() - $tokenTime > 3600) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Remove token after use (one-time use)
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    /**
     * Clean old CSRF tokens
     */
    private static function cleanOldCSRFTokens() {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }
        
        $currentTime = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $time) {
            if ($currentTime - $time > 3600) { // 1 hour
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
    
    /**
     * Rate limiting for login attempts
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) { // 15 minutes
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $currentTime = time();
        $key = 'login_' . $identifier;
        
        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [
                'attempts' => 0,
                'last_attempt' => 0,
                'blocked_until' => 0
            ];
        }
        
        $rateLimit = &$_SESSION['rate_limits'][$key];
        
        // Check if currently blocked
        if ($rateLimit['blocked_until'] > $currentTime) {
            return [
                'allowed' => false,
                'remaining_time' => $rateLimit['blocked_until'] - $currentTime
            ];
        }
        
        // Reset counter if time window has passed
        if ($currentTime - $rateLimit['last_attempt'] > $timeWindow) {
            $rateLimit['attempts'] = 0;
        }
        
        // Check if limit exceeded
        if ($rateLimit['attempts'] >= $maxAttempts) {
            $rateLimit['blocked_until'] = $currentTime + $timeWindow;
            return [
                'allowed' => false,
                'remaining_time' => $timeWindow
            ];
        }
        
        return ['allowed' => true, 'attempts' => $rateLimit['attempts']];
    }
    
    /**
     * Record failed login attempt
     */
    public static function recordFailedAttempt($identifier) {
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $key = 'login_' . $identifier;
        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [
                'attempts' => 0,
                'last_attempt' => 0,
                'blocked_until' => 0
            ];
        }
        
        $_SESSION['rate_limits'][$key]['attempts']++;
        $_SESSION['rate_limits'][$key]['last_attempt'] = time();
    }
    
    /**
     * Clear rate limit for successful login
     */
    public static function clearRateLimit($identifier) {
        if (isset($_SESSION['rate_limits'])) {
            $key = 'login_' . $identifier;
            unset($_SESSION['rate_limits'][$key]);
        }
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Enforce HTTPS (if using HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy - **FIXED** to allow Google Fonts, Paystack assets, and YouTube embeds
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' blob: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://js.paystack.co; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://paystack.com; " .
               "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' https://api.paystack.co https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://pixiebrix-extension-source-maps.s3.amazonaws.com https://www.google-analytics.com; " .
               "frame-src 'self' https://checkout.paystack.com https://www.youtube.com;"; // **MODIFIED LINE**
        
        header("Content-Security-Policy: $csp");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $minLength = 8;
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Calculate password strength score
     */
    private static function calculatePasswordStrength($password) {
        $score = 0;
        
        // Length bonus
        $score += min(25, strlen($password) * 2);
        
        // Character variety bonus
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Additional complexity bonus
        if (preg_match('/[a-z].*[A-Z]|[A-Z].*[a-z]/', $password)) $score += 5;
        if (preg_match('/[0-9].*[^A-Za-z0-9]|[^A-Za-z0-9].*[0-9]/', $password)) $score += 5;
        
        return min(100, $score);
    }
    
    /**
     * Check if request is from allowed origin
     */
    public static function validateOrigin() {
        $allowedHosts = [
            $_SERVER['HTTP_HOST'] ?? '',
            'localhost',
            '127.0.0.1'
        ];
        
        if (isset($_SERVER['HTTP_REFERER'])) {
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            return in_array($refererHost, $allowedHosts);
        }
        
        return true; // Allow requests without referer
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log("SECURITY: " . json_encode($logEntry));
    }
}
?>
