<?php
// Logout Page
// logout.php

require_once 'config/config.php';
require_once 'config/session.php';

// Log the logout event if user was logged in
if (isLoggedIn()) {
    $username = getCurrentUsername();
    logEvent("User logout: $username", 'info');
}

// Destroy session and logout user
destroySession();

// Redirect to login page with logout message
redirectWithMessage('login.php', 'You have been successfully logged out.', 'success');
?>