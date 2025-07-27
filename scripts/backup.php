#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Setup logger
$logger = new Logger('backup');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/backup.log', Logger::INFO));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting backup process...');

try {
    $backupType = $argv[1] ?? 'full'; // full, database, files
    $timestamp = date('Y-m-d_H-i-s');
    $backupDir = __DIR__ . '/../backups';
    
    // Create backup directory if it doesn't exist
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $logger->info("Starting {$backupType} backup with timestamp {$timestamp}");
    
    switch ($backupType) {
        case 'full':
            backupDatabase($backupDir, $timestamp, $logger);
            backupFiles($backupDir, $timestamp, $logger);
            break;
        case 'database':
            backupDatabase($backupDir, $timestamp, $logger);
            break;
        case 'files':
            backupFiles($backupDir, $timestamp, $logger);
            break;
        default:
            $logger->error("Unknown backup type: {$backupType}");
            exit(1);
    }
    
    // Cleanup old backups (keep last 7 days)
    cleanupOldBackups($backupDir, $logger);
    
    $logger->info('Backup process completed successfully');
    
} catch (Exception $e) {
    $logger->error('Backup failed: ' . $e->getMessage());
    exit(1);
}

function backupDatabase(string $backupDir, string $timestamp, Logger $logger): void
{
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbPort = $_ENV['DB_PORT'] ?? '3306';
    $dbName = $_ENV['DB_NAME'] ?? 'linkshortener';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';
    
    $backupFile = "{$backupDir}/database_backup_{$timestamp}.sql";
    $gzipFile = "{$backupFile}.gz";
    
    $logger->info("Creating database backup: {$backupFile}");
    
    // Create mysqldump command
    $command = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers --events %s > %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbPort),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    // Execute backup
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Database backup failed with return code: {$returnCode}");
    }
    
    // Verify backup file exists and has content
    if (!file_exists($backupFile) || filesize($backupFile) === 0) {
        throw new Exception("Database backup file is empty or doesn't exist");
    }
    
    // Compress the backup
    $logger->info("Compressing database backup...");
    exec("gzip {$backupFile}", $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($gzipFile)) {
        $size = formatBytes(filesize($gzipFile));
        $logger->info("Database backup completed and compressed: {$gzipFile} ({$size})");
        
        // Create backup metadata
        $metadata = [
            'type' => 'database',
            'timestamp' => $timestamp,
            'file' => $gzipFile,
            'size' => filesize($gzipFile),
            'database' => $dbName,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents("{$gzipFile}.meta", json_encode($metadata, JSON_PRETTY_PRINT));
    } else {
        $logger->warning("Database backup compression failed, keeping uncompressed file");
    }
}

function backupFiles(string $backupDir, string $timestamp, Logger $logger): void
{
    $projectRoot = dirname(__DIR__);
    $backupFile = "{$backupDir}/files_backup_{$timestamp}.tar.gz";
    
    $logger->info("Creating files backup: {$backupFile}");
    
    // Files and directories to include
    $includeItems = [
        'src/',
        'public/',
        'templates/',
        'includes/',
        'scripts/',
        'logs/',
        'reports/',
        '.env',
        '.env.example',
        'composer.json',
        'composer.lock',
        'README.md',
        'LICENSE'
    ];
    
    // Files and directories to exclude
    $excludeItems = [
        'vendor/',
        'backups/',
        '.git/',
        'node_modules/',
        'temp/',
        'cache/',
        '*.log'
    ];
    
    // Build tar command
    $includeArgs = array_map(function($item) {
        return escapeshellarg($item);
    }, $includeItems);
    
    $excludeArgs = array_map(function($item) {
        return "--exclude=" . escapeshellarg($item);
    }, $excludeItems);
    
    $command = sprintf(
        'cd %s && tar %s -czf %s %s',
        escapeshellarg($projectRoot),
        implode(' ', $excludeArgs),
        escapeshellarg($backupFile),
        implode(' ', $includeArgs)
    );
    
    // Execute backup
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception("Files backup failed with return code: {$returnCode}");
    }
    
    // Verify backup file exists and has content
    if (!file_exists($backupFile) || filesize($backupFile) === 0) {
        throw new Exception("Files backup file is empty or doesn't exist");
    }
    
    $size = formatBytes(filesize($backupFile));
    $logger->info("Files backup completed: {$backupFile} ({$size})");
    
    // Create backup metadata
    $metadata = [
        'type' => 'files',
        'timestamp' => $timestamp,
        'file' => $backupFile,
        'size' => filesize($backupFile),
        'included_items' => $includeItems,
        'excluded_items' => $excludeItems,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents("{$backupFile}.meta", json_encode($metadata, JSON_PRETTY_PRINT));
}

function cleanupOldBackups(string $backupDir, Logger $logger): void
{
    $logger->info("Cleaning up old backups...");
    
    $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 days ago
    $deletedCount = 0;
    $deletedSize = 0;
    
    $files = glob("{$backupDir}/*");
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoffTime) {
            $size = filesize($file);
            if (unlink($file)) {
                $deletedCount++;
                $deletedSize += $size;
                $logger->info("Deleted old backup: " . basename($file));
            }
        }
    }
    
    if ($deletedCount > 0) {
        $sizeFormatted = formatBytes($deletedSize);
        $logger->info("Cleanup completed: Deleted {$deletedCount} old backup files ({$sizeFormatted})");
    } else {
        $logger->info("No old backups to clean up");
    }
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function verifyBackup(string $backupFile, Logger $logger): bool
{
    if (!file_exists($backupFile)) {
        $logger->error("Backup file does not exist: {$backupFile}");
        return false;
    }
    
    if (filesize($backupFile) === 0) {
        $logger->error("Backup file is empty: {$backupFile}");
        return false;
    }
    
    // For database backups, verify it's a valid SQL file
    if (strpos($backupFile, 'database_backup') !== false) {
        $handle = fopen($backupFile, 'r');
        $firstLine = fgets($handle);
        fclose($handle);
        
        if (strpos($firstLine, 'MySQL dump') === false && strpos($firstLine, 'mysqldump') === false) {
            $logger->error("Database backup file appears to be invalid: {$backupFile}");
            return false;
        }
    }
    
    $logger->info("Backup verification passed: {$backupFile}");
    return true;
}

exit(0);