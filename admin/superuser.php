<?php
// admin/superuser.php

# /etc/cron.d/superuser
// */5 * * * * find /path/to/website/logs -name 'superuser_key.txt' -mmin +5 -delete

require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Only the real admin may request superuser keys
requireAdmin('../login.php');

$admin_email = ADMIN_EMAIL;                       // from config.php
$superuser_file = __DIR__ . '/../logs/superuser_key.txt';

// Ensure logs directory exists
$logs_dir = dirname($superuser_file);
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

/* ----------  STEP 1  – generate & mail a 6-digit key  ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['msg'] = ['message' => 'Invalid request.', 'type' => 'error'];
        header("Location: superuser.php");
        exit;
    }
    
    $key = sprintf('%06d', random_int(0, 999999));
    file_put_contents($superuser_file, $key);
    
    // Try multiple email methods
    $email_sent = false;
    $error_msg = '';
    
    // Method 1: Try PHPMailer if available (recommended)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer') && defined('SMTP_HOST')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION ?? 'tls';
            $mail->Port = SMTP_PORT ?? 587;
            
            $mail->setFrom(SITE_EMAIL, SITE_NAME);
            $mail->addAddress($admin_email);
            $mail->Subject = 'Superuser Key - ' . SITE_NAME;
            $mail->Body = "Your superuser key is: $key\nValid for 5 minutes.\n\nRequested from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            
            $mail->send();
            $email_sent = true;
        } catch (Exception $e) {
            $error_msg = "PHPMailer error: " . $e->getMessage();
        }
    }
    
    // Method 2: Try built-in mail() function as fallback
    if (!$email_sent) {
        $subject = 'Superuser Key - ' . SITE_NAME;
        $body = "Your superuser key is: $key\nValid for 5 minutes.\n\nRequested from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        // Improved headers for better deliverability
        $headers = array();
        $headers[] = "From: " . SITE_NAME . " <" . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']) . ">";
        $headers[] = "Reply-To: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
        $headers[] = "Return-Path: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/plain; charset=UTF-8";
        $headers[] = "X-Priority: 1";
        
        $headers_string = implode("\r\n", $headers);
        
        if (mail($admin_email, $subject, $body, $headers_string)) {
            $email_sent = true;
            
            // Additional check - log mail attempts
            error_log("Superuser key mail sent to: $admin_email, Key: $key");
        } else {
            $error = error_get_last();
            $error_msg = "Mail function failed. " . ($error['message'] ?? 'Unknown error');
            error_log("Superuser key mail failed: $error_msg");
        }
    }
    
    // Set appropriate message
    if ($email_sent) {
        $_SESSION['msg'] = ['message' => 'Key sent to ' . $admin_email, 'type' => 'success'];
    } else {
        // For development/testing - show the key directly
        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
            $_SESSION['msg'] = ['message' => "Email failed. Development key: $key (Error: $error_msg)", 'type' => 'warning'];
        } else {
            $_SESSION['msg'] = ['message' => 'Failed to send email. Contact system administrator.', 'type' => 'error'];
            error_log("Superuser email failure: $error_msg");
        }
    }
    
    header("Location: superuser.php");
    exit;
}

/* ----------  STEP 2  – verify entered key  ---------- */
$key_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_key'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $key_error = 'Invalid request.';
    } else {
        $entered = trim($_POST['super_key']);
        $stored = '';
        
        if (file_exists($superuser_file)) {
            $stored = trim(file_get_contents($superuser_file));
            
            // Check if key is expired (5 minutes)
            $key_age = time() - filemtime($superuser_file);
            if ($key_age > 300) { // 5 minutes = 300 seconds
                unlink($superuser_file);
                $stored = '';
            }
        }
        
        if (!empty($entered) && $entered === $stored) {
            $_SESSION['superuser_authenticated'] = true;
            $_SESSION['superuser_time'] = time();
            $_SESSION['msg'] = ['message' => 'Superuser mode unlocked.', 'type' => 'success'];
            header("Location: superuser.php");
            exit;
        } else {
            $key_error = 'Invalid or expired key.';
        }
    }
}

/* ----------  STEP 3  – login-as any user  ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_as'])) {
    // Check superuser authentication and session timeout (30 minutes)
    if (!($_SESSION['superuser_authenticated'] ?? false) || 
        (time() - ($_SESSION['superuser_time'] ?? 0)) > 1800) {
        
        unset($_SESSION['superuser_authenticated'], $_SESSION['superuser_time']);
        $_SESSION['msg'] = ['message' => 'Superuser session expired.', 'type' => 'error'];
        header("Location: superuser.php");
        exit;
    }
    
    $username = trim($_POST['username']);
    if (empty($username)) {
        $key_error = 'Username is required.';
    } else {
        $user = getUserByUsername($username);
        if (!$user) {
            $key_error = 'User not found.';
        } else {
            // Log the superuser access for security audit
            error_log("Superuser access: Admin logged in as user '$username' from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Store superuser session data before switching users
            $superuser_data = [
                'original_admin_id' => $_SESSION['user_id'],
                'original_admin_username' => $_SESSION['username'],
                'superuser_authenticated' => true,
                'superuser_time' => $_SESSION['superuser_time'],
                'target_username' => $username
            ];
            
            // Destroy current session and create new one for target user
            session_destroy();
            session_start();
            setUserSession($user);
            
            // Restore superuser session data
            $_SESSION['superuser_data'] = $superuser_data;
            $_SESSION['is_superuser_session'] = true;
            
            $_SESSION['msg'] = ['message' => "Logged in as $username via superuser", 'type' => 'success'];
            header("Location: ../user/dashboard.php");
            exit;
        }
    }
}

// Auto-logout superuser session after 30 minutes
if (($_SESSION['superuser_authenticated'] ?? false) && 
    (time() - ($_SESSION['superuser_time'] ?? 0)) > 1800) {
    unset($_SESSION['superuser_authenticated'], $_SESSION['superuser_time']);
    $_SESSION['msg'] = ['message' => 'Superuser session expired.', 'type' => 'warning'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superuser Portal - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .card { max-width: 420px; margin: 0 auto; }
        .superuser-header { background: linear-gradient(135deg, #dc3545, #6f42c1); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container">
    <div class="card shadow">
        <div class="card-header superuser-header text-white text-center py-3">
            <h5 class="mb-0"><i class="fas fa-user-shield"></i> Superuser Portal</h5>
            <small>High-level account access</small>
        </div>

        <div class="card-body p-4">
            <?php if ($msg = $_SESSION['msg'] ?? null): ?>
                <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> alert-dismissible">
                    <?= htmlspecialchars($msg['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <?php if ($key_error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($key_error) ?></div>
            <?php endif; ?>

            <?php if (!($_SESSION['superuser_authenticated'] ?? false)): ?>
                <div class="text-center mb-4">
                    <i class="fas fa-shield-alt fa-2x text-muted mb-2"></i>
                    <p class="text-muted small">Generate a secure key to access superuser mode</p>
                    
                    <!--<?php if (defined('SHOW_EMAIL_DEBUG') && SHOW_EMAIL_DEBUG): ?>-->
                    <!--<div class="alert alert-info small">-->
                    <!--    <strong>Email Debug Info:</strong><br>-->
                    <!--    Admin Email: <?= htmlspecialchars($admin_email) ?><br>-->
                    <!--    PHP mail(): <?= function_exists('mail') ? 'Available' : 'Not available' ?><br>-->
                    <!--    PHPMailer: <?= class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'Available' : 'Not available' ?><br>-->
                    <!--    Server: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') ?>-->
                    <!--</div>-->
                    <!--<?php endif; ?>-->
                </div>

                <!-- STEP 1 – generate key -->
                <form method="POST" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <button name="generate_key" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-key me-2"></i> Generate & Send Key
                    </button>
                </form>

                <hr class="my-4">

                <!-- STEP 2 – enter key -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <div class="mb-3">
                        <label for="super_key" class="form-label">Enter 6-digit key</label>
                        <input type="text" 
                               id="super_key" 
                               name="super_key" 
                               maxlength="6" 
                               pattern="\d{6}"
                               class="form-control form-control-lg text-center" 
                               placeholder="000000" 
                               autocomplete="off"
                               required>
                        <div class="form-text">Key expires in 5 minutes</div>
                    </div>
                    <button name="verify_key" class="btn btn-success w-100 py-2">
                        <i class="fas fa-check me-2"></i> Verify Key
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center mb-4">
                    <i class="fas fa-shield-check fa-2x text-success mb-2"></i>
                    <p class="text-success"><strong>Superuser Mode Active</strong></p>
                    <small class="text-muted">You can now login as any user</small>
                </div>

                <!-- STEP 3 – username login -->
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username to Login As</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Enter username"
                               autocomplete="off"
                               required>
                    </div>
                    <button name="login_as" class="btn btn-danger w-100 py-2">
                        <i class="fas fa-sign-in-alt me-2"></i> Login Without Password
                    </button>
                </form>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="../admin/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Admin
                    </a>
                    <a href="../logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Exit & Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer text-center text-muted py-2">
            <small><i class="fas fa-exclamation-triangle me-1"></i> Use with extreme caution</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-focus on key input when page loads
document.addEventListener('DOMContentLoaded', function() {
    const keyInput = document.getElementById('super_key');
    const usernameInput = document.getElementById('username');
    
    if (keyInput) {
        keyInput.focus();
        // Format input as user types
        keyInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    } else if (usernameInput) {
        usernameInput.focus();
    }
});
</script>
</body>
</html>