<?php
// reset_password.php
require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$token = trim($_GET['token'] ?? '');
$errors = [];
$success = '';
$reset_data = null;

// Validate token exists
if (empty($token)) {
    redirectWithMessage('login.php', 'Invalid or missing reset token.', 'error');
}

try {
    $pdo = getConnection();
    
    // Check if token is valid
    $stmt = $pdo->prepare("
        SELECT email, expires_at 
        FROM password_resets 
        WHERE token = ? AND used = 0 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();
    
    if (!$reset_data) {
        redirectWithMessage('login.php', 'Invalid or expired reset link.', 'error');
    }
    
    // Check if token has expired
    if (strtotime($reset_data['expires_at']) < time()) {
        // Clean up expired token
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
        redirectWithMessage('login.php', 'Reset link has expired. Please request a new one.', 'error');
    }
    
} catch (Exception $e) {
    error_log('Reset password validation error: ' . $e->getMessage());
    redirectWithMessage('login.php', 'An error occurred. Please try again.', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate passwords
        if (empty($new_password) || empty($confirm_password)) {
            $errors['password'] = 'Both password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $errors['password'] = 'Passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters long.';
        } else {
            try {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $result = $stmt->execute([$hashed_password, $reset_data['email']]);
                
                if ($result) {
                    // Mark token as used
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
                    
                    $success = 'Your password has been reset successfully! You can now log in with your new password.';
                } else {
                    $errors['general'] = 'Failed to update password. Please try again.';
                }
                
            } catch (Exception $e) {
                error_log('Password update error: ' . $e->getMessage());
                $errors['general'] = 'An error occurred while updating your password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= htmlspecialchars(SITE_NAME) ?></title>
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
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
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
                            <i class="fas fa-lock-open text-success"></i> New Password
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login Now
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
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-key"></i> New Password
                                    </label>
                                    <input type="password" 
                                           class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                           id="new_password" 
                                           name="new_password" 
                                           placeholder="Enter new password"
                                           required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-check-double"></i> Confirm Password
                                    </label>
                                    <input type="password" 
                                           class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Confirm new password"
                                           required>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback">
                                            <?= htmlspecialchars($errors['password']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="password-requirements mb-3">
                                    <small>
                                        <i class="fas fa-info-circle"></i> 
                                        Password must be at least 6 characters long
                                    </small>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 mb-3">
                                    <i class="fas fa-save me-2"></i> Reset Password
                                </button>
                            </form>

                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none small">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
