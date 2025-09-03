<?php
// forgot_password.php - Updated version with email debugging
require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$errors = [];
$success = '';
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === 'true'; // Add ?debug=true to URL for debugging

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            try {
                $pdo = getConnection();
                
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate secure token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    
                    // Store reset token
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (email, token, expires_at, used) 
                        VALUES (?, ?, ?, 0)
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), 
                        expires_at = VALUES(expires_at), 
                        used = 0
                    ");
                    $stmt->execute([$email, $token, $expires]);
                    
                    // Build reset URL
                    $resetUrl = rtrim(SITE_URL, '/') . '/reset_password.php?token=' . $token;
                    
                    // Prepare email
                    $subject = 'Password Reset Request - ' . SITE_NAME;
                    $body = "Hello,\n\n";
                    $body .= "You have requested a password reset for your account.\n\n";
                    $body .= "Click the link below to reset your password:\n";
                    $body .= "$resetUrl\n\n";
                    $body .= "This link will expire in 30 minutes for security reasons.\n\n";
                    $body .= "If you did not request this reset, please ignore this email.\n\n";
                    $body .= "Best regards,\n" . SITE_NAME;
                    
                    // Send email using proven superuser method
                    if ($debug_mode) {
                        echo "<div class='alert alert-info'><strong>DEBUG MODE ACTIVE</strong></div>";
                    }
                    
                    $email_sent = sendEmail($email, $subject, $body, $debug_mode);
                    
                    if ($email_sent) {
                        $success = 'If an account with that email exists, a reset link has been sent.';
                        if ($debug_mode) {
                            $success .= " (DEBUG: Email was actually sent successfully)";
                        }
                    } else {
                        // For development/testing - show the reset link directly (like superuser shows key)
                        if ($debug_mode || (defined('ENVIRONMENT') && ENVIRONMENT !== 'production')) {
                            $resetUrl = rtrim(SITE_URL, '/') . '/reset_password.php?token=' . $token;
                            $errors['general'] = 'Email failed. Development reset link: <a href="' . htmlspecialchars($resetUrl) . '" target="_blank">Click here to reset</a>';
                        } else {
                            $errors['general'] = 'Failed to send reset email. Please contact system administrator.';
                            error_log("Password reset email failure for: $email");
                        }
                    }
                } else {
                    // Don't reveal if email doesn't exist (security)
                    $success = 'If an account with that email exists, a reset link has been sent.';
                    if ($debug_mode) {
                        $success .= " (DEBUG: Email not found in database)";
                    }
                }
                
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $errors['general'] = 'An error occurred. Please try again later.';
                if ($debug_mode) {
                    $errors['general'] .= " (DEBUG: " . $e->getMessage() . ")";
                }
            }
        }
    }
}

// Show email test if debug mode is active
if ($debug_mode && !$_POST) {
    testEmailConfiguration();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-header text-center py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-key text-primary"></i> Reset Password
                        </h4>
                        <?php if ($debug_mode): ?>
                            <small class="text-warning">DEBUG MODE</small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php displayFlashMessage(); ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        <?php else: ?>
                            
                            <?php if (isset($errors['general'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errors['general']) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" 
                                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                           id="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           placeholder="Enter your email address"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback">
                                            <?= htmlspecialchars($errors['email']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                                </button>
                            </form>

                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none small">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                                <!--<?php if (!$debug_mode): ?>-->
                                <!--    <br>-->
                                <!--    <a href="?debug=true" class="text-decoration-none small text-muted">-->
                                <!--        <i class="fas fa-bug me-1"></i> Debug Email Issues-->
                                <!--    </a>-->
                                <!--<?php endif; ?>-->
                            </div>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
