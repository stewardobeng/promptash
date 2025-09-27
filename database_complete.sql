-- =====================================================
-- Promptash - Complete Database Schema
-- Version: 2.5.1 (with Passkey support)
-- Description: Comprehensive database schema with all features including sharing and passkeys.
-- Last Updated: 2025-09-22 - Added passkey_credentials table.
-- =====================================================

-- Set MySQL mode and character set for compatibility
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- -- DATABASE: promptashdb
-- -- CORE MEMBERSHIP SYSTEM (Create first for foreign keys)
CREATE TABLE IF NOT EXISTS `membership_tiers` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`name` VARCHAR(50) NOT NULL UNIQUE,
`display_name` VARCHAR(100) NOT NULL,
`description` TEXT,
`price_annual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
`price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
`max_prompts_per_month` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_ai_generations_per_month` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_categories` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_bookmarks` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_notes` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_documents` INT DEFAULT 0 COMMENT '0 = unlimited',
`max_videos` INT DEFAULT 0 COMMENT '0 = unlimited',
`features` JSON COMMENT 'Additional features as JSON array',
`is_active` BOOLEAN DEFAULT TRUE,
`sort_order` INT DEFAULT 0,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX `idx_name` (`name`),
INDEX `idx_is_active` (`is_active`),
INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- USER MANAGEMENT
CREATE TABLE IF NOT EXISTS `users` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`username` VARCHAR(50) UNIQUE NOT NULL,
`email` VARCHAR(100) UNIQUE NOT NULL,
`password` VARCHAR(255) NOT NULL,
`first_name` VARCHAR(50) NOT NULL,
`last_name` VARCHAR(50) NOT NULL,
`role` ENUM('admin', 'user') DEFAULT 'user',
`is_active` BOOLEAN DEFAULT TRUE,
`current_tier_id` INT DEFAULT 1 COMMENT 'Current membership tier (defaults to free)',
`two_factor_enabled` BOOLEAN DEFAULT FALSE,
`two_factor_secret` VARCHAR(32),
`two_factor_recovery_codes` TEXT,
`login_token` VARCHAR(255) DEFAULT NULL,
`login_token_expires_at` DATETIME DEFAULT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`current_tier_id`) REFERENCES `membership_tiers`(`id`) ON DELETE SET NULL,
INDEX `idx_username` (`username`),
INDEX `idx_email` (`email`),
INDEX `idx_role` (`role`),
INDEX `idx_is_active` (`is_active`),
INDEX `idx_current_tier_id` (`current_tier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- CONTENT MANAGEMENT
CREATE TABLE IF NOT EXISTS `categories` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`name` VARCHAR(100) NOT NULL,
`description` TEXT,
`user_id` INT NOT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prompts` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`title` VARCHAR(255) NOT NULL,
`content` TEXT NOT NULL,
`description` TEXT,
`category_id` INT,
`user_id` INT NOT NULL,
`tags` VARCHAR(500),
`is_favorite` BOOLEAN DEFAULT FALSE,
`usage_count` INT DEFAULT 0,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_category_id` (`category_id`),
INDEX `idx_is_favorite` (`is_favorite`),
INDEX `idx_created_at` (`created_at`),
FULLTEXT(`title`, `content`, `description`, `tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookmarks` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`url` VARCHAR(2048) NOT NULL,
`title` VARCHAR(255) NOT NULL,
`description` TEXT,
`image` VARCHAR(2048),
`tags` VARCHAR(500),
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`),
FULLTEXT(`title`, `description`, `tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notes` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`title` VARCHAR(255) NOT NULL,
`content` TEXT,
`color` VARCHAR(20) DEFAULT 'yellow',
`is_pinned` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_is_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`file_name` VARCHAR(255) NOT NULL,
`file_path` VARCHAR(512) NOT NULL,
`file_size` INT NOT NULL,
`file_type` VARCHAR(100) NOT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `videos` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`url` VARCHAR(2048) NOT NULL,
`title` VARCHAR(255) NOT NULL,
`description` TEXT,
`thumbnail_url` VARCHAR(2048),
`channel_title` VARCHAR(255),
`duration` VARCHAR(20),
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- SUBSCRIPTION & PAYMENT SYSTEM
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`tier_id` INT NOT NULL,
`status` ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'active',
`billing_cycle` ENUM('monthly', 'annual') DEFAULT 'annual',
`started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`expires_at` TIMESTAMP NULL,
`cancelled_at` TIMESTAMP NULL,
`auto_renew` BOOLEAN DEFAULT TRUE,
`payment_method` VARCHAR(50) DEFAULT 'paystack',
`external_subscription_id` VARCHAR(255) COMMENT 'Paystack subscription ID',
`last_payment_at` TIMESTAMP NULL,
`next_payment_at` TIMESTAMP NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`tier_id`) REFERENCES `membership_tiers`(`id`) ON DELETE RESTRICT,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_tier_id` (`tier_id`),
INDEX `idx_status` (`status`),
INDEX `idx_expires_at` (`expires_at`),
INDEX `idx_next_payment_at` (`next_payment_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`subscription_id` INT,
`transaction_type` ENUM('subscription', 'upgrade', 'renewal', 'refund') NOT NULL,
`payment_method` VARCHAR(50) DEFAULT 'paystack',
`external_transaction_id` VARCHAR(255) NOT NULL COMMENT 'Paystack transaction reference',
`amount` DECIMAL(10,2) NOT NULL,
`currency` VARCHAR(3) DEFAULT 'USD',
`status` ENUM('pending', 'success', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
`gateway_response` JSON COMMENT 'Full gateway response for debugging',
`processed_at` TIMESTAMP NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions`(`id`) ON DELETE SET NULL,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_subscription_id` (`subscription_id`),
INDEX `idx_external_transaction_id` (`external_transaction_id`),
INDEX `idx_status` (`status`),
INDEX `idx_transaction_type` (`transaction_type`),
INDEX `idx_processed_at` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- USAGE TRACKING & LIMITS
CREATE TABLE IF NOT EXISTS `usage_tracking` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`usage_type` ENUM('prompt_creation', 'ai_generation', 'category_creation', 'bookmark_creation', 'note_creation', 'document_creation', 'video_creation') NOT NULL,
`usage_month` DATE NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
`usage_count` INT DEFAULT 0,
`last_reset_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
UNIQUE KEY `unique_user_usage_month` (`user_id`, `usage_type`, `usage_month`),
INDEX `idx_user_id` (`user_id`),
INDEX `idx_usage_type` (`usage_type`),
INDEX `idx_usage_month` (`usage_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- SECURITY & AUTHENTICATION
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`token` VARCHAR(255) NOT NULL,
`expires_at` TIMESTAMP NOT NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_token` (`token`),
INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- SETTINGS & CONFIGURATION
CREATE TABLE IF NOT EXISTS `app_settings` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`setting_key` VARCHAR(100) UNIQUE NOT NULL,
`setting_value` TEXT,
`setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
`description` TEXT,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_settings` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`setting_key` VARCHAR(100) NOT NULL,
`setting_value` TEXT,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
UNIQUE KEY `unique_user_setting` (`user_id`, `setting_key`),
INDEX `idx_user_id` (`user_id`),
INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- COLLABORATION & SHARING
CREATE TABLE IF NOT EXISTS `shared_prompts` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`prompt_id` INT NOT NULL,
`sharer_id` INT NOT NULL,
`recipient_id` INT,
`shared_with_all` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`prompt_id`) REFERENCES `prompts`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`sharer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_prompt_id` (`prompt_id`),
INDEX `idx_sharer_id` (`sharer_id`),
INDEX `idx_recipient_id` (`recipient_id`),
INDEX `idx_shared_with_all` (`shared_with_all`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_bookmarks` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`bookmark_id` INT NOT NULL,
`sharer_id` INT NOT NULL,
`recipient_id` INT,
`shared_with_all` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`bookmark_id`) REFERENCES `bookmarks`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`sharer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_bookmark_id` (`bookmark_id`),
INDEX `idx_sharer_id` (`sharer_id`),
INDEX `idx_recipient_id` (`recipient_id`),
INDEX `idx_shared_with_all` (`shared_with_all`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ** NEW ** SHARING TABLES
CREATE TABLE IF NOT EXISTS `shared_notes` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`note_id` INT NOT NULL,
`sharer_id` INT NOT NULL,
`recipient_id` INT,
`shared_with_all` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`sharer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_note_id` (`note_id`),
INDEX `idx_sharer_id` (`sharer_id`),
INDEX `idx_recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_documents` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`document_id` INT NOT NULL,
`sharer_id` INT NOT NULL,
`recipient_id` INT,
`shared_with_all` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`sharer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_document_id` (`document_id`),
INDEX `idx_sharer_id` (`sharer_id`),
INDEX `idx_recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_videos` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`video_id` INT NOT NULL,
`sharer_id` INT NOT NULL,
`recipient_id` INT,
`shared_with_all` BOOLEAN DEFAULT FALSE,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`sharer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_video_id` (`video_id`),
INDEX `idx_sharer_id` (`sharer_id`),
INDEX `idx_recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ** END NEW **

-- -- NOTIFICATION SYSTEM
CREATE TABLE IF NOT EXISTS `usage_notifications` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`notification_type` ENUM('warning_75', 'warning_90', 'limit_reached', 'limit_exceeded') NOT NULL,
`usage_type` ENUM('prompt_creation', 'ai_generation', 'category_creation', 'bookmark_creation', 'note_creation', 'document_creation', 'video_creation') NOT NULL,
`usage_month` DATE NOT NULL,
`sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`acknowledged_at` TIMESTAMP NULL,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
UNIQUE KEY `unique_notification` (`user_id`, `notification_type`, `usage_type`, `usage_month`),
INDEX `idx_user_id` (`user_id`),
INDEX `idx_usage_month` (`usage_month`),
INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_notifications` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`user_id` INT NOT NULL,
`subject` VARCHAR(255) NOT NULL,
`message` TEXT NOT NULL,
`type` VARCHAR(50) DEFAULT 'info',
`action_url` VARCHAR(255) NULL,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`read_at` TIMESTAMP NULL,
FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
INDEX `idx_user_id` (`user_id`),
INDEX `idx_created_at` (`created_at`),
INDEX `idx_read_at` (`read_at`),
INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ** NEW ** PASSKEY CREDENTIALS TABLE
CREATE TABLE IF NOT EXISTS `passkey_credentials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `credential_id` VARCHAR(255) NOT NULL UNIQUE,
  `public_key` TEXT NOT NULL,
  `attestation_object` TEXT,
  `sign_count` INT NOT NULL DEFAULT 0,
  `label` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ** END NEW **

-- -- DEFAULT DATA INSERTION
INSERT INTO `membership_tiers` (`id`, `name`, `display_name`, `description`, `price_annual`, `price_monthly`, `max_prompts_per_month`, `max_ai_generations_per_month`, `max_categories`, `max_bookmarks`, `max_notes`, `max_documents`, `max_videos`, `features`, `sort_order`) VALUES
(1, 'free', 'Free Plan', 'Perfect for getting started with prompt management', 0.00, 0.00, 50, 50, 5, 50, 50, 20, 30,
'[\"Basic prompt management\", \"Standard categories\", \"Community support\"]', 1),
(2, 'premium', 'Premium Plan', 'Unlock the full power of prompt management', 100.00, 10.00, 0, 300, 0, 0, 250, 150, 200,
'[\"Unlimited prompts\", \"300 AI generations/month\", \"Unlimited categories\", \"Advanced search\", \"Export functionality\", \"Priority support\", \"Advanced analytics\", \"Early access to features\"]', 2)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name', 'Promptash', 'string', 'Application name displayed in header and title'),
('app_version', '2.5.0', 'string', 'Current application version'),
('app_description', 'Professional prompt management made simple', 'string', 'Application description'),
('allow_registration', 'true', 'boolean', 'Allow new user registration'),
('max_prompts_per_user', '1000', 'number', 'Maximum prompts per user (0 = unlimited)'),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode to restrict access'),
('default_theme', 'light', 'string', 'Default theme for new users'),
('membership_enabled', 'true', 'boolean', 'Enable membership tiers and limits'),
('paystack_public_key', '', 'string', 'Paystack public key for payment processing'),
('paystack_secret_key', '', 'string', 'Paystack secret key for payment processing'),
('payment_currency', 'GHS', 'string', 'Default currency for payments'),
('trial_period_days', '0', 'number', 'Free trial period for premium users (0 = no trial)'),
('grace_period_days', '3', 'number', 'Grace period after subscription expires'),
('usage_reset_day', '1', 'number', 'Day of month to reset usage counters (1-28)'),
('openai_api_key', '', 'string', 'OpenAI API key for AI functionality'),
('selected_openai_model', 'gpt-3.5-turbo', 'string', 'Selected AI model for generations'),
('ai_enabled', 'false', 'boolean', 'Enable AI-powered features'),
('email_enabled', 'false', 'boolean', 'Enable email notifications system'),
('smtp_host', '', 'string', 'SMTP server hostname (e.g., smtp.gmail.com)'),
('smtp_port', '587', 'number', 'SMTP server port (587 for TLS, 465 for SSL)'),
('smtp_username', '', 'string', 'SMTP authentication username'),
('smtp_password', '', 'string', 'SMTP authentication password'),
('smtp_encryption', 'tls', 'string', 'SMTP encryption method (tls, ssl, none)'),
('smtp_from_email', '', 'string', 'From email address for notifications'),
('smtp_from_name', 'Promptash', 'string', 'From name for email notifications')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

COMMIT;

-- Restore previous settings
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
