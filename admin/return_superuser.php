<?php
// admin/return_superuser.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/superuser_helper.php';

// Check if this is a valid superuser session
if (!isSuperuserSession() || !isSuperuserSessionValid()) {
    $_SESSION['msg'] = ['message' => 'Invalid or expired superuser session.', 'type' => 'error'];
    header("Location: ../login.php");
    exit;
}

// Return to superuser portal
if (returnToSuperuser()) {
    $_SESSION['msg'] = ['message' => 'Returned to superuser portal.', 'type' => 'info'];
    header("Location: superuser.php");
} else {
    $_SESSION['msg'] = ['message' => 'Failed to return to superuser portal.', 'type' => 'error'];
    header("Location: ../login.php");
}
exit;
?>