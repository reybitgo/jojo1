<?php
// Session Configuration
// config/session.php

// Configure session settings only if session is not active
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Session configuration
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
}

/**
 * Initialize secure session
 */
function initSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        destroySession();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check if user is logged in
 * @return bool True if logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user is admin
 * @return bool True if admin
 */
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get current user ID
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * @return string|null Username or null if not logged in
 */
function getCurrentUsername()
{
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user role
 * @return string|null User role or null if not logged in
 */
function getCurrentUserRole()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Set user session data
 * @param array $user User data
 */
function setUserSession($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * Destroy session and logout user
 */
function destroySession()
{
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Require user to be logged in
 * @param string $redirect_url URL to redirect to if not logged in
 */
function requireLogin($redirect_url = 'login.php')
{
    if (!initSession() || !isLoggedIn()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Require admin privileges
 * @param string $redirect_url URL to redirect to if not admin
 */
function requireAdmin($redirect_url = 'login.php')
{
    requireLogin($redirect_url);
    if (!isAdmin()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Prevent logged in users from accessing auth pages
 * @param string $redirect_url URL to redirect to if logged in
 */
function preventLoggedInAccess($redirect_url = 'user/dashboard.php')
{
    if (initSession() && isLoggedIn()) {
        if (isAdmin()) {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: $redirect_url");
        }
        exit;
    }
}

/**
 * Get user's login duration
 * @return int Login duration in seconds
 */
function getLoginDuration()
{
    return isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : 0;
}

/**
 * Check if session is about to expire
 * @param int $warning_time Warning time in seconds before expiry
 * @return bool True if session is about to expire
 */
function isSessionExpiring($warning_time = 300)
{ // 5 minutes warning
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    $time_left = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    return $time_left <= $warning_time;
}

/**
 * Get time left in session
 * @return int Time left in seconds
 */
function getSessionTimeLeft()
{
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }

    return max(0, SESSION_TIMEOUT - (time() - $_SESSION['last_activity']));
}

// Initialize session
initSession();
?>