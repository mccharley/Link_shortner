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
$logger = new Logger('revenue');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/revenue.log', Logger::INFO));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting revenue calculation...');

try {
    $pdo = Database::getConnection();
    $period = $argv[1] ?? 'daily'; // daily, weekly, monthly
    $date = $argv[2] ?? date('Y-m-d');
    
    $logger->info("Calculating {$period} revenue for {$date}");
    
    // Create revenue tracking table if it doesn't exist
    createRevenueTable($pdo);
    
    switch ($period) {
        case 'daily':
            calculateDailyRevenue($pdo, $date, $logger);
            break;
        case 'weekly':
            calculateWeeklyRevenue($pdo, $date, $logger);
            break;
        case 'monthly':
            calculateMonthlyRevenue($pdo, $date, $logger);
            break;
        default:
            $logger->error("Unknown period: {$period}");
            exit(1);
    }
    
    $logger->info('Revenue calculation completed successfully');
    
} catch (Exception $e) {
    $logger->error('Revenue calculation failed: ' . $e->getMessage());
    exit(1);
}

function createRevenueTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `revenue_tracking` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `period_type` enum('daily','weekly','monthly') NOT NULL,
        `period_date` date NOT NULL,
        `total_clicks` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `premium_clicks` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `revenue_per_click` decimal(10,4) NOT NULL DEFAULT 0.0010,
        `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
        `partner_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
        `platform_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
        `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `period_tracking` (`period_type`, `period_date`),
        KEY `idx_period_date` (`period_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function calculateDailyRevenue(PDO $pdo, string $date, Logger $logger): void
{
    // Get click statistics for the day
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_clicks,
            COUNT(CASE WHEN JSON_EXTRACT(l.metadata, '$.premium') = true THEN 1 END) as premium_clicks
        FROM click_analytics ca
        JOIN links l ON ca.link_id = l.id
        WHERE DATE(ca.clicked_at) = ?
    ");
    $stmt->execute([$date]);
    $stats = $stmt->fetch();
    
    // Revenue rates (configurable)
    $standardRate = 0.001; // $0.001 per click
    $premiumRate = 0.005;  // $0.005 per premium click
    $partnerShare = 0.7;   // 70% to partners, 30% to platform
    
    $standardClicks = $stats['total_clicks'] - $stats['premium_clicks'];
    $premiumClicks = $stats['premium_clicks'];
    
    $standardRevenue = $standardClicks * $standardRate;
    $premiumRevenue = $premiumClicks * $premiumRate;
    $totalRevenue = $standardRevenue + $premiumRevenue;
    
    $partnerRevenue = $totalRevenue * $partnerShare;
    $platformRevenue = $totalRevenue * (1 - $partnerShare);
    
    // Insert or update revenue record
    $stmt = $pdo->prepare("
        INSERT INTO revenue_tracking 
        (period_type, period_date, total_clicks, premium_clicks, total_revenue, partner_revenue, platform_revenue)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_clicks = VALUES(total_clicks),
        premium_clicks = VALUES(premium_clicks),
        total_revenue = VALUES(total_revenue),
        partner_revenue = VALUES(partner_revenue),
        platform_revenue = VALUES(platform_revenue),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        'daily',
        $date,
        $stats['total_clicks'],
        $stats['premium_clicks'],
        $totalRevenue,
        $partnerRevenue,
        $platformRevenue
    ]);
    
    $logger->info("Daily revenue calculated: Total: $" . number_format($totalRevenue, 4) . 
                 ", Partner: $" . number_format($partnerRevenue, 4) . 
                 ", Platform: $" . number_format($platformRevenue, 4));
    
    // Calculate partner-specific revenue
    calculatePartnerRevenue($pdo, $date, 'daily', $logger);
}

function calculateWeeklyRevenue(PDO $pdo, string $date, Logger $logger): void
{
    $weekStart = date('Y-m-d', strtotime($date . ' -6 days'));
    
    // Aggregate daily revenue for the week
    $stmt = $pdo->prepare("
        SELECT 
            SUM(total_clicks) as total_clicks,
            SUM(premium_clicks) as premium_clicks,
            SUM(total_revenue) as total_revenue,
            SUM(partner_revenue) as partner_revenue,
            SUM(platform_revenue) as platform_revenue
        FROM revenue_tracking
        WHERE period_type = 'daily' AND period_date BETWEEN ? AND ?
    ");
    $stmt->execute([$weekStart, $date]);
    $stats = $stmt->fetch();
    
    if ($stats['total_clicks'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO revenue_tracking 
            (period_type, period_date, total_clicks, premium_clicks, total_revenue, partner_revenue, platform_revenue)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_clicks = VALUES(total_clicks),
            premium_clicks = VALUES(premium_clicks),
            total_revenue = VALUES(total_revenue),
            partner_revenue = VALUES(partner_revenue),
            platform_revenue = VALUES(platform_revenue),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            'weekly',
            $date,
            $stats['total_clicks'],
            $stats['premium_clicks'],
            $stats['total_revenue'],
            $stats['partner_revenue'],
            $stats['platform_revenue']
        ]);
        
        $logger->info("Weekly revenue calculated: Total: $" . number_format($stats['total_revenue'], 2));
    }
}

function calculateMonthlyRevenue(PDO $pdo, string $date, Logger $logger): void
{
    $month = date('Y-m', strtotime($date));
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    // Aggregate daily revenue for the month
    $stmt = $pdo->prepare("
        SELECT 
            SUM(total_clicks) as total_clicks,
            SUM(premium_clicks) as premium_clicks,
            SUM(total_revenue) as total_revenue,
            SUM(partner_revenue) as partner_revenue,
            SUM(platform_revenue) as platform_revenue
        FROM revenue_tracking
        WHERE period_type = 'daily' AND period_date BETWEEN ? AND ?
    ");
    $stmt->execute([$monthStart, $monthEnd]);
    $stats = $stmt->fetch();
    
    if ($stats['total_clicks'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO revenue_tracking 
            (period_type, period_date, total_clicks, premium_clicks, total_revenue, partner_revenue, platform_revenue)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_clicks = VALUES(total_clicks),
            premium_clicks = VALUES(premium_clicks),
            total_revenue = VALUES(total_revenue),
            partner_revenue = VALUES(partner_revenue),
            platform_revenue = VALUES(platform_revenue),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            'monthly',
            $monthEnd,
            $stats['total_clicks'],
            $stats['premium_clicks'],
            $stats['total_revenue'],
            $stats['partner_revenue'],
            $stats['platform_revenue']
        ]);
        
        $logger->info("Monthly revenue calculated: Total: $" . number_format($stats['total_revenue'], 2));
        
        // Generate payment reports for partners
        generatePartnerPaymentReports($pdo, $month, $logger);
    }
}

function calculatePartnerRevenue(PDO $pdo, string $date, string $period, Logger $logger): void
{
    // Create partner revenue table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `partner_revenue` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `partner_id` varchar(100) NOT NULL,
        `period_type` enum('daily','weekly','monthly') NOT NULL,
        `period_date` date NOT NULL,
        `clicks` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        `revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
        `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `partner_period` (`partner_id`, `period_type`, `period_date`),
        KEY `idx_partner_id` (`partner_id`),
        KEY `idx_period_date` (`period_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // Calculate revenue per partner (based on created_by field)
    $stmt = $pdo->prepare("
        SELECT 
            l.created_by as partner_id,
            COUNT(ca.id) as clicks,
            COUNT(ca.id) * 0.0007 as revenue  -- 70% of $0.001
        FROM click_analytics ca
        JOIN links l ON ca.link_id = l.id
        WHERE DATE(ca.clicked_at) = ?
        GROUP BY l.created_by
    ");
    $stmt->execute([$date]);
    $partnerStats = $stmt->fetchAll();
    
    foreach ($partnerStats as $partner) {
        $stmt = $pdo->prepare("
            INSERT INTO partner_revenue (partner_id, period_type, period_date, clicks, revenue)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            clicks = VALUES(clicks),
            revenue = VALUES(revenue),
            calculated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $partner['partner_id'],
            $period,
            $date,
            $partner['clicks'],
            $partner['revenue']
        ]);
    }
    
    $logger->info("Partner revenue calculated for " . count($partnerStats) . " partners");
}

function generatePartnerPaymentReports(PDO $pdo, string $month, Logger $logger): void
{
    $reportsDir = __DIR__ . '/../reports/payments';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    // Get monthly partner revenue
    $stmt = $pdo->prepare("
        SELECT 
            partner_id,
            SUM(clicks) as total_clicks,
            SUM(revenue) as total_revenue
        FROM partner_revenue
        WHERE period_type = 'daily' 
        AND DATE_FORMAT(period_date, '%Y-%m') = ?
        GROUP BY partner_id
        HAVING total_revenue >= 10.00  -- Minimum payout threshold
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$month]);
    $partnerPayments = $stmt->fetchAll();
    
    $paymentReport = [
        'month' => $month,
        'generated_at' => date('Y-m-d H:i:s'),
        'total_partners' => count($partnerPayments),
        'total_payout' => array_sum(array_column($partnerPayments, 'total_revenue')),
        'payments' => $partnerPayments
    ];
    
    $filename = "{$reportsDir}/partner_payments_{$month}.json";
    file_put_contents($filename, json_encode($paymentReport, JSON_PRETTY_PRINT));
    
    $logger->info("Partner payment report generated: {$filename}");
    $logger->info("Total payout: $" . number_format($paymentReport['total_payout'], 2) . 
                 " for " . count($partnerPayments) . " partners");
}

exit(0);