<?php
/**
 * Cron Test Script
 * Run this every minute to test if cron is working
 * Usage: php /path/to/cron_test.php
 */

// Set timezone (adjust to your timezone)
date_default_timezone_set('Asia/Manila');

// Create log file path (adjust path as needed)
$logFile = __DIR__ . '/cron_test.log';

// Current timestamp
$timestamp = date('Y-m-d H:i:s');
$message = "Cron executed at: $timestamp" . PHP_EOL;

// Write to log file
if (file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX) !== false) {
    echo "Success: Logged at $timestamp\n";
} else {
    echo "Error: Could not write to log file\n";
}

// Optional: Keep only last 100 entries to prevent log from growing too large
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (count($lines) > 100) {
    $recentLines = array_slice($lines, -100);
    file_put_contents($logFile, implode(PHP_EOL, $recentLines) . PHP_EOL);
    echo "Log file trimmed to last 100 entries\n";
}

// Optional: Also write to system log
error_log("Cron test executed at $timestamp");

?>