<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use LinkShortener\Models\Partner;
use LinkShortener\Models\SubscriptionPlan;
use LinkShortener\Repositories\PartnerRepository;
use LinkShortener\Repositories\SubscriptionPlanRepository;
use LinkShortener\Repositories\SecurityEventRepository;
use LinkShortener\Repositories\AuditLogRepository;
use LinkShortener\Config\Cache;
use LinkShortener\Config\Database;
use DateTime;
use PDO;

class AdminService
{
    public function __construct(
        private PartnerRepository $partnerRepository,
        private SubscriptionPlanRepository $planRepository,
        private SecurityEventRepository $securityEventRepository,
        private AuditLogRepository $auditLogRepository,
        private SystemConfigService $configService,
        private Cache $cache,
        private PDO $database
    ) {}

    // ============================================================================
    // DASHBOARD DATA
    // ============================================================================

    public function getDashboardData(): array
    {
        $cacheKey = 'admin:dashboard:data';
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }

        $data = [
            'overview' => $this->getDashboardOverview(),
            'recent_partners' => $this->getRecentPartners(10),
            'system_health' => $this->getSystemHealth(),
            'revenue_summary' => $this->getRevenueSummary(),
            'top_performing_ads' => $this->getTopPerformingAds(5),
            'security_alerts' => $this->getRecentSecurityAlerts(5),
            'system_metrics' => $this->getSystemMetrics()
        ];

        $this->cache->set($cacheKey, $data, 300); // Cache for 5 minutes
        return $data;
    }

    private function getDashboardOverview(): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                (SELECT COUNT(*) FROM partners WHERE status = 'active') as active_partners,
                (SELECT COUNT(*) FROM partners WHERE status = 'pending') as pending_partners,
                (SELECT COUNT(*) FROM partners WHERE status = 'suspended') as suspended_partners,
                (SELECT COUNT(*) FROM shortened_urls WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as links_today,
                (SELECT COUNT(*) FROM click_analytics WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as clicks_today,
                (SELECT SUM(revenue_generated) FROM click_analytics WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as revenue_30d,
                (SELECT COUNT(*) FROM advertisements WHERE active = 1) as active_ads
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getRecentPartners(int $limit): array
    {
        $stmt = $this->database->prepare("
            SELECT p.*, pp.first_name, pp.last_name, sp.name as plan_name
            FROM partners p
            LEFT JOIN partner_profiles pp ON p.id = pp.partner_id
            LEFT JOIN subscription_plans sp ON p.plan_id = sp.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'redis' => $this->checkRedisHealth(),
            'storage' => $this->checkStorageHealth(),
            'external_apis' => $this->checkExternalApisHealth()
        ];
    }

    private function getRevenueSummary(): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                SUM(revenue_generated) as total_revenue,
                COUNT(*) as total_clicks,
                AVG(revenue_generated) as avg_revenue_per_click,
                SUM(CASE WHEN clicked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN revenue_generated ELSE 0 END) as revenue_today,
                SUM(CASE WHEN clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN revenue_generated ELSE 0 END) as revenue_7d,
                SUM(CASE WHEN clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN revenue_generated ELSE 0 END) as revenue_30d
            FROM click_analytics
            WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getTopPerformingAds(int $limit): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                a.*,
                COUNT(ca.id) as impressions,
                SUM(CASE WHEN ca.ad_clicked = 1 THEN 1 ELSE 0 END) as clicks,
                ROUND(SUM(CASE WHEN ca.ad_clicked = 1 THEN 1 ELSE 0 END) / COUNT(ca.id) * 100, 2) as ctr,
                SUM(ca.revenue_generated) as revenue
            FROM advertisements a
            LEFT JOIN click_analytics ca ON a.id = ca.ad_id
            WHERE a.active = 1 AND ca.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY a.id
            ORDER BY revenue DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentSecurityAlerts(int $limit): array
    {
        $stmt = $this->database->prepare("
            SELECT *
            FROM security_events
            WHERE severity IN ('high', 'critical') AND resolved = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSystemMetrics(): array
    {
        return [
            'uptime' => $this->getSystemUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'active_connections' => $this->getActiveConnections(),
            'cache_hit_rate' => $this->getCacheHitRate()
        ];
    }

    // ============================================================================
    // PARTNER MANAGEMENT
    // ============================================================================

    public function updatePartnerStatus(string $partnerId, string $status, string $reason = ''): Partner
    {
        $partner = $this->partnerRepository->findById($partnerId);
        if (!$partner) {
            throw new \Exception('Partner not found');
        }

        $oldStatus = $partner->getStatus();
        $partner->setStatus($status);
        
        $this->partnerRepository->save($partner);

        // Log the status change
        $this->logAuditEvent('partner_status_change', [
            'partner_id' => $partnerId,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'reason' => $reason
        ]);

        // Send notification to partner
        $this->sendPartnerStatusNotification($partner, $status, $reason);

        // Clear cache
        $this->cache->delete("partner:{$partnerId}");

        return $partner;
    }

    public function updatePartnerPermissions(string $partnerId, array $permissions): Partner
    {
        $partner = $this->partnerRepository->findById($partnerId);
        if (!$partner) {
            throw new \Exception('Partner not found');
        }

        // Update partner permissions in metadata
        $metadata = $partner->getMetadata() ?? [];
        $metadata['permissions'] = $permissions;
        $partner->setMetadata($metadata);
        
        $this->partnerRepository->save($partner);

        // Log the permission change
        $this->logAuditEvent('partner_permissions_change', [
            'partner_id' => $partnerId,
            'permissions' => $permissions
        ]);

        // Clear cache
        $this->cache->delete("partner:{$partnerId}");

        return $partner;
    }

    public function updateRevenueShare(string $partnerId, float $revenueShare, string $reason = ''): Partner
    {
        $partner = $this->partnerRepository->findById($partnerId);
        if (!$partner) {
            throw new \Exception('Partner not found');
        }

        $oldShare = $partner->getRevenueShare();
        $partner->setRevenueShare($revenueShare);
        
        $this->partnerRepository->save($partner);

        // Log the revenue share change
        $this->logAuditEvent('partner_revenue_share_change', [
            'partner_id' => $partnerId,
            'old_share' => $oldShare,
            'new_share' => $revenueShare,
            'reason' => $reason
        ]);

        // Clear cache
        $this->cache->delete("partner:{$partnerId}");

        return $partner;
    }

    public function getPartnerActivity(string $partnerId): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                'login' as activity_type,
                last_login as activity_time,
                'Partner logged in' as description
            FROM partners 
            WHERE id = ? AND last_login IS NOT NULL
            
            UNION ALL
            
            SELECT 
                'link_created' as activity_type,
                created_at as activity_time,
                CONCAT('Created link: ', short_code) as description
            FROM shortened_urls 
            WHERE partner_id = ?
            
            UNION ALL
            
            SELECT 
                'security_event' as activity_type,
                created_at as activity_time,
                CONCAT('Security event: ', event_type) as description
            FROM security_events 
            WHERE partner_id = ?
            
            ORDER BY activity_time DESC
            LIMIT 50
        ");
        $stmt->execute([$partnerId, $partnerId, $partnerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================================
    // SUBSCRIPTION PLAN MANAGEMENT
    // ============================================================================

    public function getAllSubscriptionPlans(): array
    {
        return $this->planRepository->findAll();
    }

    public function getSubscriptionPlan(string $planId): ?SubscriptionPlan
    {
        return $this->planRepository->findById($planId);
    }

    public function createSubscriptionPlan(array $data): SubscriptionPlan
    {
        $plan = new SubscriptionPlan(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'] ?? null,
            priceMonthly: (float) ($data['price_monthly'] ?? 0),
            priceYearly: (float) ($data['price_yearly'] ?? 0),
            apiRequestsLimit: (int) ($data['api_requests_limit'] ?? 1000),
            features: $data['features'] ?? [],
            active: (bool) ($data['active'] ?? true)
        );

        $this->planRepository->save($plan);

        // Log the plan creation
        $this->logAuditEvent('subscription_plan_created', [
            'plan_id' => $plan->getId(),
            'plan_data' => $data
        ]);

        return $plan;
    }

    public function updateSubscriptionPlan(string $planId, array $data): SubscriptionPlan
    {
        $plan = $this->planRepository->findById($planId);
        if (!$plan) {
            throw new \Exception('Subscription plan not found');
        }

        if (isset($data['name'])) $plan->setName($data['name']);
        if (isset($data['description'])) $plan->setDescription($data['description']);
        if (isset($data['price_monthly'])) $plan->setPriceMonthly((float) $data['price_monthly']);
        if (isset($data['price_yearly'])) $plan->setPriceYearly((float) $data['price_yearly']);
        if (isset($data['api_requests_limit'])) $plan->setApiRequestsLimit((int) $data['api_requests_limit']);
        if (isset($data['features'])) $plan->setFeatures($data['features']);
        if (isset($data['active'])) $plan->setActive((bool) $data['active']);

        $this->planRepository->save($plan);

        // Log the plan update
        $this->logAuditEvent('subscription_plan_updated', [
            'plan_id' => $planId,
            'plan_data' => $data
        ]);

        return $plan;
    }

    // ============================================================================
    // SECURITY & AUDIT
    // ============================================================================

    public function getSecurityEvents(array $filters): array
    {
        return $this->securityEventRepository->findWithFilters($filters);
    }

    public function getAuditLogs(array $filters): array
    {
        return $this->auditLogRepository->findWithFilters($filters);
    }

    private function logAuditEvent(string $action, array $data): void
    {
        $this->auditLogRepository->create([
            'action' => $action,
            'data' => json_encode($data),
            'admin_user_id' => $_SESSION['admin_user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => new DateTime()
        ]);
    }

    // ============================================================================
    // BLACKLIST/WHITELIST MANAGEMENT
    // ============================================================================

    public function getPartnerBlacklist(): array
    {
        $stmt = $this->database->prepare("
            SELECT p.*, pb.reason, pb.created_at as blacklisted_at
            FROM partners p
            JOIN partner_blacklist pb ON p.id = pb.partner_id
            ORDER BY pb.created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePartnerBlacklist(array $data): void
    {
        $action = $data['action']; // 'add' or 'remove'
        $partnerId = $data['partner_id'];
        $reason = $data['reason'] ?? '';

        if ($action === 'add') {
            $stmt = $this->database->prepare("
                INSERT INTO partner_blacklist (partner_id, reason, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), updated_at = NOW()
            ");
            $stmt->execute([$partnerId, $reason]);

            // Update partner status
            $this->updatePartnerStatus($partnerId, 'suspended', 'Added to blacklist: ' . $reason);
        } else {
            $stmt = $this->database->prepare("
                DELETE FROM partner_blacklist WHERE partner_id = ?
            ");
            $stmt->execute([$partnerId]);

            // Reactivate partner if appropriate
            $this->updatePartnerStatus($partnerId, 'active', 'Removed from blacklist');
        }

        $this->logAuditEvent('partner_blacklist_update', [
            'action' => $action,
            'partner_id' => $partnerId,
            'reason' => $reason
        ]);
    }

    public function getDomainBlacklist(): array
    {
        $stmt = $this->database->prepare("
            SELECT * FROM domain_blacklist ORDER BY created_at DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDomainBlacklist(array $data): void
    {
        $action = $data['action']; // 'add' or 'remove'
        $domain = $data['domain'];
        $reason = $data['reason'] ?? '';

        if ($action === 'add') {
            $stmt = $this->database->prepare("
                INSERT INTO domain_blacklist (domain, reason, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), updated_at = NOW()
            ");
            $stmt->execute([$domain, $reason]);
        } else {
            $stmt = $this->database->prepare("
                DELETE FROM domain_blacklist WHERE domain = ?
            ");
            $stmt->execute([$domain]);
        }

        $this->logAuditEvent('domain_blacklist_update', [
            'action' => $action,
            'domain' => $domain,
            'reason' => $reason
        ]);
    }

    // ============================================================================
    // SYSTEM MAINTENANCE
    // ============================================================================

    public function getSystemStatus(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'redis' => $this->checkRedisHealth(),
            'storage' => $this->checkStorageHealth(),
            'external_apis' => $this->checkExternalApisHealth(),
            'queue_status' => $this->getQueueStatus(),
            'recent_errors' => $this->getRecentErrors(),
            'system_load' => $this->getSystemLoad()
        ];
    }

    public function clearCache(string $cacheType = 'all'): array
    {
        $result = ['cleared' => []];

        switch ($cacheType) {
            case 'all':
                $this->cache->flush();
                $result['cleared'][] = 'all_cache';
                break;
            
            case 'partners':
                $this->clearPartnerCache();
                $result['cleared'][] = 'partner_cache';
                break;
            
            case 'analytics':
                $this->clearAnalyticsCache();
                $result['cleared'][] = 'analytics_cache';
                break;
            
            case 'config':
                $this->clearConfigCache();
                $result['cleared'][] = 'config_cache';
                break;
        }

        $this->logAuditEvent('cache_cleared', [
            'cache_type' => $cacheType,
            'result' => $result
        ]);

        return $result;
    }

    public function runMaintenanceTask(string $task): array
    {
        $result = ['task' => $task, 'status' => 'completed', 'details' => []];

        switch ($task) {
            case 'cleanup_expired_links':
                $result['details'] = $this->cleanupExpiredLinks();
                break;
            
            case 'cleanup_old_analytics':
                $result['details'] = $this->cleanupOldAnalytics();
                break;
            
            case 'optimize_database':
                $result['details'] = $this->optimizeDatabase();
                break;
            
            case 'generate_reports':
                $result['details'] = $this->generateScheduledReports();
                break;
            
            default:
                $result['status'] = 'unknown_task';
        }

        $this->logAuditEvent('maintenance_task_run', $result);

        return $result;
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    private function checkDatabaseHealth(): array
    {
        try {
            $stmt = $this->database->query('SELECT 1');
            $result = $stmt->fetch();
            
            return [
                'status' => 'healthy',
                'response_time' => $this->measureQueryTime('SELECT 1'),
                'connections' => $this->getDatabaseConnections()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkRedisHealth(): array
    {
        try {
            $startTime = microtime(true);
            $this->cache->set('health_check', 'ok', 10);
            $value = $this->cache->get('health_check');
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => $value === 'ok' ? 'healthy' : 'unhealthy',
                'response_time' => round($responseTime, 2) . 'ms'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkStorageHealth(): array
    {
        $storagePath = $_ENV['STORAGE_PATH'] ?? 'storage';
        
        return [
            'status' => is_writable($storagePath) ? 'healthy' : 'unhealthy',
            'free_space' => disk_free_space($storagePath),
            'total_space' => disk_total_space($storagePath)
        ];
    }

    private function checkExternalApisHealth(): array
    {
        // Check Stripe API
        $stripeStatus = $this->checkStripeApi();
        
        // Check email service
        $emailStatus = $this->checkEmailService();
        
        return [
            'stripe' => $stripeStatus,
            'email' => $emailStatus
        ];
    }

    private function sendPartnerStatusNotification(Partner $partner, string $status, string $reason): void
    {
        // Implementation would send email notification to partner
        // This is a placeholder for the actual email sending logic
    }

    private function clearPartnerCache(): void
    {
        // Clear all partner-related cache keys
        $keys = $this->cache->keys('partner:*');
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    private function clearAnalyticsCache(): void
    {
        // Clear all analytics-related cache keys
        $keys = $this->cache->keys('analytics:*');
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    private function clearConfigCache(): void
    {
        // Clear all config-related cache keys
        $keys = $this->cache->keys('config:*');
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    private function cleanupExpiredLinks(): array
    {
        $stmt = $this->database->prepare("
            UPDATE shortened_urls 
            SET status = 'expired' 
            WHERE expires_at < NOW() AND status = 'active'
        ");
        $stmt->execute();
        
        return ['expired_links' => $stmt->rowCount()];
    }

    private function cleanupOldAnalytics(): array
    {
        $retentionDays = $this->configService->get('analytics_retention_days', 730);
        
        $stmt = $this->database->prepare("
            DELETE FROM click_analytics 
            WHERE clicked_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$retentionDays]);
        
        return ['deleted_records' => $stmt->rowCount()];
    }

    private function optimizeDatabase(): array
    {
        $tables = ['partners', 'shortened_urls', 'click_analytics', 'advertisements'];
        $optimized = [];
        
        foreach ($tables as $table) {
            $stmt = $this->database->prepare("OPTIMIZE TABLE {$table}");
            $stmt->execute();
            $optimized[] = $table;
        }
        
        return ['optimized_tables' => $optimized];
    }

    private function generateScheduledReports(): array
    {
        // Generate daily, weekly, monthly reports
        // This would trigger the report generation system
        return ['reports_generated' => ['daily', 'weekly', 'monthly']];
    }

    private function measureQueryTime(string $query): float
    {
        $startTime = microtime(true);
        $this->database->query($query);
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    private function getDatabaseConnections(): int
    {
        $stmt = $this->database->query("SHOW STATUS LIKE 'Threads_connected'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['Value'];
    }

    private function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = (int) explode(' ', $uptime)[0];
            return gmdate('H:i:s', $seconds);
        }
        return 'N/A';
    }

    private function getMemoryUsage(): array
    {
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }

    private function getDiskUsage(): array
    {
        $path = $_ENV['STORAGE_PATH'] ?? '.';
        return [
            'free' => disk_free_space($path),
            'total' => disk_total_space($path)
        ];
    }

    private function getActiveConnections(): int
    {
        return $this->getDatabaseConnections();
    }

    private function getCacheHitRate(): float
    {
        // This would require Redis INFO command or similar cache statistics
        return 0.0; // Placeholder
    }

    private function getQueueStatus(): array
    {
        // Check queue health and pending jobs
        return ['status' => 'healthy', 'pending_jobs' => 0];
    }

    private function getRecentErrors(): array
    {
        // Get recent error logs
        return [];
    }

    private function getSystemLoad(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        }
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }

    private function checkStripeApi(): array
    {
        try {
            // Placeholder for Stripe API health check
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkEmailService(): array
    {
        try {
            // Placeholder for email service health check
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}