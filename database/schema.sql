-- Modern Link Shortener Database Schema
-- Compatible with MySQL 8.0+ and MariaDB 10.4+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `linkshortener` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `linkshortener`;

-- Links table (improved from original link_mapping)
CREATE TABLE `links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_url` text NOT NULL,
  `short_code` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` varchar(255) NOT NULL,
  `click_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_code` (`short_code`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_original_url_hash` ((CAST(SHA2(original_url, 256) AS CHAR(64))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Click analytics table
CREATE TABLE `click_analytics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_id` bigint(20) UNSIGNED NOT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `referer` text,
  `country` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_link_id` (`link_id`),
  KEY `idx_clicked_at` (`clicked_at`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_country` (`country`),
  CONSTRAINT `fk_click_analytics_link` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (for future authentication)
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `api_key` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domains table (for domain-based analytics and blocking)
CREATE TABLE `domains` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `block_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `idx_is_blocked` (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting table
CREATE TABLE `rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `requests` int(11) NOT NULL DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier_action` (`identifier`, `action`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security events table
CREATE TABLE `security_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `details` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('short_code_length', '6'),
('short_code_alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
('default_expiration_days', '0'),
('max_links_per_user', '1000'),
('enable_analytics', '1'),
('enable_custom_codes', '1'),
('maintenance_mode', '0');

-- Create indexes for better performance
CREATE INDEX `idx_links_created_by_active` ON `links` (`created_by`, `is_active`);
CREATE INDEX `idx_links_expires_active` ON `links` (`expires_at`, `is_active`);
CREATE INDEX `idx_click_analytics_link_date` ON `click_analytics` (`link_id`, `clicked_at`);

-- Create views for common queries
CREATE VIEW `active_links` AS
SELECT 
    l.*,
    CASE 
        WHEN l.expires_at IS NULL THEN 0
        WHEN l.expires_at > NOW() THEN 0
        ELSE 1
    END as is_expired
FROM `links` l
WHERE l.is_active = 1;

CREATE VIEW `link_stats` AS
SELECT 
    l.id,
    l.short_code,
    l.original_url,
    l.created_by,
    l.created_at,
    l.click_count,
    COUNT(ca.id) as detailed_clicks,
    MAX(ca.clicked_at) as last_clicked,
    COUNT(DISTINCT ca.ip_address) as unique_visitors,
    COUNT(DISTINCT ca.country) as countries_count
FROM `links` l
LEFT JOIN `click_analytics` ca ON l.id = ca.link_id
GROUP BY l.id;

-- Create stored procedures for common operations
DELIMITER $$

CREATE PROCEDURE `CleanupExpiredLinks`()
BEGIN
    UPDATE `links` 
    SET `is_active` = 0 
    WHERE `expires_at` < NOW() AND `is_active` = 1;
    
    SELECT ROW_COUNT() as affected_rows;
END$$

CREATE PROCEDURE `GetLinkAnalytics`(IN link_id BIGINT)
BEGIN
    SELECT 
        DATE(clicked_at) as date,
        COUNT(*) as clicks,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM `click_analytics`
    WHERE `link_id` = link_id
    GROUP BY DATE(clicked_at)
    ORDER BY date DESC
    LIMIT 30;
END$$

CREATE PROCEDURE `GetTopDomains`(IN limit_count INT)
BEGIN
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain')) as domain,
        COUNT(*) as link_count,
        SUM(click_count) as total_clicks
    FROM `links`
    WHERE is_active = 1
    GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain'))
    ORDER BY total_clicks DESC
    LIMIT limit_count;
END$$

DELIMITER ;

-- Create triggers for automatic updates
DELIMITER $$

CREATE TRIGGER `update_click_count` 
AFTER INSERT ON `click_analytics`
FOR EACH ROW
BEGIN
    UPDATE `links` 
    SET `click_count` = `click_count` + 1 
    WHERE `id` = NEW.link_id;
END$$

CREATE TRIGGER `log_link_creation`
AFTER INSERT ON `links`
FOR EACH ROW
BEGIN
    INSERT INTO `security_events` (event_type, ip_address, severity, details)
    VALUES ('link_created', 
            JSON_UNQUOTE(JSON_EXTRACT(NEW.metadata, '$.created_ip')), 
            'low',
            JSON_OBJECT('short_code', NEW.short_code, 'link_id', NEW.id));
END$$

DELIMITER ;

COMMIT;

-- Performance optimization commands
ANALYZE TABLE `links`;
ANALYZE TABLE `click_analytics`;
ANALYZE TABLE `users`;

-- Example data (optional - remove in production)
INSERT INTO `links` (`original_url`, `short_code`, `created_by`, `metadata`) VALUES
('https://www.google.com', 'goog1e', '127.0.0.1', '{"domain": "www.google.com", "created_ip": "127.0.0.1", "user_agent": "Mozilla/5.0"}'),
('https://www.github.com', 'github', '127.0.0.1', '{"domain": "www.github.com", "created_ip": "127.0.0.1", "user_agent": "Mozilla/5.0"}'),
('https://stackoverflow.com', 'stack1', '127.0.0.1', '{"domain": "stackoverflow.com", "created_ip": "127.0.0.1", "user_agent": "Mozilla/5.0"}');

-- Grant permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON linkshortener.* TO 'linkshortener_user'@'localhost';
-- FLUSH PRIVILEGES;