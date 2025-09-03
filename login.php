<?php
// Login Page
// login.php

require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Prevent logged in users from accessing login page
preventLoggedInAccess();

$errors = [];
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Validate login data
        $validation = validateLoginData($_POST);

        if ($validation['valid']) {
            $username = $validation['data']['username'];
            $password = $validation['data']['password'];

            // Authenticate user
            $user = authenticateUser($username, $password);

            if ($user) {
                // Set user session
                setUserSession($user);

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirectWithMessage('admin/dashboard.php', 'Welcome back, ' . $user['username'] . '!', 'success');
                } else {
                    redirectWithMessage('user/dashboard.php', 'Welcome back, ' . $user['username'] . '!', 'success');
                }
            } else {
                $errors['general'] = 'Invalid username/email or password.';
            }
        } else {
            $errors = $validation['errors'];
        }

        // Add this after the authenticateUser call in login.php
        // if (!$user) {
        //     // Debug information
        //     echo "<pre>";
        //     echo "Login attempt failed\n";
        //     echo "Username: " . htmlspecialchars($username) . "\n";
        //     echo "Password: " . htmlspecialchars($password) . "\n";

        //     // Check if user exists
        //     $pdo = getConnection();
        //     $stmt = $pdo->prepare("SELECT username, password FROM users WHERE username = ? OR email = ?");
        //     $stmt->execute([$username, $username]);
        //     $debug_user = $stmt->fetch();

        //     if ($debug_user) {
        //         echo "User found in database\n";
        //         echo "DB Password Hash: " . $debug_user['password'] . "\n";
        //         echo "Hash verification: " . (password_verify($password, $debug_user['password']) ? 'SUCCESS' : 'FAILED') . "\n";
        //     } else {
        //         echo "User NOT found in database\n";
        //     }
        //     echo "</pre>";
        // }
    }
}

// Get any flash messages
$flash_message = getFlashMessage();
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
                        <form method="POST" action="">
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
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Login
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>