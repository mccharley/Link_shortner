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
$logger = new Logger('log-cleaner');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$logger->info('Starting log cleanup process...');

try {
    $action = $argv[1] ?? 'rotate'; // rotate, clear, compress, archive
    $daysToKeep = (int)($argv[2] ?? 30);
    
    $logsDir = __DIR__ . '/../logs';
    
    if (!is_dir($logsDir)) {
        $logger->warning("Logs directory does not exist: {$logsDir}");
        exit(0);
    }
    
    $logger->info("Processing logs with action '{$action}', keeping {$daysToKeep} days");
    
    switch ($action) {
        case 'rotate':
            rotateLogs($logsDir, $daysToKeep, $logger);
            break;
        case 'clear':
            clearOldLogs($logsDir, $daysToKeep, $logger);
            break;
        case 'compress':
            compressOldLogs($logsDir, $daysToKeep, $logger);
            break;
        case 'archive':
            archiveLogs($logsDir, $daysToKeep, $logger);
            break;
        default:
            $logger->error("Unknown action: {$action}");
            $logger->info("Available actions: rotate, clear, compress, archive");
            exit(1);
    }
    
    // Clean up empty log files
    cleanupEmptyLogs($logsDir, $logger);
    
    // Generate log summary
    generateLogSummary($logsDir, $logger);
    
    $logger->info('Log cleanup process completed successfully');
    
} catch (Exception $e) {
    $logger->error('Log cleanup failed: ' . $e->getMessage());
    exit(1);
}

function rotateLogs(string $logsDir, int $daysToKeep, Logger $logger): void
{
    $logger->info("Rotating log files...");
    
    $logFiles = glob("{$logsDir}/*.log");
    $rotatedCount = 0;
    $totalSize = 0;
    
    foreach ($logFiles as $logFile) {
        $fileInfo = pathinfo($logFile);
        $baseName = $fileInfo['filename'];
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedFile = "{$logsDir}/{$baseName}_{timestamp}.log";
        
        // Only rotate if file is not empty and older than 1 day
        if (filesize($logFile) > 0 && (time() - filemtime($logFile)) > 86400) {
            if (rename($logFile, $rotatedFile)) {
                $size = filesize($rotatedFile);
                $totalSize += $size;
                $rotatedCount++;
                
                $logger->info("Rotated: {$baseName}.log -> {$baseName}_{timestamp}.log (" . formatBytes($size) . ")");
                
                // Create new empty log file
                touch($logFile);
                chmod($logFile, 0644);
                
                // Compress the rotated file
                compressLogFile($rotatedFile, $logger);
            }
        }
    }
    
    if ($rotatedCount > 0) {
        $logger->info("Rotated {$rotatedCount} log files (" . formatBytes($totalSize) . " total)");
    } else {
        $logger->info("No log files needed rotation");
    }
    
    // Clean up old rotated logs
    cleanupOldRotatedLogs($logsDir, $daysToKeep, $logger);
}

function clearOldLogs(string $logsDir, int $daysToKeep, Logger $logger): void
{
    $logger->info("Clearing old log files (keeping {$daysToKeep} days)...");
    
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $logFiles = glob("{$logsDir}/*.log*");
    $deletedCount = 0;
    $deletedSize = 0;
    
    foreach ($logFiles as $logFile) {
        if (filemtime($logFile) < $cutoffTime) {
            $size = filesize($logFile);
            if (unlink($logFile)) {
                $deletedCount++;
                $deletedSize += $size;
                $logger->info("Deleted old log: " . basename($logFile) . " (" . formatBytes($size) . ")");
            }
        }
    }
    
    if ($deletedCount > 0) {
        $logger->info("Deleted {$deletedCount} old log files (" . formatBytes($deletedSize) . " total)");
    } else {
        $logger->info("No old log files to delete");
    }
}

function compressOldLogs(string $logsDir, int $daysToKeep, Logger $logger): void
{
    $logger->info("Compressing old log files (older than {$daysToKeep} days)...");
    
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $logFiles = glob("{$logsDir}/*.log");
    $compressedCount = 0;
    $spaceSaved = 0;
    
    foreach ($logFiles as $logFile) {
        if (filemtime($logFile) < $cutoffTime && filesize($logFile) > 0) {
            $originalSize = filesize($logFile);
            
            if (compressLogFile($logFile, $logger)) {
                $compressedSize = filesize($logFile . '.gz');
                $spaceSaved += ($originalSize - $compressedSize);
                $compressedCount++;
                
                // Remove original file after successful compression
                unlink($logFile);
            }
        }
    }
    
    if ($compressedCount > 0) {
        $logger->info("Compressed {$compressedCount} log files (saved " . formatBytes($spaceSaved) . ")");
    } else {
        $logger->info("No log files needed compression");
    }
}

function archiveLogs(string $logsDir, int $daysToKeep, Logger $logger): void
{
    $logger->info("Archiving old log files...");
    
    $archiveDir = "{$logsDir}/archive";
    if (!is_dir($archiveDir)) {
        mkdir($archiveDir, 0755, true);
    }
    
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $logFiles = glob("{$logsDir}/*.log*");
    $archivedCount = 0;
    $archivedSize = 0;
    
    foreach ($logFiles as $logFile) {
        $fileName = basename($logFile);
        
        // Skip files already in archive directory
        if (strpos($logFile, '/archive/') !== false) {
            continue;
        }
        
        if (filemtime($logFile) < $cutoffTime) {
            $archiveFile = "{$archiveDir}/{$fileName}";
            $size = filesize($logFile);
            
            if (rename($logFile, $archiveFile)) {
                $archivedCount++;
                $archivedSize += $size;
                $logger->info("Archived: {$fileName} (" . formatBytes($size) . ")");
            }
        }
    }
    
    if ($archivedCount > 0) {
        $logger->info("Archived {$archivedCount} log files (" . formatBytes($archivedSize) . " total)");
        
        // Create archive index
        createArchiveIndex($archiveDir, $logger);
    } else {
        $logger->info("No log files needed archiving");
    }
}

function compressLogFile(string $logFile, Logger $logger): bool
{
    if (!file_exists($logFile) || filesize($logFile) === 0) {
        return false;
    }
    
    $compressedFile = $logFile . '.gz';
    
    // Use gzip command if available
    if (command_exists('gzip')) {
        exec("gzip -c " . escapeshellarg($logFile) . " > " . escapeshellarg($compressedFile), $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($compressedFile) && filesize($compressedFile) > 0) {
            return true;
        }
    }
    
    // Fallback to PHP gzip functions
    $input = fopen($logFile, 'rb');
    $output = gzopen($compressedFile, 'wb9');
    
    if ($input && $output) {
        while (!feof($input)) {
            gzwrite($output, fread($input, 8192));
        }
        
        fclose($input);
        gzclose($output);
        
        return file_exists($compressedFile) && filesize($compressedFile) > 0;
    }
    
    return false;
}

function cleanupEmptyLogs(string $logsDir, Logger $logger): void
{
    $logger->info("Cleaning up empty log files...");
    
    $logFiles = glob("{$logsDir}/*.log");
    $deletedCount = 0;
    
    foreach ($logFiles as $logFile) {
        if (filesize($logFile) === 0 && (time() - filemtime($logFile)) > 3600) { // 1 hour old
            if (unlink($logFile)) {
                $deletedCount++;
                $logger->info("Deleted empty log: " . basename($logFile));
            }
        }
    }
    
    if ($deletedCount > 0) {
        $logger->info("Deleted {$deletedCount} empty log files");
    }
}

function cleanupOldRotatedLogs(string $logsDir, int $daysToKeep, Logger $logger): void
{
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $rotatedFiles = glob("{$logsDir}/*_????-??-??_??-??-??.log*");
    $deletedCount = 0;
    $deletedSize = 0;
    
    foreach ($rotatedFiles as $rotatedFile) {
        if (filemtime($rotatedFile) < $cutoffTime) {
            $size = filesize($rotatedFile);
            if (unlink($rotatedFile)) {
                $deletedCount++;
                $deletedSize += $size;
            }
        }
    }
    
    if ($deletedCount > 0) {
        $logger->info("Deleted {$deletedCount} old rotated log files (" . formatBytes($deletedSize) . ")");
    }
}

function generateLogSummary(string $logsDir, Logger $logger): void
{
    $summary = [
        'generated_at' => date('Y-m-d H:i:s'),
        'logs_directory' => $logsDir,
        'files' => []
    ];
    
    $logFiles = glob("{$logsDir}/*.log*");
    $totalSize = 0;
    
    foreach ($logFiles as $logFile) {
        $fileName = basename($logFile);
        $size = filesize($logFile);
        $totalSize += $size;
        
        $summary['files'][] = [
            'name' => $fileName,
            'size' => $size,
            'size_formatted' => formatBytes($size),
            'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'type' => pathinfo($logFile, PATHINFO_EXTENSION)
        ];
    }
    
    $summary['total_files'] = count($logFiles);
    $summary['total_size'] = $totalSize;
    $summary['total_size_formatted'] = formatBytes($totalSize);
    
    // Save summary
    $summaryFile = "{$logsDir}/log_summary.json";
    file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT));
    
    $logger->info("Log summary generated: {$summaryFile}");
    $logger->info("Total: " . count($logFiles) . " files (" . formatBytes($totalSize) . ")");
}

function createArchiveIndex(string $archiveDir, Logger $logger): void
{
    $index = [
        'created_at' => date('Y-m-d H:i:s'),
        'archive_directory' => $archiveDir,
        'files' => []
    ];
    
    $archiveFiles = glob("{$archiveDir}/*");
    
    foreach ($archiveFiles as $archiveFile) {
        if (is_file($archiveFile)) {
            $index['files'][] = [
                'name' => basename($archiveFile),
                'size' => filesize($archiveFile),
                'archived_at' => date('Y-m-d H:i:s', filemtime($archiveFile))
            ];
        }
    }
    
    file_put_contents("{$archiveDir}/archive_index.json", json_encode($index, JSON_PRETTY_PRINT));
}

function command_exists(string $command): bool
{
    $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
    return !empty($return);
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

exit(0);