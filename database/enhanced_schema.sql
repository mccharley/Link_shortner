-- Enhanced Link Shortener Database Schema with Partner API and Revenue Sharing
-- Compatible with MySQL 8.0+ and MariaDB 10.4+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `linkshortener_api` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `linkshortener_api`;

-- ============================================================================
-- PARTNER MANAGEMENT TABLES
-- ============================================================================

-- Partners/Users table with comprehensive authentication fields
CREATE TABLE `partners` (
    `id` VARCHAR(50) PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `email_verified` BOOLEAN DEFAULT FALSE,
    `phone_verified` BOOLEAN DEFAULT FALSE,
    `api_key` VARCHAR(100) UNIQUE NOT NULL,
    `api_secret` VARCHAR(100) NOT NULL,
    `revenue_share` DECIMAL(3,2) DEFAULT 0.50,
    `plan_id` VARCHAR(50) DEFAULT 'free',
    `status` ENUM('pending', 'active', 'suspended', 'closed') DEFAULT 'pending',
    `mfa_enabled` BOOLEAN DEFAULT FALSE,
    `mfa_secret` VARCHAR(255),
    `last_login` TIMESTAMP NULL,
    `login_attempts` INTEGER DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `stripe_customer_id` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_api_key` (`api_key`),
    INDEX `idx_plan_id` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partner profiles for additional information
CREATE TABLE `partner_profiles` (
    `partner_id` VARCHAR(50) PRIMARY KEY,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `company_website` VARCHAR(255),
    `company_description` TEXT,
    `billing_address` JSON,
    `tax_id` VARCHAR(50),
    `business_type` ENUM('individual', 'company', 'organization'),
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `notification_preferences` JSON,
    `kyc_status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    `kyc_documents` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session management
CREATE TABLE `partner_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_partner_expires` (`partner_id`, `expires_at`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE `password_reset_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_partner_expires` (`partner_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email verification tokens
CREATE TABLE `email_verification_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SUBSCRIPTION AND BILLING TABLES
-- ============================================================================

-- Subscription plans
CREATE TABLE `subscription_plans` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `price_monthly` DECIMAL(10,2) DEFAULT 0.00,
    `price_yearly` DECIMAL(10,2) DEFAULT 0.00,
    `api_requests_limit` INTEGER DEFAULT 1000,
    `features` JSON,
    `active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partner subscriptions
CREATE TABLE `partner_subscriptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `plan_id` VARCHAR(50) NOT NULL,
    `status` ENUM('active', 'cancelled', 'expired', 'past_due') DEFAULT 'active',
    `current_period_start` TIMESTAMP NOT NULL,
    `current_period_end` TIMESTAMP NOT NULL,
    `cancel_at_period_end` BOOLEAN DEFAULT FALSE,
    `stripe_subscription_id` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans`(`id`),
    INDEX `idx_partner_status` (`partner_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- API MANAGEMENT TABLES
-- ============================================================================

-- API usage tracking for rate limiting
CREATE TABLE `api_usage` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `endpoint` VARCHAR(100) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `requests_count` INTEGER DEFAULT 1,
    `date_hour` TIMESTAMP NOT NULL, -- Rounded to hour for aggregation
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_usage` (`partner_id`, `endpoint`, `method`, `date_hour`),
    INDEX `idx_partner_date` (`partner_id`, `date_hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API tokens for OAuth-like authentication
CREATE TABLE `api_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `refresh_token` VARCHAR(255) UNIQUE,
    `token_type` ENUM('access', 'refresh') DEFAULT 'access',
    `expires_at` TIMESTAMP NOT NULL,
    `scopes` JSON,
    `revoked` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_partner_expires` (`partner_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ENHANCED LINKS TABLES
-- ============================================================================

-- Enhanced links table with partner association
CREATE TABLE `shortened_urls` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `short_code` VARCHAR(10) UNIQUE NOT NULL,
    `original_url` TEXT NOT NULL,
    `partner_id` VARCHAR(50),
    `custom_alias` VARCHAR(50),
    `title` VARCHAR(255),
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `click_count` BIGINT UNSIGNED DEFAULT 0,
    `status` ENUM('active', 'expired', 'disabled') DEFAULT 'active',
    `metadata` JSON,
    `created_by_ip` VARCHAR(45),
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL,
    INDEX `idx_short_code` (`short_code`),
    INDEX `idx_partner_id` (`partner_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADVERTISEMENT SYSTEM TABLES
-- ============================================================================

-- Advertisement campaigns
CREATE TABLE `advertisements` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT,
    `image_url` VARCHAR(500),
    `video_url` VARCHAR(500),
    `click_url` VARCHAR(500),
    `duration_seconds` INTEGER DEFAULT 15,
    `active` BOOLEAN DEFAULT TRUE,
    `start_date` TIMESTAMP NULL,
    `end_date` TIMESTAMP NULL,
    `target_countries` JSON,
    `target_devices` JSON,
    `cpm_rate` DECIMAL(10,4) DEFAULT 0.0000,
    `daily_budget` DECIMAL(10,2),
    `total_budget` DECIMAL(10,2),
    `impressions_count` BIGINT UNSIGNED DEFAULT 0,
    `clicks_count` BIGINT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active_dates` (`active`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ANALYTICS AND TRACKING TABLES
-- ============================================================================

-- Enhanced click analytics with revenue tracking
CREATE TABLE `click_analytics` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `short_code` VARCHAR(10) NOT NULL,
    `url_id` BIGINT UNSIGNED,
    `partner_id` VARCHAR(50),
    `original_url` TEXT,
    `visitor_ip` VARCHAR(45),
    `user_agent` TEXT,
    `referer` TEXT,
    `country` VARCHAR(2),
    `region` VARCHAR(100),
    `city` VARCHAR(100),
    `device_type` VARCHAR(50),
    `browser` VARCHAR(50),
    `os` VARCHAR(50),
    `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ad_displayed` BOOLEAN DEFAULT FALSE,
    `ad_id` BIGINT UNSIGNED,
    `ad_duration` INTEGER,
    `ad_clicked` BOOLEAN DEFAULT FALSE,
    `conversion` BOOLEAN DEFAULT FALSE,
    `revenue_generated` DECIMAL(10,4) DEFAULT 0.0000,
    FOREIGN KEY (`url_id`) REFERENCES `shortened_urls`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE SET NULL,
    INDEX `idx_short_code` (`short_code`),
    INDEX `idx_partner_clicked` (`partner_id`, `clicked_at`),
    INDEX `idx_clicked_at` (`clicked_at`),
    INDEX `idx_country` (`country`),
    INDEX `idx_ad_id` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revenue tracking and partner payouts
CREATE TABLE `revenue_records` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `partner_id` VARCHAR(50) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `total_clicks` INTEGER DEFAULT 0,
    `total_impressions` INTEGER DEFAULT 0,
    `gross_revenue` DECIMAL(10,4) DEFAULT 0.0000,
    `partner_revenue` DECIMAL(10,4) DEFAULT 0.0000,
    `service_revenue` DECIMAL(10,4) DEFAULT 0.0000,
    `status` ENUM('pending', 'paid', 'disputed') DEFAULT 'pending',
    `payout_date` TIMESTAMP NULL,
    `payout_reference` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE CASCADE,
    INDEX `idx_partner_period` (`partner_id`, `period_start`, `period_end`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECURITY AND AUDIT TABLES
-- ============================================================================

-- Security events and audit log
CREATE TABLE `security_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,
    `partner_id` VARCHAR(50),
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    `details` JSON,
    `resolved` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_partner_id` (`partner_id`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting tracking
CREATE TABLE `rate_limits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(255) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `requests` INTEGER DEFAULT 1,
    `window_start` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    UNIQUE KEY `identifier_action` (`identifier`, `action`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SYSTEM CONFIGURATION
-- ============================================================================

-- System settings
CREATE TABLE `system_settings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT DATA AND CONFIGURATION
-- ============================================================================

-- Insert default subscription plans
INSERT INTO `subscription_plans` (`id`, `name`, `description`, `price_monthly`, `price_yearly`, `api_requests_limit`, `features`) VALUES
('free', 'Free Plan', 'Basic plan with limited features', 0.00, 0.00, 1000, '{"analytics": false, "custom_domains": false, "api_access": true, "support": "community"}'),
('starter', 'Starter Plan', 'Perfect for small businesses', 29.99, 299.99, 10000, '{"analytics": true, "custom_domains": false, "api_access": true, "support": "email"}'),
('professional', 'Professional Plan', 'Advanced features for growing companies', 99.99, 999.99, 100000, '{"analytics": true, "custom_domains": true, "api_access": true, "support": "priority"}'),
('enterprise', 'Enterprise Plan', 'Full-featured plan for large organizations', 299.99, 2999.99, 1000000, '{"analytics": true, "custom_domains": true, "api_access": true, "support": "dedicated", "white_label": true}');

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('short_code_length', '6'),
('short_code_alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
('default_expiration_days', '0'),
('enable_analytics', '1'),
('enable_custom_codes', '1'),
('maintenance_mode', '0'),
('default_ad_duration', '15'),
('max_ad_duration', '30'),
('min_ad_duration', '5'),
('revenue_share_default', '0.50'),
('api_rate_limit_default', '1000'),
('session_timeout_hours', '24');

-- Insert sample advertisement
INSERT INTO `advertisements` (`title`, `content`, `duration_seconds`, `active`, `cpm_rate`) VALUES
('Welcome Advertisement', 'Thank you for using our link shortener service!', 15, TRUE, 2.5000);

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Active partners view
CREATE VIEW `active_partners` AS
SELECT 
    p.*,
    pp.first_name,
    pp.last_name,
    pp.company_website,
    sp.name as plan_name,
    sp.api_requests_limit
FROM `partners` p
LEFT JOIN `partner_profiles` pp ON p.id = pp.partner_id
LEFT JOIN `subscription_plans` sp ON p.plan_id = sp.id
WHERE p.status = 'active';

-- Link statistics view
CREATE VIEW `link_stats` AS
SELECT 
    su.id,
    su.short_code,
    su.original_url,
    su.partner_id,
    su.created_at,
    su.click_count,
    COUNT(ca.id) as detailed_clicks,
    MAX(ca.clicked_at) as last_clicked,
    COUNT(DISTINCT ca.visitor_ip) as unique_visitors,
    COUNT(DISTINCT ca.country) as countries_count,
    SUM(ca.revenue_generated) as total_revenue
FROM `shortened_urls` su
LEFT JOIN `click_analytics` ca ON su.id = ca.url_id
GROUP BY su.id;

-- Partner revenue summary view
CREATE VIEW `partner_revenue_summary` AS
SELECT 
    p.id as partner_id,
    p.company_name,
    COUNT(DISTINCT su.id) as total_links,
    SUM(su.click_count) as total_clicks,
    SUM(ca.revenue_generated) as total_revenue,
    SUM(ca.revenue_generated * p.revenue_share) as partner_earnings
FROM `partners` p
LEFT JOIN `shortened_urls` su ON p.id = su.partner_id
LEFT JOIN `click_analytics` ca ON su.id = ca.url_id
GROUP BY p.id;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Calculate partner revenue for a specific period
CREATE PROCEDURE `CalculatePartnerRevenue`(
    IN partner_id VARCHAR(50),
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    DECLARE total_clicks INT DEFAULT 0;
    DECLARE total_impressions INT DEFAULT 0;
    DECLARE gross_revenue DECIMAL(10,4) DEFAULT 0.0000;
    DECLARE partner_share DECIMAL(3,2) DEFAULT 0.50;
    DECLARE partner_revenue DECIMAL(10,4) DEFAULT 0.0000;
    DECLARE service_revenue DECIMAL(10,4) DEFAULT 0.0000;
    
    -- Get partner's revenue share
    SELECT revenue_share INTO partner_share 
    FROM partners 
    WHERE id = partner_id;
    
    -- Calculate metrics
    SELECT 
        COUNT(*) as clicks,
        COUNT(DISTINCT CASE WHEN ad_displayed = TRUE THEN id END) as impressions,
        SUM(revenue_generated) as gross_rev
    INTO total_clicks, total_impressions, gross_revenue
    FROM click_analytics 
    WHERE partner_id = partner_id 
    AND DATE(clicked_at) BETWEEN start_date AND end_date;
    
    SET partner_revenue = gross_revenue * partner_share;
    SET service_revenue = gross_revenue - partner_revenue;
    
    -- Insert or update revenue record
    INSERT INTO revenue_records 
    (partner_id, period_start, period_end, total_clicks, total_impressions, 
     gross_revenue, partner_revenue, service_revenue, status)
    VALUES 
    (partner_id, start_date, end_date, total_clicks, total_impressions,
     gross_revenue, partner_revenue, service_revenue, 'pending')
    ON DUPLICATE KEY UPDATE
    total_clicks = VALUES(total_clicks),
    total_impressions = VALUES(total_impressions),
    gross_revenue = VALUES(gross_revenue),
    partner_revenue = VALUES(partner_revenue),
    service_revenue = VALUES(service_revenue),
    updated_at = CURRENT_TIMESTAMP;
    
    SELECT partner_id, total_clicks, total_impressions, gross_revenue, 
           partner_revenue, service_revenue;
END$$

-- Generate unique short code with partner identification
CREATE PROCEDURE `GenerateShortCode`(
    IN partner_id VARCHAR(50),
    OUT short_code VARCHAR(10)
)
BEGIN
    DECLARE partner_hash INT DEFAULT 0;
    DECLARE url_id BIGINT DEFAULT 0;
    DECLARE code_exists INT DEFAULT 1;
    DECLARE attempt_count INT DEFAULT 0;
    DECLARE max_attempts INT DEFAULT 10;
    
    -- Generate partner hash (0-999)
    SET partner_hash = CRC32(partner_id) % 1000;
    
    WHILE code_exists = 1 AND attempt_count < max_attempts DO
        -- Generate random URL ID
        SET url_id = FLOOR(RAND() * 1000000);
        
        -- Encode with partner identification
        SET short_code = CONV((url_id * 1000) + partner_hash, 10, 36);
        
        -- Pad to 6 characters
        WHILE LENGTH(short_code) < 6 DO
            SET short_code = CONCAT('0', short_code);
        END WHILE;
        
        -- Check if code exists
        SELECT COUNT(*) INTO code_exists 
        FROM shortened_urls 
        WHERE short_code = short_code;
        
        SET attempt_count = attempt_count + 1;
    END WHILE;
    
    IF code_exists = 1 THEN
        SET short_code = NULL;
    END IF;
END$$

-- Clean up expired data
CREATE PROCEDURE `CleanupExpiredData`()
BEGIN
    -- Disable expired URLs
    UPDATE shortened_urls 
    SET status = 'expired' 
    WHERE expires_at < NOW() AND status = 'active';
    
    -- Clean expired sessions
    DELETE FROM partner_sessions 
    WHERE expires_at < NOW();
    
    -- Clean expired tokens
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW();
    
    DELETE FROM email_verification_tokens 
    WHERE expires_at < NOW();
    
    DELETE FROM api_tokens 
    WHERE expires_at < NOW() AND revoked = FALSE;
    
    -- Clean old rate limit records
    DELETE FROM rate_limits 
    WHERE expires_at < NOW();
    
    -- Archive old click analytics (older than 2 years)
    DELETE FROM click_analytics 
    WHERE clicked_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
    
    SELECT ROW_COUNT() as cleaned_records;
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER $$

-- Update click count when analytics record is inserted
CREATE TRIGGER `update_click_count` 
AFTER INSERT ON `click_analytics`
FOR EACH ROW
BEGIN
    UPDATE shortened_urls 
    SET click_count = click_count + 1 
    WHERE id = NEW.url_id;
    
    -- Update advertisement impression/click counts
    IF NEW.ad_id IS NOT NULL THEN
        UPDATE advertisements 
        SET impressions_count = impressions_count + 1,
            clicks_count = clicks_count + IF(NEW.ad_clicked = TRUE, 1, 0)
        WHERE id = NEW.ad_id;
    END IF;
END$$

-- Log security events for sensitive operations
CREATE TRIGGER `log_partner_creation`
AFTER INSERT ON `partners`
FOR EACH ROW
BEGIN
    INSERT INTO security_events (event_type, partner_id, ip_address, severity, details)
    VALUES ('partner_registered', NEW.id, '0.0.0.0', 'low',
            JSON_OBJECT('company_name', NEW.company_name, 'email', NEW.email));
END$$

CREATE TRIGGER `log_partner_login`
AFTER UPDATE ON `partners`
FOR EACH ROW
BEGIN
    IF NEW.last_login != OLD.last_login AND NEW.last_login IS NOT NULL THEN
        INSERT INTO security_events (event_type, partner_id, ip_address, severity, details)
        VALUES ('partner_login', NEW.id, '0.0.0.0', 'low',
                JSON_OBJECT('login_time', NEW.last_login));
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Additional composite indexes for common queries
CREATE INDEX `idx_partners_status_plan` ON `partners` (`status`, `plan_id`);
CREATE INDEX `idx_click_analytics_partner_date` ON `click_analytics` (`partner_id`, `clicked_at`);
CREATE INDEX `idx_shortened_urls_partner_status` ON `shortened_urls` (`partner_id`, `status`);
CREATE INDEX `idx_revenue_records_partner_period` ON `revenue_records` (`partner_id`, `period_start`, `period_end`);

COMMIT;

-- Performance optimization
ANALYZE TABLE `partners`;
ANALYZE TABLE `shortened_urls`;
ANALYZE TABLE `click_analytics`;
ANALYZE TABLE `revenue_records`;
ANALYZE TABLE `advertisements`;