<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use LinkShortener\Config\Cache;
use PDO;
use DateTime;

class SystemConfigService
{
    private const CACHE_PREFIX = 'config:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private PDO $database,
        private Cache $cache
    ) {}

    // ============================================================================
    // GENERAL SYSTEM CONFIGURATION
    // ============================================================================

    public function getAllSystemConfig(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'all_system';
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->database->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            ORDER BY setting_key
        ");
        $stmt->execute();
        
        $config = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $config[$row['setting_key']] = $this->parseSettingValue($row['setting_value']);
        }

        $this->cache->set($cacheKey, $config, self::CACHE_TTL);
        return $config;
    }

    public function updateSystemConfig(array $settings): void
    {
        $this->database->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                $this->setSetting($key, $value);
            }
            
            $this->database->commit();
            $this->clearConfigCache();
            
            // Log configuration change
            $this->logConfigChange('system_config_update', $settings);
            
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->database->prepare("
            SELECT setting_value FROM system_settings WHERE setting_key = ?
        ");
        $stmt->execute([$key]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $value = $result ? $this->parseSettingValue($result['setting_value']) : $default;
        
        $this->cache->set($cacheKey, $value, self::CACHE_TTL);
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setSetting($key, $value);
        $this->cache->delete(self::CACHE_PREFIX . $key);
        $this->cache->delete(self::CACHE_PREFIX . 'all_system');
    }

    // ============================================================================
    // ADVERTISEMENT CONFIGURATION
    // ============================================================================

    public function getAdConfig(): array
    {
        return [
            'default_duration' => $this->get('default_ad_duration', 15),
            'min_duration' => $this->get('min_ad_duration', 5),
            'max_duration' => $this->get('max_ad_duration', 30),
            'default_cpm' => $this->get('default_ad_cpm', 2.5000),
            'skip_enabled' => $this->get('ad_skip_enabled', false),
            'skip_delay' => $this->get('ad_skip_delay', 5),
            'auto_play' => $this->get('ad_auto_play', true),
            'sound_enabled' => $this->get('ad_sound_enabled', false),
            'frequency_cap' => $this->get('ad_frequency_cap', 3),
            'frequency_window' => $this->get('ad_frequency_window', 24),
            'targeting_enabled' => $this->get('ad_targeting_enabled', true),
            'geo_targeting' => $this->get('ad_geo_targeting', true),
            'device_targeting' => $this->get('ad_device_targeting', true),
            'time_targeting' => $this->get('ad_time_targeting', false),
            'budget_enforcement' => $this->get('ad_budget_enforcement', true),
            'quality_score_enabled' => $this->get('ad_quality_score_enabled', true),
            'minimum_quality_score' => $this->get('ad_minimum_quality_score', 3.0),
            'rotation_algorithm' => $this->get('ad_rotation_algorithm', 'weighted_random'),
            'click_fraud_protection' => $this->get('ad_click_fraud_protection', true),
            'impression_tracking' => $this->get('ad_impression_tracking', true),
            'viewability_threshold' => $this->get('ad_viewability_threshold', 50),
            'cache_duration' => $this->get('ad_cache_duration', 300)
        ];
    }

    public function updateAdConfig(array $config): void
    {
        $adSettings = [
            'default_ad_duration' => $config['default_duration'] ?? null,
            'min_ad_duration' => $config['min_duration'] ?? null,
            'max_ad_duration' => $config['max_duration'] ?? null,
            'default_ad_cpm' => $config['default_cpm'] ?? null,
            'ad_skip_enabled' => $config['skip_enabled'] ?? null,
            'ad_skip_delay' => $config['skip_delay'] ?? null,
            'ad_auto_play' => $config['auto_play'] ?? null,
            'ad_sound_enabled' => $config['sound_enabled'] ?? null,
            'ad_frequency_cap' => $config['frequency_cap'] ?? null,
            'ad_frequency_window' => $config['frequency_window'] ?? null,
            'ad_targeting_enabled' => $config['targeting_enabled'] ?? null,
            'ad_geo_targeting' => $config['geo_targeting'] ?? null,
            'ad_device_targeting' => $config['device_targeting'] ?? null,
            'ad_time_targeting' => $config['time_targeting'] ?? null,
            'ad_budget_enforcement' => $config['budget_enforcement'] ?? null,
            'ad_quality_score_enabled' => $config['quality_score_enabled'] ?? null,
            'ad_minimum_quality_score' => $config['minimum_quality_score'] ?? null,
            'ad_rotation_algorithm' => $config['rotation_algorithm'] ?? null,
            'ad_click_fraud_protection' => $config['click_fraud_protection'] ?? null,
            'ad_impression_tracking' => $config['impression_tracking'] ?? null,
            'ad_viewability_threshold' => $config['viewability_threshold'] ?? null,
            'ad_cache_duration' => $config['cache_duration'] ?? null
        ];

        // Filter out null values
        $adSettings = array_filter($adSettings, fn($value) => $value !== null);

        $this->updateSystemConfig($adSettings);
        $this->logConfigChange('ad_config_update', $config);
    }

    // ============================================================================
    // REVENUE CONFIGURATION
    // ============================================================================

    public function getRevenueConfig(): array
    {
        return [
            'default_revenue_share' => $this->get('default_revenue_share', 0.50),
            'min_revenue_share' => $this->get('min_revenue_share', 0.10),
            'max_revenue_share' => $this->get('max_revenue_share', 0.90),
            'min_payout_amount' => $this->get('min_payout_amount', 50.00),
            'payout_frequency' => $this->get('payout_frequency', 'monthly'),
            'payout_delay_days' => $this->get('payout_delay_days', 7),
            'currency' => $this->get('revenue_currency', 'USD'),
            'tax_handling' => $this->get('revenue_tax_handling', 'partner_responsible'),
            'commission_calculation' => $this->get('commission_calculation', 'net_revenue'),
            'minimum_clicks_for_payout' => $this->get('min_clicks_for_payout', 100),
            'fraud_detection_enabled' => $this->get('revenue_fraud_detection', true),
            'revenue_reconciliation' => $this->get('revenue_reconciliation', 'daily'),
            'partner_dashboard_enabled' => $this->get('partner_dashboard_enabled', true),
            'real_time_reporting' => $this->get('real_time_reporting', true),
            'revenue_forecasting' => $this->get('revenue_forecasting', false),
            'bonus_programs_enabled' => $this->get('bonus_programs_enabled', false),
            'performance_bonuses' => $this->get('performance_bonuses', []),
            'referral_program' => $this->get('referral_program_enabled', false),
            'referral_commission' => $this->get('referral_commission', 0.05)
        ];
    }

    public function updateRevenueConfig(array $config): void
    {
        $revenueSettings = [
            'default_revenue_share' => $config['default_revenue_share'] ?? null,
            'min_revenue_share' => $config['min_revenue_share'] ?? null,
            'max_revenue_share' => $config['max_revenue_share'] ?? null,
            'min_payout_amount' => $config['min_payout_amount'] ?? null,
            'payout_frequency' => $config['payout_frequency'] ?? null,
            'payout_delay_days' => $config['payout_delay_days'] ?? null,
            'revenue_currency' => $config['currency'] ?? null,
            'revenue_tax_handling' => $config['tax_handling'] ?? null,
            'commission_calculation' => $config['commission_calculation'] ?? null,
            'min_clicks_for_payout' => $config['minimum_clicks_for_payout'] ?? null,
            'revenue_fraud_detection' => $config['fraud_detection_enabled'] ?? null,
            'revenue_reconciliation' => $config['revenue_reconciliation'] ?? null,
            'partner_dashboard_enabled' => $config['partner_dashboard_enabled'] ?? null,
            'real_time_reporting' => $config['real_time_reporting'] ?? null,
            'revenue_forecasting' => $config['revenue_forecasting'] ?? null,
            'bonus_programs_enabled' => $config['bonus_programs_enabled'] ?? null,
            'performance_bonuses' => $config['performance_bonuses'] ?? null,
            'referral_program_enabled' => $config['referral_program'] ?? null,
            'referral_commission' => $config['referral_commission'] ?? null
        ];

        // Filter out null values
        $revenueSettings = array_filter($revenueSettings, fn($value) => $value !== null);

        $this->updateSystemConfig($revenueSettings);
        $this->logConfigChange('revenue_config_update', $config);
    }

    // ============================================================================
    // SECURITY CONFIGURATION
    // ============================================================================

    public function getSecurityConfig(): array
    {
        return [
            'api_rate_limit_enabled' => $this->get('api_rate_limit_enabled', true),
            'api_rate_limit_requests' => $this->get('api_rate_limit_requests', 1000),
            'api_rate_limit_window' => $this->get('api_rate_limit_window', 3600),
            'partner_registration_enabled' => $this->get('partner_registration_enabled', true),
            'email_verification_required' => $this->get('email_verification_required', true),
            'kyc_verification_required' => $this->get('kyc_verification_required', false),
            'mfa_enforcement' => $this->get('mfa_enforcement', 'optional'),
            'password_min_length' => $this->get('password_min_length', 8),
            'password_require_uppercase' => $this->get('password_require_uppercase', true),
            'password_require_lowercase' => $this->get('password_require_lowercase', true),
            'password_require_numbers' => $this->get('password_require_numbers', true),
            'password_require_symbols' => $this->get('password_require_symbols', true),
            'password_expiry_days' => $this->get('password_expiry_days', 0),
            'session_timeout_minutes' => $this->get('session_timeout_minutes', 1440),
            'max_login_attempts' => $this->get('max_login_attempts', 5),
            'lockout_duration_minutes' => $this->get('lockout_duration_minutes', 30),
            'ip_whitelist_enabled' => $this->get('ip_whitelist_enabled', false),
            'ip_whitelist' => $this->get('ip_whitelist', []),
            'geo_blocking_enabled' => $this->get('geo_blocking_enabled', false),
            'blocked_countries' => $this->get('blocked_countries', []),
            'suspicious_activity_detection' => $this->get('suspicious_activity_detection', true),
            'audit_logging_enabled' => $this->get('audit_logging_enabled', true),
            'audit_log_retention_days' => $this->get('audit_log_retention_days', 365),
            'security_headers_enabled' => $this->get('security_headers_enabled', true),
            'cors_enabled' => $this->get('cors_enabled', true),
            'cors_allowed_origins' => $this->get('cors_allowed_origins', ['*']),
            'webhook_signature_verification' => $this->get('webhook_signature_verification', true),
            'api_key_rotation_enabled' => $this->get('api_key_rotation_enabled', false),
            'api_key_rotation_days' => $this->get('api_key_rotation_days', 90)
        ];
    }

    public function updateSecurityConfig(array $config): void
    {
        $securitySettings = [
            'api_rate_limit_enabled' => $config['api_rate_limit_enabled'] ?? null,
            'api_rate_limit_requests' => $config['api_rate_limit_requests'] ?? null,
            'api_rate_limit_window' => $config['api_rate_limit_window'] ?? null,
            'partner_registration_enabled' => $config['partner_registration_enabled'] ?? null,
            'email_verification_required' => $config['email_verification_required'] ?? null,
            'kyc_verification_required' => $config['kyc_verification_required'] ?? null,
            'mfa_enforcement' => $config['mfa_enforcement'] ?? null,
            'password_min_length' => $config['password_min_length'] ?? null,
            'password_require_uppercase' => $config['password_require_uppercase'] ?? null,
            'password_require_lowercase' => $config['password_require_lowercase'] ?? null,
            'password_require_numbers' => $config['password_require_numbers'] ?? null,
            'password_require_symbols' => $config['password_require_symbols'] ?? null,
            'password_expiry_days' => $config['password_expiry_days'] ?? null,
            'session_timeout_minutes' => $config['session_timeout_minutes'] ?? null,
            'max_login_attempts' => $config['max_login_attempts'] ?? null,
            'lockout_duration_minutes' => $config['lockout_duration_minutes'] ?? null,
            'ip_whitelist_enabled' => $config['ip_whitelist_enabled'] ?? null,
            'ip_whitelist' => $config['ip_whitelist'] ?? null,
            'geo_blocking_enabled' => $config['geo_blocking_enabled'] ?? null,
            'blocked_countries' => $config['blocked_countries'] ?? null,
            'suspicious_activity_detection' => $config['suspicious_activity_detection'] ?? null,
            'audit_logging_enabled' => $config['audit_logging_enabled'] ?? null,
            'audit_log_retention_days' => $config['audit_log_retention_days'] ?? null,
            'security_headers_enabled' => $config['security_headers_enabled'] ?? null,
            'cors_enabled' => $config['cors_enabled'] ?? null,
            'cors_allowed_origins' => $config['cors_allowed_origins'] ?? null,
            'webhook_signature_verification' => $config['webhook_signature_verification'] ?? null,
            'api_key_rotation_enabled' => $config['api_key_rotation_enabled'] ?? null,
            'api_key_rotation_days' => $config['api_key_rotation_days'] ?? null
        ];

        // Filter out null values
        $securitySettings = array_filter($securitySettings, fn($value) => $value !== null);

        $this->updateSystemConfig($securitySettings);
        $this->logConfigChange('security_config_update', $config);
    }

    // ============================================================================
    // LINK SHORTENING CONFIGURATION
    // ============================================================================

    public function getLinkConfig(): array
    {
        return [
            'short_code_length' => $this->get('short_code_length', 6),
            'short_code_alphabet' => $this->get('short_code_alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
            'custom_aliases_enabled' => $this->get('custom_aliases_enabled', true),
            'link_expiration_enabled' => $this->get('link_expiration_enabled', true),
            'default_expiration_days' => $this->get('default_expiration_days', 0),
            'max_expiration_days' => $this->get('max_expiration_days', 365),
            'bulk_creation_enabled' => $this->get('bulk_creation_enabled', true),
            'bulk_creation_limit' => $this->get('bulk_creation_limit', 1000),
            'qr_code_generation' => $this->get('qr_code_generation', true),
            'preview_enabled' => $this->get('link_preview_enabled', true),
            'click_tracking_enabled' => $this->get('click_tracking_enabled', true),
            'analytics_enabled' => $this->get('analytics_enabled', true),
            'geo_analytics_enabled' => $this->get('geo_analytics_enabled', true),
            'device_analytics_enabled' => $this->get('device_analytics_enabled', true),
            'referrer_tracking' => $this->get('referrer_tracking', true),
            'utm_parameter_tracking' => $this->get('utm_parameter_tracking', true),
            'link_validation_enabled' => $this->get('link_validation_enabled', true),
            'malware_scanning_enabled' => $this->get('malware_scanning_enabled', false),
            'adult_content_detection' => $this->get('adult_content_detection', false),
            'spam_detection_enabled' => $this->get('spam_detection_enabled', true),
            'domain_blacklist_enabled' => $this->get('domain_blacklist_enabled', true),
            'partner_branding_enabled' => $this->get('partner_branding_enabled', false),
            'custom_domains_enabled' => $this->get('custom_domains_enabled', false)
        ];
    }

    public function updateLinkConfig(array $config): void
    {
        $linkSettings = array_filter([
            'short_code_length' => $config['short_code_length'] ?? null,
            'short_code_alphabet' => $config['short_code_alphabet'] ?? null,
            'custom_aliases_enabled' => $config['custom_aliases_enabled'] ?? null,
            'link_expiration_enabled' => $config['link_expiration_enabled'] ?? null,
            'default_expiration_days' => $config['default_expiration_days'] ?? null,
            'max_expiration_days' => $config['max_expiration_days'] ?? null,
            'bulk_creation_enabled' => $config['bulk_creation_enabled'] ?? null,
            'bulk_creation_limit' => $config['bulk_creation_limit'] ?? null,
            'qr_code_generation' => $config['qr_code_generation'] ?? null,
            'link_preview_enabled' => $config['preview_enabled'] ?? null,
            'click_tracking_enabled' => $config['click_tracking_enabled'] ?? null,
            'analytics_enabled' => $config['analytics_enabled'] ?? null,
            'geo_analytics_enabled' => $config['geo_analytics_enabled'] ?? null,
            'device_analytics_enabled' => $config['device_analytics_enabled'] ?? null,
            'referrer_tracking' => $config['referrer_tracking'] ?? null,
            'utm_parameter_tracking' => $config['utm_parameter_tracking'] ?? null,
            'link_validation_enabled' => $config['link_validation_enabled'] ?? null,
            'malware_scanning_enabled' => $config['malware_scanning_enabled'] ?? null,
            'adult_content_detection' => $config['adult_content_detection'] ?? null,
            'spam_detection_enabled' => $config['spam_detection_enabled'] ?? null,
            'domain_blacklist_enabled' => $config['domain_blacklist_enabled'] ?? null,
            'partner_branding_enabled' => $config['partner_branding_enabled'] ?? null,
            'custom_domains_enabled' => $config['custom_domains_enabled'] ?? null
        ], fn($value) => $value !== null);

        $this->updateSystemConfig($linkSettings);
        $this->logConfigChange('link_config_update', $config);
    }

    // ============================================================================
    // SYSTEM MAINTENANCE CONFIGURATION
    // ============================================================================

    public function getMaintenanceConfig(): array
    {
        return [
            'maintenance_mode' => $this->get('maintenance_mode', false),
            'maintenance_message' => $this->get('maintenance_message', 'System is under maintenance. Please try again later.'),
            'auto_cleanup_enabled' => $this->get('auto_cleanup_enabled', true),
            'cleanup_expired_links' => $this->get('cleanup_expired_links', true),
            'cleanup_old_analytics' => $this->get('cleanup_old_analytics', true),
            'analytics_retention_days' => $this->get('analytics_retention_days', 730),
            'log_retention_days' => $this->get('log_retention_days', 90),
            'session_cleanup_enabled' => $this->get('session_cleanup_enabled', true),
            'cache_warming_enabled' => $this->get('cache_warming_enabled', true),
            'database_optimization' => $this->get('database_optimization', true),
            'backup_enabled' => $this->get('backup_enabled', true),
            'backup_frequency' => $this->get('backup_frequency', 'daily'),
            'backup_retention_days' => $this->get('backup_retention_days', 30),
            'health_check_enabled' => $this->get('health_check_enabled', true),
            'health_check_interval' => $this->get('health_check_interval', 300),
            'monitoring_enabled' => $this->get('monitoring_enabled', true),
            'alert_thresholds' => $this->get('alert_thresholds', [
                'cpu_usage' => 80,
                'memory_usage' => 85,
                'disk_usage' => 90,
                'response_time' => 1000,
                'error_rate' => 5
            ])
        ];
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    private function setSetting(string $key, mixed $value): void
    {
        $serializedValue = $this->serializeSettingValue($value);
        
        $stmt = $this->database->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = VALUES(updated_at)
        ");
        
        $stmt->execute([$key, $serializedValue]);
    }

    private function parseSettingValue(string $value): mixed
    {
        // Try to decode as JSON first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Check for boolean values
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        // Check for numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Return as string
        return $value;
    }

    private function serializeSettingValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function clearConfigCache(): void
    {
        $keys = $this->cache->keys(self::CACHE_PREFIX . '*');
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    private function logConfigChange(string $action, array $config): void
    {
        $stmt = $this->database->prepare("
            INSERT INTO admin_audit_log (action, data, admin_user_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $action,
            json_encode($config),
            $_SESSION['admin_user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    // ============================================================================
    // BULK CONFIGURATION OPERATIONS
    // ============================================================================

    public function exportConfiguration(): array
    {
        return [
            'system' => $this->getAllSystemConfig(),
            'advertisement' => $this->getAdConfig(),
            'revenue' => $this->getRevenueConfig(),
            'security' => $this->getSecurityConfig(),
            'links' => $this->getLinkConfig(),
            'maintenance' => $this->getMaintenanceConfig(),
            'exported_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];
    }

    public function importConfiguration(array $config): void
    {
        $this->database->beginTransaction();
        
        try {
            if (isset($config['system'])) {
                $this->updateSystemConfig($config['system']);
            }
            
            if (isset($config['advertisement'])) {
                $this->updateAdConfig($config['advertisement']);
            }
            
            if (isset($config['revenue'])) {
                $this->updateRevenueConfig($config['revenue']);
            }
            
            if (isset($config['security'])) {
                $this->updateSecurityConfig($config['security']);
            }
            
            if (isset($config['links'])) {
                $this->updateLinkConfig($config['links']);
            }
            
            $this->database->commit();
            $this->clearConfigCache();
            
            $this->logConfigChange('configuration_import', [
                'imported_sections' => array_keys($config),
                'import_timestamp' => new DateTime()
            ]);
            
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw new \Exception('Configuration import failed: ' . $e->getMessage());
        }
    }

    public function resetToDefaults(): void
    {
        $this->database->beginTransaction();
        
        try {
            // Delete all current settings
            $this->database->exec("DELETE FROM system_settings");
            
            // Insert default settings
            $defaults = $this->getDefaultSettings();
            foreach ($defaults as $key => $value) {
                $this->setSetting($key, $value);
            }
            
            $this->database->commit();
            $this->clearConfigCache();
            
            $this->logConfigChange('configuration_reset_to_defaults', [
                'reset_timestamp' => new DateTime()
            ]);
            
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw new \Exception('Configuration reset failed: ' . $e->getMessage());
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            // System settings
            'short_code_length' => 6,
            'short_code_alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'default_expiration_days' => 0,
            'enable_analytics' => true,
            'enable_custom_codes' => true,
            'maintenance_mode' => false,
            
            // Advertisement settings
            'default_ad_duration' => 15,
            'max_ad_duration' => 30,
            'min_ad_duration' => 5,
            'default_ad_cpm' => 2.5000,
            
            // Revenue settings
            'default_revenue_share' => 0.50,
            'min_payout_amount' => 50.00,
            
            // Security settings
            'api_rate_limit_default' => 1000,
            'session_timeout_hours' => 24,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 30
        ];
    }
}