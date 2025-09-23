<?php
/**
 * TOTP (Time-based One-Time Password) Helper Class
 * Implements RFC 6238 for two-factor authentication
 * Compatible with Google Authenticator, Authy, and other TOTP apps
 */
class TOTP {
    
    /**
     * Generate a random secret key for TOTP
     * @param int $length Length of the secret (default 32 characters)
     * @return string Base32 encoded secret
     */
    public static function generateSecret($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Generate TOTP code for given secret and time
     * @param string $secret Base32 encoded secret
     * @param int $time Unix timestamp (default: current time)
     * @param int $digits Number of digits in code (default: 6)
     * @param int $period Time period in seconds (default: 30)
     * @return string TOTP code
     */
    public static function generateCode($secret, $time = null, $digits = 6, $period = 30) {
        if ($time === null) {
            $time = time();
        }
        
        $counter = floor($time / $period);
        $secretBinary = self::base32Decode($secret);
        
        // Pack counter as 64-bit big-endian
        $counterBinary = pack('N*', 0) . pack('N*', $counter);
        
        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);
        
        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, $digits);
        
        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     * @param string $secret Base32 encoded secret
     * @param string $code Code to verify
     * @param int $window Time window for verification (default: 1, allows ±30 seconds)
     * @param int $time Unix timestamp (default: current time)
     * @return bool True if code is valid
     */
    public static function verifyCode($secret, $code, $window = 1, $time = null) {
        if ($time === null) {
            $time = time();
        }
        
        $period = 30;
        
        // Check current time and surrounding windows
        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $time + ($i * $period);
            $expectedCode = self::generateCode($secret, $testTime);
            
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate QR code URL for authenticator apps
     * @param string $secret Base32 encoded secret
     * @param string $label User identifier (email or username)
     * @param string $issuer Application name
     * @return string QR code URL
     */
    public static function getQRCodeUrl($secret, $label, $issuer = 'Promptash') {
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => '6',
            'period' => '30'
        ];
        
        $otpauth = 'otpauth://totp/' . urlencode($issuer . ':' . $label) . '?' . http_build_query($params);
        
        // Using QR Server API for QR code generation
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauth);
    }
    
    /**
     * Generate recovery codes
     * @param int $count Number of recovery codes to generate
     * @return array Array of recovery codes
     */
    public static function generateRecoveryCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
    
    /**
     * Verify recovery code
     * @param string $code Code to verify
     * @param array $validCodes Array of valid recovery codes
     * @return bool True if code is valid
     */
    public static function verifyRecoveryCode($code, $validCodes) {
        $code = strtoupper(trim($code));
        return in_array($code, $validCodes, true);
    }
    
    /**
     * Remove used recovery code from array
     * @param string $usedCode Code that was used
     * @param array $codes Array of recovery codes
     * @return array Updated array with used code removed
     */
    public static function removeRecoveryCode($usedCode, $codes) {
        $usedCode = strtoupper(trim($usedCode));
        return array_values(array_filter($codes, function($code) use ($usedCode) {
            return $code !== $usedCode;
        }));
    }
    
    /**
     * Decode Base32 string
     * @param string $input Base32 encoded string
     * @return string Binary data
     */
    private static function base32Decode($input) {
        $input = strtoupper($input);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            if ($char === '=') {
                break;
            }
            
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            
            $v = ($v << 5) | $pos;
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 0xff);
                $vbits -= 8;
            }
        }
        
        return $output;
    }
    
    /**
     * Get current time step for TOTP
     * @param int $time Unix timestamp (default: current time)
     * @param int $period Time period in seconds (default: 30)
     * @return int Current time step
     */
    public static function getCurrentTimeStep($time = null, $period = 30) {
        if ($time === null) {
            $time = time();
        }
        return floor($time / $period);
    }
    
    /**
     * Get remaining seconds until next time step
     * @param int $time Unix timestamp (default: current time)
     * @param int $period Time period in seconds (default: 30)
     * @return int Remaining seconds
     */
    public static function getRemainingSeconds($time = null, $period = 30) {
        if ($time === null) {
            $time = time();
        }
        return $period - ($time % $period);
    }
}
?>
