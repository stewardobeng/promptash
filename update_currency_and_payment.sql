-- Update Currency and Payment System
-- This script updates existing installations to use Ghana Cedis (GHS) 
-- and ensures all payment/membership settings are correct

-- Update app settings for currency and AI model
UPDATE `app_settings` SET `setting_value` = 'GHS' WHERE `setting_name` = 'payment_currency';
UPDATE `app_settings` SET `setting_value` = 'moonshotai/kimi-k2:free' WHERE `setting_name` = 'selected_ai_model';

-- Insert missing app settings if they don't exist
INSERT IGNORE INTO `app_settings` (`setting_name`, `setting_value`, `setting_type`, `description`) VALUES
('payment_currency', 'GHS', 'string', 'Default payment currency'),
('paystack_public_key', '', 'string', 'Paystack public key'),
('paystack_secret_key', '', 'string', 'Paystack secret key'),
('selected_ai_model', 'moonshotai/kimi-k2:free', 'string', 'Selected AI model'),
('ai_enabled', 'false', 'boolean', 'Enable AI features'),
('openrouter_api_key', '', 'string', 'OpenRouter API key for AI features');

-- Update premium tier pricing to 100 GHS
UPDATE `membership_tiers` SET 
    `price_annual` = 100.00,
    `price_monthly` = 10.00
WHERE `name` = 'premium';

-- Ensure free tier is set correctly 
UPDATE `membership_tiers` SET 
    `price_annual` = 0.00,
    `price_monthly` = 0.00
WHERE `name` = 'free';

-- Update any existing payment transactions to use correct currency
-- (This is safe as it only updates the display currency, not amounts)
UPDATE `payment_transactions` SET `currency` = 'GHS' WHERE `currency` = 'USD' OR `currency` IS NULL;

-- Add currency column to payment_transactions if it doesn't exist
ALTER TABLE `payment_transactions` 
ADD COLUMN `currency` VARCHAR(3) DEFAULT 'GHS' AFTER `amount`;

-- Ensure all admin users are upgraded to premium
UPDATE `users` SET `current_tier_id` = 2 WHERE `role` = 'admin' AND `current_tier_id` = 1;

-- Create user subscriptions for admin users if they don't exist
INSERT IGNORE INTO `user_subscriptions` 
(`user_id`, `tier_id`, `status`, `billing_cycle`, `started_at`, `expires_at`, `external_subscription_id`)
SELECT 
    u.id,
    2,
    'active',
    'annual',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 10 YEAR),
    CONCAT('admin_auto_', u.id)
FROM `users` u 
WHERE u.role = 'admin' 
AND NOT EXISTS (
    SELECT 1 FROM `user_subscriptions` us 
    WHERE us.user_id = u.id AND us.status = 'active'
);

SELECT 'Currency and payment system update completed successfully!' as result;