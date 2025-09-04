<?php
// Enhanced Secure Login Page
// login.php

require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Prevent logged in users from accessing login page
preventLoggedInAccess();

$errors = [];
$success_message = '';

// Rate limiting configuration
$max_attempts = 5;
$lockout_time = 900; // 15 minutes in seconds
$attempt_window = 300; // 5 minutes in seconds

/**
 * Check and handle rate limiting for login attempts
 * @param string $identifier IP address or username
 * @return array Result with allowed status and remaining attempts
 */
function checkRateLimit($identifier) {
    global $max_attempts, $lockout_time, $attempt_window;
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $now = time();
    $attempts = &$_SESSION['login_attempts'];
    
    // Clean old attempts outside the window
    if (isset($attempts[$identifier])) {
        $attempts[$identifier] = array_filter($attempts[$identifier], function($timestamp) use ($now, $attempt_window) {
            return ($now - $timestamp) < $attempt_window;
        });
    }
    
    // Check if user is locked out
    if (isset($attempts[$identifier]) && count($attempts[$identifier]) >= $max_attempts) {
        $oldest_attempt = min($attempts[$identifier]);
        $time_since_oldest = $now - $oldest_attempt;
        
        if ($time_since_oldest < $lockout_time) {
            $remaining_lockout = $lockout_time - $time_since_oldest;
            return [
                'allowed' => false,
                'remaining_attempts' => 0,
                'lockout_remaining' => $remaining_lockout,
                'message' => 'Too many failed attempts. Try again in ' . ceil($remaining_lockout / 60) . ' minutes.'
            ];
        } else {
            // Reset attempts after lockout period
            unset($attempts[$identifier]);
        }
    }
    
    $current_attempts = isset($attempts[$identifier]) ? count($attempts[$identifier]) : 0;
    $remaining = $max_attempts - $current_attempts;
    
    return [
        'allowed' => true,
        'remaining_attempts' => $remaining,
        'lockout_remaining' => 0,
        'message' => ''
    ];
}

/**
 * Record a failed login attempt
 * @param string $identifier IP address or username
 */
function recordFailedAttempt($identifier) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!isset($_SESSION['login_attempts'][$identifier])) {
        $_SESSION['login_attempts'][$identifier] = [];
    }
    
    $_SESSION['login_attempts'][$identifier][] = time();
}

/**
 * Clear failed attempts for successful login
 * @param string $identifier IP address or username
 */
function clearFailedAttempts($identifier) {
    if (isset($_SESSION['login_attempts'][$identifier])) {
        unset($_SESSION['login_attempts'][$identifier]);
    }
}

/**
 * Enhanced authentication with additional security checks
 * @param string $username Username or email
 * @param string $password Password
 * @param string $user_ip User's IP address
 * @return array|false User data on success, false on failure
 */
function secureAuthenticateUser($username, $password, $user_ip) {
    try {
        $pdo = getConnection();
        
        // Check if input is email or username
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        // Enhanced query to check account status
        $stmt = $pdo->prepare("SELECT * FROM users WHERE $field = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            logEvent("Login attempt for non-existent user: $username from IP: $user_ip", 'warning');
            return false;
        }
        
        // Check if account is suspended
        if ($user['status'] === 'suspended') {
            logEvent("Login attempt for suspended user: $username from IP: $user_ip", 'warning');
            return false;
        }
        
        // Verify password with timing attack protection
        $password_valid = password_verify($password, $user['password']);
        
        if ($password_valid) {
            // Log successful login
            logLoginAttempt($username, $user_ip, true, $user['id']);
            
            // Update last login timestamp if column exists
            try {
                $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$user['id']]);
            } catch (Exception $e) {
                // Column might not exist, ignore this error
            }
            
            logEvent("Successful login: " . $user['username'] . " from IP: $user_ip", 'info');
            return $user;
        } else {
            // Log failed login
            logLoginAttempt($username, $user_ip, false, $user['id']);
            logEvent("Failed login attempt for: $username from IP: $user_ip", 'warning');
            return false;
        }
        
    } catch (Exception $e) {
        logEvent("Login error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Log login attempts to database
 * @param string $username Username attempted
 * @param string $ip_address IP address
 * @param bool $success Whether login was successful
 * @param int $user_id User ID if known
 */
function logLoginAttempt($username, $ip_address, $success, $user_id = null) {
    try {
        $pdo = getConnection();
        
        // Create login_attempts table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_id INT NULL,
                success TINYINT(1) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_agent TEXT NULL,
                INDEX idx_username (username),
                INDEX idx_ip (ip_address),
                INDEX idx_attempted_at (attempted_at)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, user_id, success, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username, 
            $ip_address, 
            $user_id, 
            $success ? 1 : 0, 
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        logEvent("Failed to log login attempt: " . $e->getMessage(), 'error');
    }
}

/**
 * Get user's real IP address (considering proxies)
 * @return string IP address
 */
function getRealIpAddress() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (from proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_ip = getRealIpAddress();
    $username = sanitizeInput($_POST['username'] ?? '');
    
    // Check rate limiting
    $rate_limit_check = checkRateLimit($user_ip);
    $username_rate_check = checkRateLimit($username);
    
    if (!$rate_limit_check['allowed'] || !$username_rate_check['allowed']) {
        $lockout_message = !$rate_limit_check['allowed'] ? $rate_limit_check['message'] : $username_rate_check['message'];
        $errors['general'] = $lockout_message;
        logEvent("Rate limit exceeded for IP: $user_ip, Username: $username", 'warning');
    } 
    // Verify CSRF token
    elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please refresh and try again.';
        logEvent("CSRF token validation failed from IP: $user_ip", 'warning');
    } 
    else {
        // Validate login data
        $validation = validateLoginData($_POST);

        if ($validation['valid']) {
            $username = $validation['data']['username'];
            $password = $validation['data']['password'];

            // Enhanced authentication
            $user = secureAuthenticateUser($username, $password, $user_ip);

            if ($user) {
                // Clear failed attempts
                clearFailedAttempts($user_ip);
                clearFailedAttempts($username);
                
                // Set user session with additional security
                setUserSession($user);
                
                // Set secure session flags
                $_SESSION['login_ip'] = $user_ip;
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirectWithMessage('admin/dashboard.php', 'Welcome back, ' . htmlspecialchars($user['username']) . '!', 'success');
                } else {
                    redirectWithMessage('user/dashboard.php', 'Welcome back, ' . htmlspecialchars($user['username']) . '!', 'success');
                }
            } else {
                // Record failed attempt
                recordFailedAttempt($user_ip);
                recordFailedAttempt($username);
                
                // Generic error message to prevent user enumeration
                $errors['general'] = 'Invalid username/email or password.';
                
                // Show remaining attempts if rate limiting is in effect
                $remaining = min($rate_limit_check['remaining_attempts'], $username_rate_check['remaining_attempts']) - 1;
                if ($remaining > 0 && $remaining <= 2) {
                    $errors['general'] .= " You have $remaining attempt(s) remaining.";
                }
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}

// Enhanced session validation for existing sessions
if (isLoggedIn()) {
    $current_ip = getRealIpAddress();
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check for session hijacking (optional - can be disabled if users have dynamic IPs)
    if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] !== $current_ip) {
        logEvent("Potential session hijacking detected - IP mismatch for user: " . getCurrentUsername() . " (Old: " . $_SESSION['login_ip'] . ", New: $current_ip)", 'warning');
        // Uncomment below to force logout on IP change
        // destroySession();
        // redirectWithMessage('login.php', 'Your session has been terminated for security reasons.', 'warning');
    }
}

// Get any flash messages
$flash_message = getFlashMessage();

// Content Security Policy header
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");

// Additional security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .login-branding {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px 0 10px;
        }

        .login-branding img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .login-branding .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .input-group-text {
            background: transparent;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <!-- Header -->
                    <div class="login-header text-center">
                        <div class="login-branding">
                            <img src="assets/images/logo3.png" alt="JOJO Token Logo">
                            <h1 class="brand-text">JOJO Token</h1>
                        </div>
                        <p class="mb-0 pb-2">Welcome Back!</p>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <!-- Flash Message -->
                        <?php if ($flash_message): ?>
                            <div class="alert alert-<?php echo $flash_message['type'] === 'error' ? 'danger' : $flash_message['type']; ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo htmlspecialchars($flash_message['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- General Error -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form method="POST" action="" id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <!-- Username/Email -->
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                        id="username" name="username"
                                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                        placeholder="Enter your username or email" required>
                                    <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['username']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password"
                                        class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                        id="password" name="password" placeholder="Enter your password" required>
                                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['password']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login" id="loginButton">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    <span id="loginButtonText">Login</span>
                                </button>
                            </div>
                        </form>

                        <!-- Register Link -->
                        <div class="text-center">
                            <p class="mb-0">Don't have an account?
                                <a href="register.php" class="text-decoration-none fw-bold">
                                    Register here <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-2">
                            <a href="forgot_password.php" class="text-decoration-none small">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced client-side security measures
        (function() {
            'use strict';
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            
            togglePassword.addEventListener('click', function () {
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            // Form submission handling with loading state
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const loginButtonText = document.getElementById('loginButtonText');

            loginForm.addEventListener('submit', function(e) {
                // Prevent double submission
                loginButton.disabled = true;
                loginButtonText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
                
                // Re-enable after 5 seconds as fallback
                setTimeout(function() {
                    loginButton.disabled = false;
                    loginButtonText.innerHTML = 'Login';
                }, 5000);
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function () {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

            // Basic client-side input validation
            const usernameField = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            usernameField.addEventListener('input', function() {
                this.value = this.value.trim();
            });

            // Prevent form submission if fields are empty
            loginForm.addEventListener('submit', function(e) {
                if (!usernameField.value.trim() || !passwordInput.value) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    loginButton.disabled = false;
                    loginButtonText.innerHTML = 'Login';
                    return false;
                }
            });

            // Clear password field on page unload for security
            window.addEventListener('beforeunload', function() {
                passwordInput.value = '';
            });

        })();
    </script>
</body>

</html>