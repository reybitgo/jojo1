<?php
// includes/superuser_helper.php

/**
 * Check if current session is a superuser session
 */
function isSuperuserSession() {
    return isset($_SESSION['is_superuser_session']) && $_SESSION['is_superuser_session'] === true;
}

/**
 * Check if superuser session is still valid
 */
function isSuperuserSessionValid() {
    if (!isSuperuserSession() || !isset($_SESSION['superuser_data'])) {
        return false;
    }
    
    $superuser_time = $_SESSION['superuser_data']['superuser_time'] ?? 0;
    return (time() - $superuser_time) <= 1800; // 30 minutes
}

/**
 * Get superuser session data
 */
function getSuperuserData() {
    return $_SESSION['superuser_data'] ?? null;
}

/**
 * Return to superuser portal (admin login)
 */
function returnToSuperuser() {
    if (!isSuperuserSession()) {
        return false;
    }
    
    $superuser_data = getSuperuserData();
    if (!$superuser_data) {
        return false;
    }
    
    // Get original admin user data
    $admin_user = getUserById($superuser_data['original_admin_id']);
    if (!$admin_user) {
        return false;
    }
    
    // Destroy current session and restore admin session
    session_destroy();
    session_start();
    setUserSession($admin_user);
    
    // Restore superuser authentication
    $_SESSION['superuser_authenticated'] = $superuser_data['superuser_authenticated'];
    $_SESSION['superuser_time'] = $superuser_data['superuser_time'];
    
    return true;
}

/**
 * Display superuser banner for users being impersonated
 */
function displaySuperuserBanner() {
    if (!isSuperuserSession() || !isSuperuserSessionValid()) {
        return '';
    }
    
    $superuser_data = getSuperuserData();
    $target_user = $superuser_data['target_username'] ?? 'Unknown';
    $admin_user = $superuser_data['original_admin_username'] ?? 'Admin';
    
    return '
    <div class="alert alert-warning alert-dismissible d-flex align-items-center mb-0 border-0 rounded-0" style="background: linear-gradient(135deg, #ff6b35, #f7931e);">
        <i class="fas fa-user-shield me-2"></i>
        <div class="flex-grow-1">
            <strong>Superuser Mode Active</strong> - 
            Admin "<strong>' . htmlspecialchars($admin_user) . '</strong>" viewing as "<strong>' . htmlspecialchars($target_user) . '</strong>"
        </div>
        <div class="btn-group btn-group-sm ms-3">
            <a href="' . SITE_URL . '/admin/return_superuser.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Return to Superuser
            </a>
            <a href="' . SITE_URL . '/admin/switch_user.php" class="btn btn-light btn-sm">
                <i class="fas fa-user-friends me-1"></i> Switch User
            </a>
            <a href="' . SITE_URL . '/logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Exit All
            </a>
        </div>
    </div>';
}

/**
 * Clean expired superuser sessions
 */
function cleanExpiredSuperuserSessions() {
    if (isSuperuserSession() && !isSuperuserSessionValid()) {
        unset($_SESSION['is_superuser_session'], $_SESSION['superuser_data']);
        $_SESSION['msg'] = ['message' => 'Superuser session expired.', 'type' => 'warning'];
        return false;
    }
    return true;
}
?>