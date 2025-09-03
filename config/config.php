<?php
// System Configuration
// config/config.php

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SHOW_EMAIL_DEBUG', true);  // Remove after testing
define('ENVIRONMENT', 'development'); // Shows key directly if email fails

// Currency settings
if (!defined('DEFAULT_CURRENCY')) define('DEFAULT_CURRENCY', 'USDT');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'USDT');
if (!defined('DECIMAL_PLACES')) define('DECIMAL_PLACES', 2);

// Site configuration
if (!defined('SITE_NAME')) define('SITE_NAME', 'TokenMining');
if (!defined('SITE_URL')) define('SITE_URL', 'http://jojo1.test');
if (!defined('BASE_PATH')) define('BASE_PATH', __DIR__ . '/../');

// Package configuration
if (!defined('PACKAGES')) define('PACKAGES', [
    1 => ['name' => 'Starter Plan', 'price' => 20.00],
    2 => ['name' => 'Bronze Plan', 'price' => 100.00],
    3 => ['name' => 'Silver Plan', 'price' => 500.00],
    4 => ['name' => 'Gold Plan', 'price' => 1000.00],
    5 => ['name' => 'Platinum Plan', 'price' => 2000.00],
    6 => ['name' => 'Diamond Plan', 'price' => 10000.00]
]);

// Bonus configuration
if (!defined('MONTHLY_BONUS_PERCENTAGE')) define('MONTHLY_BONUS_PERCENTAGE', 50); // 50% of package price
if (!defined('BONUS_MONTHS')) define('BONUS_MONTHS', 3); // Number of months to receive bonus
if (!defined('BONUS_DAYS')) define('BONUS_DAYS', 90);   // 90 days for daily packages
if (!defined('REFERRAL_BONUSES')) define('REFERRAL_BONUSES', [
    2 => 10, // Level 2: 10%
    3 => 1,  // Level 3: 1%
    4 => 1,  // Level 4: 1%
    5 => 1   // Level 5: 1%
]);

// Security configuration
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 6);
if (!defined('USERNAME_MIN_LENGTH')) define('USERNAME_MIN_LENGTH', 3);
if (!defined('USERNAME_MAX_LENGTH')) define('USERNAME_MAX_LENGTH', 50);
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// Pagination
if (!defined('RECORDS_PER_PAGE')) define('RECORDS_PER_PAGE', 20);

// File upload limits
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Email configuration (for future use)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'noreply@ojotokenmining.com');
if (!defined('FROM_NAME')) define('FROM_NAME', 'OjoTokenMining');

// Admin settings
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'jvsalidaga88@gmail.com'); // ragnarvion@gmail.com // jvsalidaga88@gmail.com
if (!defined('SITE_EMAIL')) define('SITE_EMAIL', 'support@btc3.site');

/**
 * Get package information by ID
 * @param int $package_id Package ID
 * @return array|null Package information
 */
function getPackageInfo($package_id)
{
    $packages = PACKAGES;
    return isset($packages[$package_id]) ? $packages[$package_id] : null;
}

/**
 * Format currency amount
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatCurrency($amount)
{
    return number_format($amount, DECIMAL_PLACES) . ' USDT' /*CURRENCY_SYMBOL*/;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION)) session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken($token)
{
    if (!isset($_SESSION)) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info')
{
    if (!isset($_SESSION)) session_start();
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 * @return array|null Message array with 'message' and 'type' keys
 */
function getFlashMessage()
{
    if (!isset($_SESSION)) session_start();
    if (isset($_SESSION['message'])) {
        $message = [
            'message' => $_SESSION['message'],
            'type' => $_SESSION['message_type'] ?? 'info'
        ];
        unset($_SESSION['message'], $_SESSION['message_type']);
        return $message;
    }
    return null;
}

/**
 * Log system events
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 */
function logEvent($message, $level = 'info')
{
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(BASE_PATH . 'logs/system.log', $log_message, FILE_APPEND | LOCK_EX);
}

// Create logs directory if it doesn't exist
$logs_dir = BASE_PATH . 'logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}
