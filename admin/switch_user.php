<?php
// admin/switch_user.php
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

$error_msg = '';
$users = [];

// Get all users for the dropdown
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users for superuser: " . $e->getMessage());
}

// Handle user switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_to'])) {
    $target_username = trim($_POST['switch_to']);
    
    if (empty($target_username)) {
        $error_msg = 'Please select a user.';
    } else {
        $user = getUserByUsername($target_username);
        if (!$user) {
            $error_msg = 'User not found.';
        } else {
            // Get current superuser data
            $superuser_data = getSuperuserData();
            $superuser_data['target_username'] = $target_username;
            
            // Switch to new user
            session_destroy();
            session_start();
            setUserSession($user);
            
            // Restore superuser session data
            $_SESSION['superuser_data'] = $superuser_data;
            $_SESSION['is_superuser_session'] = true;
            
            $_SESSION['msg'] = ['message' => "Switched to user: $target_username", 'type' => 'success'];
            header("Location: ../user/dashboard.php");
            exit;
        }
    }
}

$current_superuser_data = getSuperuserData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch User - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .card { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center py-3">
            <h5 class="mb-0"><i class="fas fa-user-friends"></i> Quick User Switch</h5>
            <small>Currently viewing as: <strong><?= htmlspecialchars($current_superuser_data['target_username'] ?? 'Unknown') ?></strong></small>
        </div>

        <div class="card-body p-4">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="switch_to" class="form-label">Switch to User:</label>
                    <select id="switch_to" name="switch_to" class="form-select" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['username']) ?>">
                                <?= htmlspecialchars($user['username']) ?> 
                                (<?= htmlspecialchars($user['email']) ?>) 
                                - <?= htmlspecialchars($user['role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-exchange-alt me-2"></i> Switch User
                    </button>
                    <a href="return_superuser.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Superuser Portal
                    </a>
                    <a href="../logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Exit All
                    </a>
                </div>
            </form>
        </div>

        <div class="card-footer text-center text-muted py-2">
            <small>
                <i class="fas fa-user-shield me-1"></i> 
                Admin: <?= htmlspecialchars($current_superuser_data['original_admin_username'] ?? 'Unknown') ?>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Add search functionality to user dropdown
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('switch_to');
    select.addEventListener('click', function() {
        // Focus for easier keyboard navigation
        this.focus();
    });
});
</script>
</body>
</html>