<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('users.php', 'Invalid request.', 'error');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage('users.php', 'Security token invalid.', 'error');
}

$userId = (int) ($_POST['user_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if (!in_array($newStatus, ['active', 'suspended', 'inactive'])) {
    redirectWithMessage('users.php', 'Invalid status.', 'error');
}

try {
    $pdo = getConnection();
    $pdo->prepare(
        'UPDATE users SET status = ? WHERE id = ?'
    )->execute([$newStatus, $userId]);

    redirectWithMessage('users.php', 'User status updated.', 'success');
} catch (Exception $e) {
    redirectWithMessage('users.php', 'Update failed.', 'error');
}