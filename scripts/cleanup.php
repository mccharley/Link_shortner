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
$logger = new Logger('cleanup');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/cleanup.log', Logger::INFO));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting cleanup process...');

try {
    $pdo = Database::getConnection();
    $cleanupCount = 0;
    
    // 1. Cleanup expired links
    $logger->info('Cleaning up expired links...');
    $stmt = $pdo->prepare("UPDATE links SET is_active = 0 WHERE expires_at < NOW() AND is_active = 1");
    $stmt->execute();
    $expiredLinks = $stmt->rowCount();
    $cleanupCount += $expiredLinks;
    $logger->info("Deactivated {$expiredLinks} expired links");
    
    // 2. Delete old analytics data (older than 1 year)
    $logger->info('Cleaning up old analytics data...');
    $stmt = $pdo->prepare("DELETE FROM click_analytics WHERE clicked_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
    $stmt->execute();
    $oldAnalytics = $stmt->rowCount();
    $cleanupCount += $oldAnalytics;
    $logger->info("Deleted {$oldAnalytics} old analytics records");
    
    // 3. Cleanup expired rate limits
    $logger->info('Cleaning up expired rate limits...');
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
    $stmt->execute();
    $expiredRateLimits = $stmt->rowCount();
    $cleanupCount += $expiredRateLimits;
    $logger->info("Deleted {$expiredRateLimits} expired rate limit entries");
    
    // 4. Cleanup old security events (older than 6 months)
    $logger->info('Cleaning up old security events...');
    $stmt = $pdo->prepare("DELETE FROM security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) AND severity IN ('low', 'medium')");
    $stmt->execute();
    $oldSecurityEvents = $stmt->rowCount();
    $cleanupCount += $oldSecurityEvents;
    $logger->info("Deleted {$oldSecurityEvents} old security events");
    
    // 5. Optimize tables for better performance
    $logger->info('Optimizing database tables...');
    $tables = ['links', 'click_analytics', 'rate_limits', 'security_events'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("OPTIMIZE TABLE {$table}");
        $stmt->execute();
        $logger->info("Optimized table: {$table}");
    }
    
    // 6. Update table statistics
    $logger->info('Updating table statistics...');
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("ANALYZE TABLE {$table}");
        $stmt->execute();
    }
    
    $logger->info("Cleanup completed successfully. Total items processed: {$cleanupCount}");
    
} catch (Exception $e) {
    $logger->error('Cleanup failed: ' . $e->getMessage());
    exit(1);
}

exit(0);