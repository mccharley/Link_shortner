#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LinkShortener\Config\Database;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Setup logger
$logger = new Logger('stats-updater');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/stats.log', Logger::INFO));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting statistics update process...');

try {
    $pdo = Database::getConnection();
    $statsType = $argv[1] ?? 'all'; // all, links, analytics, system, cache
    
    $logger->info("Updating statistics: {$statsType}");
    
    switch ($statsType) {
        case 'all':
            updateLinkStats($pdo, $logger);
            updateAnalyticsStats($pdo, $logger);
            updateSystemStats($pdo, $logger);
            updateCacheStats($logger);
            break;
        case 'links':
            updateLinkStats($pdo, $logger);
            break;
        case 'analytics':
            updateAnalyticsStats($pdo, $logger);
            break;
        case 'system':
            updateSystemStats($pdo, $logger);
            break;
        case 'cache':
            updateCacheStats($logger);
            break;
        default:
            $logger->error("Unknown statistics type: {$statsType}");
            exit(1);
    }
    
    // Update last statistics update timestamp
    updateLastStatsTimestamp($pdo, $logger);
    
    $logger->info('Statistics update completed successfully');
    
} catch (Exception $e) {
    $logger->error('Statistics update failed: ' . $e->getMessage());
    exit(1);
}

function updateLinkStats(PDO $pdo, Logger $logger): void
{
    $logger->info('Updating link statistics...');
    
    // Create statistics table if it doesn't exist
    createStatsTable($pdo);
    
    // Total links
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links");
    $totalLinks = $stmt->fetch()['count'];
    
    // Active links
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links WHERE is_active = 1");
    $activeLinks = $stmt->fetch()['count'];
    
    // Expired links
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links WHERE expires_at < NOW() AND expires_at IS NOT NULL");
    $expiredLinks = $stmt->fetch()['count'];
    
    // Links created today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links WHERE DATE(created_at) = CURDATE()");
    $linksToday = $stmt->fetch()['count'];
    
    // Links created this week
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $linksThisWeek = $stmt->fetch()['count'];
    
    // Links created this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM links WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $linksThisMonth = $stmt->fetch()['count'];
    
    // Top domains
    $stmt = $pdo->query("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain')) as domain,
            COUNT(*) as count
        FROM links 
        WHERE is_active = 1
        GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain'))
        ORDER BY count DESC
        LIMIT 10
    ");
    $topDomains = $stmt->fetchAll();
    
    // Update statistics
    $linkStats = [
        'total_links' => $totalLinks,
        'active_links' => $activeLinks,
        'expired_links' => $expiredLinks,
        'links_today' => $linksToday,
        'links_this_week' => $linksThisWeek,
        'links_this_month' => $linksThisMonth,
        'top_domains' => $topDomains
    ];
    
    updateStatistic($pdo, 'link_stats', $linkStats);
    
    $logger->info("Link statistics updated: {$totalLinks} total, {$activeLinks} active, {$expiredLinks} expired");
}

function updateAnalyticsStats(PDO $pdo, Logger $logger): void
{
    $logger->info('Updating analytics statistics...');
    
    // Total clicks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM click_analytics");
    $totalClicks = $stmt->fetch()['count'];
    
    // Clicks today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM click_analytics WHERE DATE(clicked_at) = CURDATE()");
    $clicksToday = $stmt->fetch()['count'];
    
    // Clicks this week
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM click_analytics WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $clicksThisWeek = $stmt->fetch()['count'];
    
    // Clicks this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM click_analytics WHERE MONTH(clicked_at) = MONTH(NOW()) AND YEAR(clicked_at) = YEAR(NOW())");
    $clicksThisMonth = $stmt->fetch()['count'];
    
    // Unique visitors today
    $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as count FROM click_analytics WHERE DATE(clicked_at) = CURDATE()");
    $uniqueVisitorsToday = $stmt->fetch()['count'];
    
    // Top countries
    $stmt = $pdo->query("
        SELECT country, COUNT(*) as clicks
        FROM click_analytics
        WHERE country IS NOT NULL AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY country
        ORDER BY clicks DESC
        LIMIT 10
    ");
    $topCountries = $stmt->fetchAll();
    
    // Top browsers
    $stmt = $pdo->query("
        SELECT browser, COUNT(*) as count
        FROM click_analytics
        WHERE browser IS NOT NULL AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ");
    $topBrowsers = $stmt->fetchAll();
    
    // Top devices
    $stmt = $pdo->query("
        SELECT device_type, COUNT(*) as count
        FROM click_analytics
        WHERE device_type IS NOT NULL AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY device_type
        ORDER BY count DESC
        LIMIT 10
    ");
    $topDevices = $stmt->fetchAll();
    
    // Most active hours
    $stmt = $pdo->query("
        SELECT HOUR(clicked_at) as hour, COUNT(*) as clicks
        FROM click_analytics
        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(clicked_at)
        ORDER BY clicks DESC
    ");
    $hourlyStats = $stmt->fetchAll();
    
    // Click trends (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            DATE(clicked_at) as date,
            COUNT(*) as clicks,
            COUNT(DISTINCT ip_address) as unique_visitors
        FROM click_analytics
        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(clicked_at)
        ORDER BY date DESC
    ");
    $clickTrends = $stmt->fetchAll();
    
    $analyticsStats = [
        'total_clicks' => $totalClicks,
        'clicks_today' => $clicksToday,
        'clicks_this_week' => $clicksThisWeek,
        'clicks_this_month' => $clicksThisMonth,
        'unique_visitors_today' => $uniqueVisitorsToday,
        'top_countries' => $topCountries,
        'top_browsers' => $topBrowsers,
        'top_devices' => $topDevices,
        'hourly_stats' => $hourlyStats,
        'click_trends' => $clickTrends
    ];
    
    updateStatistic($pdo, 'analytics_stats', $analyticsStats);
    
    $logger->info("Analytics statistics updated: {$totalClicks} total clicks, {$clicksToday} today");
}

function updateSystemStats(PDO $pdo, Logger $logger): void
{
    $logger->info('Updating system statistics...');
    
    // Database size
    $dbName = $_ENV['DB_NAME'] ?? 'linkshortener';
    $stmt = $pdo->prepare("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = ?
    ");
    $stmt->execute([$dbName]);
    $dbSize = $stmt->fetch()['size_mb'] ?? 0;
    
    // Table sizes
    $stmt = $pdo->prepare("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
            table_rows
        FROM information_schema.tables
        WHERE table_schema = ?
        ORDER BY (data_length + index_length) DESC
    ");
    $stmt->execute([$dbName]);
    $tableSizes = $stmt->fetchAll();
    
    // Security events count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM security_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $securityEventsToday = $stmt->fetch()['count'];
    
    // Rate limit entries
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rate_limits WHERE expires_at > NOW()");
    $activeRateLimits = $stmt->fetch()['count'];
    
    // System uptime (approximate based on oldest log entry)
    $stmt = $pdo->query("SELECT MIN(created_at) as oldest FROM links");
    $oldestRecord = $stmt->fetch()['oldest'];
    $systemUptime = $oldestRecord ? time() - strtotime($oldestRecord) : 0;
    
    // Performance metrics
    $performanceStats = getPerformanceStats($pdo);
    
    $systemStats = [
        'database_size_mb' => $dbSize,
        'table_sizes' => $tableSizes,
        'security_events_today' => $securityEventsToday,
        'active_rate_limits' => $activeRateLimits,
        'system_uptime_seconds' => $systemUptime,
        'performance' => $performanceStats,
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true)
    ];
    
    updateStatistic($pdo, 'system_stats', $systemStats);
    
    $logger->info("System statistics updated: DB size {$dbSize}MB, {$securityEventsToday} security events today");
}

function updateCacheStats(Logger $logger): void
{
    $logger->info('Updating cache statistics...');
    
    try {
        // This would depend on your cache implementation
        // For now, we'll create a placeholder
        $cacheStats = [
            'cache_type' => 'redis',
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // If using Redis
        if (class_exists('Redis')) {
            $redis = new Redis();
            if ($redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379))) {
                $info = $redis->info();
                $cacheStats = [
                    'cache_type' => 'redis',
                    'status' => 'connected',
                    'memory_used' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $redis->close();
            }
        }
        
        // Save cache stats to file since we can't use the cache to store cache stats
        $statsFile = __DIR__ . '/../logs/cache_stats.json';
        file_put_contents($statsFile, json_encode($cacheStats, JSON_PRETTY_PRINT));
        
        $logger->info("Cache statistics updated: " . $cacheStats['status']);
        
    } catch (Exception $e) {
        $logger->warning("Could not update cache statistics: " . $e->getMessage());
    }
}

function getPerformanceStats(PDO $pdo): array
{
    $stats = [];
    
    // Average response time (simulated - you'd need to implement actual tracking)
    $stats['avg_response_time_ms'] = rand(50, 200);
    
    // Database query performance
    $start = microtime(true);
    $pdo->query("SELECT 1");
    $stats['db_query_time_ms'] = round((microtime(true) - $start) * 1000, 2);
    
    // Slow query count (if enabled in MySQL)
    try {
        $stmt = $pdo->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $result = $stmt->fetch();
        $stats['slow_queries'] = $result ? (int)$result['Value'] : 0;
    } catch (Exception $e) {
        $stats['slow_queries'] = 'unavailable';
    }
    
    return $stats;
}

function createStatsTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `statistics` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `stat_key` varchar(100) NOT NULL,
        `stat_value` longtext NOT NULL,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `stat_key` (`stat_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function updateStatistic(PDO $pdo, string $key, array $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO statistics (stat_key, stat_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
        stat_value = VALUES(stat_value),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([$key, json_encode($value)]);
}

function updateLastStatsTimestamp(PDO $pdo, Logger $logger): void
{
    $timestamp = date('Y-m-d H:i:s');
    updateStatistic($pdo, 'last_stats_update', ['timestamp' => $timestamp]);
    $logger->info("Last statistics update timestamp set to: {$timestamp}");
}

exit(0);