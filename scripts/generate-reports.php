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
$logger = new Logger('reports');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/reports.log', Logger::INFO));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting report generation...');

try {
    $pdo = Database::getConnection();
    $reportType = $argv[1] ?? 'daily'; // daily, weekly, monthly
    $date = $argv[2] ?? date('Y-m-d');
    
    // Create reports directory if it doesn't exist
    $reportsDir = __DIR__ . '/../reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    $logger->info("Generating {$reportType} report for {$date}");
    
    switch ($reportType) {
        case 'daily':
            generateDailyReport($pdo, $date, $reportsDir, $logger);
            break;
        case 'weekly':
            generateWeeklyReport($pdo, $date, $reportsDir, $logger);
            break;
        case 'monthly':
            generateMonthlyReport($pdo, $date, $reportsDir, $logger);
            break;
        default:
            $logger->error("Unknown report type: {$reportType}");
            exit(1);
    }
    
    $logger->info('Report generation completed successfully');
    
} catch (Exception $e) {
    $logger->error('Report generation failed: ' . $e->getMessage());
    exit(1);
}

function generateDailyReport(PDO $pdo, string $date, string $reportsDir, Logger $logger): void
{
    $reportData = [];
    
    // Total links created today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM links WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $reportData['links_created'] = $stmt->fetch()['count'];
    
    // Total clicks today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM click_analytics WHERE DATE(clicked_at) = ?");
    $stmt->execute([$date]);
    $reportData['total_clicks'] = $stmt->fetch()['count'];
    
    // Unique visitors today
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as count FROM click_analytics WHERE DATE(clicked_at) = ?");
    $stmt->execute([$date]);
    $reportData['unique_visitors'] = $stmt->fetch()['count'];
    
    // Top 10 most clicked links today
    $stmt = $pdo->prepare("
        SELECT l.short_code, l.original_url, COUNT(ca.id) as clicks
        FROM links l
        JOIN click_analytics ca ON l.id = ca.link_id
        WHERE DATE(ca.clicked_at) = ?
        GROUP BY l.id
        ORDER BY clicks DESC
        LIMIT 10
    ");
    $stmt->execute([$date]);
    $reportData['top_links'] = $stmt->fetchAll();
    
    // Top countries
    $stmt = $pdo->prepare("
        SELECT country, COUNT(*) as clicks
        FROM click_analytics
        WHERE DATE(clicked_at) = ? AND country IS NOT NULL
        GROUP BY country
        ORDER BY clicks DESC
        LIMIT 10
    ");
    $stmt->execute([$date]);
    $reportData['top_countries'] = $stmt->fetchAll();
    
    // Browser statistics
    $stmt = $pdo->prepare("
        SELECT browser, COUNT(*) as count
        FROM click_analytics
        WHERE DATE(clicked_at) = ? AND browser IS NOT NULL
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$date]);
    $reportData['browsers'] = $stmt->fetchAll();
    
    // Save report
    $filename = "{$reportsDir}/daily_report_{$date}.json";
    file_put_contents($filename, json_encode($reportData, JSON_PRETTY_PRINT));
    
    // Generate HTML report
    generateHtmlReport($reportData, $date, 'Daily', "{$reportsDir}/daily_report_{$date}.html");
    
    $logger->info("Daily report saved to {$filename}");
}

function generateWeeklyReport(PDO $pdo, string $date, string $reportsDir, Logger $logger): void
{
    $weekStart = date('Y-m-d', strtotime($date . ' -6 days'));
    $reportData = [];
    
    // Weekly statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as links_created,
            COUNT(ca.id) as total_clicks,
            COUNT(DISTINCT ca.ip_address) as unique_visitors
        FROM links l
        LEFT JOIN click_analytics ca ON l.id = ca.link_id
        WHERE l.created_at >= ? AND l.created_at <= ?
    ");
    $stmt->execute([$weekStart, $date . ' 23:59:59']);
    $reportData['summary'] = $stmt->fetch();
    
    // Daily breakdown
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as links_created
        FROM links
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$weekStart, $date . ' 23:59:59']);
    $reportData['daily_links'] = $stmt->fetchAll();
    
    // Top performing links of the week
    $stmt = $pdo->prepare("
        SELECT l.short_code, l.original_url, COUNT(ca.id) as clicks
        FROM links l
        JOIN click_analytics ca ON l.id = ca.link_id
        WHERE ca.clicked_at >= ? AND ca.clicked_at <= ?
        GROUP BY l.id
        ORDER BY clicks DESC
        LIMIT 20
    ");
    $stmt->execute([$weekStart, $date . ' 23:59:59']);
    $reportData['top_links'] = $stmt->fetchAll();
    
    $filename = "{$reportsDir}/weekly_report_{$weekStart}_to_{$date}.json";
    file_put_contents($filename, json_encode($reportData, JSON_PRETTY_PRINT));
    
    generateHtmlReport($reportData, "{$weekStart} to {$date}", 'Weekly', "{$reportsDir}/weekly_report_{$weekStart}_to_{$date}.html");
    
    $logger->info("Weekly report saved to {$filename}");
}

function generateMonthlyReport(PDO $pdo, string $date, string $reportsDir, Logger $logger): void
{
    $month = date('Y-m', strtotime($date));
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    $reportData = [];
    
    // Monthly statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as links_created,
            COUNT(ca.id) as total_clicks,
            COUNT(DISTINCT ca.ip_address) as unique_visitors,
            COUNT(DISTINCT ca.country) as countries_reached
        FROM links l
        LEFT JOIN click_analytics ca ON l.id = ca.link_id
        WHERE l.created_at >= ? AND l.created_at <= ?
    ");
    $stmt->execute([$monthStart, $monthEnd . ' 23:59:59']);
    $reportData['summary'] = $stmt->fetch();
    
    // Daily breakdown for the month
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as links_created
        FROM links
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$monthStart, $monthEnd . ' 23:59:59']);
    $reportData['daily_breakdown'] = $stmt->fetchAll();
    
    // Top domains
    $stmt = $pdo->prepare("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain')) as domain,
            COUNT(*) as link_count,
            SUM(click_count) as total_clicks
        FROM links
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.domain'))
        ORDER BY total_clicks DESC
        LIMIT 15
    ");
    $stmt->execute([$monthStart, $monthEnd . ' 23:59:59']);
    $reportData['top_domains'] = $stmt->fetchAll();
    
    $filename = "{$reportsDir}/monthly_report_{$month}.json";
    file_put_contents($filename, json_encode($reportData, JSON_PRETTY_PRINT));
    
    generateHtmlReport($reportData, $month, 'Monthly', "{$reportsDir}/monthly_report_{$month}.html");
    
    $logger->info("Monthly report saved to {$filename}");
}

function generateHtmlReport(array $data, string $period, string $type, string $filename): void
{
    $html = "<!DOCTYPE html>
<html>
<head>
    <title>{$type} Report - {$period}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f4f4f4; padding: 20px; border-radius: 5px; }
        .stat-box { display: inline-block; margin: 10px; padding: 15px; background: #e9e9e9; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>{$type} Report</h1>
        <h2>Period: {$period}</h2>
    </div>";
    
    if (isset($data['summary'])) {
        $html .= "<div class='summary'>";
        foreach ($data['summary'] as $key => $value) {
            $html .= "<div class='stat-box'><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> {$value}</div>";
        }
        $html .= "</div>";
    }
    
    if (isset($data['top_links'])) {
        $html .= "<h3>Top Links</h3><table><tr><th>Short Code</th><th>Original URL</th><th>Clicks</th></tr>";
        foreach ($data['top_links'] as $link) {
            $html .= "<tr><td>{$link['short_code']}</td><td>" . htmlspecialchars($link['original_url']) . "</td><td>{$link['clicks']}</td></tr>";
        }
        $html .= "</table>";
    }
    
    $html .= "</body></html>";
    
    file_put_contents($filename, $html);
}

exit(0);
