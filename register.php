<?php
// Register Page
// register.php

require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Prevent logged in users from accessing register page
// preventLoggedInAccess();

$errors = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Map form field names to what registerUser expects
        $mapped_data = [
            'firstname' => trim($_POST['first_name'] ?? ''),
            'middlename' => trim($_POST['middle_name'] ?? ''),
            'lastname' => trim($_POST['last_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'sponsor_name' => trim($_POST['sponsor_name'] ?? ''),
            // Add address fields
            'address_line_1' => trim($_POST['address_line_1'] ?? ''),
            'address_line_2' => trim($_POST['address_line_2'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state_province' => trim($_POST['state_province'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'country' => trim($_POST['country'] ?? '')
        ];

        // Validate required fields first (excluding address lines - handled separately)
        $required_fields = ['firstname', 'middlename', 'lastname', 'username', 'email', 'password', 'confirm_password', 'city', 'state_province', 'postal_code', 'country'];
        foreach ($required_fields as $field) {
            if (empty($mapped_data[$field])) {
                $form_field = str_replace(['firstname', 'middlename', 'lastname'], ['first_name', 'middle_name', 'last_name'], $field);
                $field_display = ucfirst(str_replace('_', ' ', $form_field));
                $errors[$form_field] = $field_display . ' is required.';
            }
        }

        // Validate that at least one address line is provided
        if (empty($mapped_data['address_line_1']) && empty($mapped_data['address_line_2'])) {
            $errors['address_line_1'] = 'At least one address line is required.';
        }

        // Only proceed with detailed validation if basic requirements are met
        if (empty($errors)) {
            // Validate password match
            if ($mapped_data['password'] !== $mapped_data['confirm_password']) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }

            // Validate password length
            if (strlen($mapped_data['password']) < PASSWORD_MIN_LENGTH) {
                $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            }

            // Validate username length
            if (strlen($mapped_data['username']) < USERNAME_MIN_LENGTH || strlen($mapped_data['username']) > USERNAME_MAX_LENGTH) {
                $errors['username'] = 'Username must be between ' . USERNAME_MIN_LENGTH . ' and ' . USERNAME_MAX_LENGTH . ' characters.';
            }

            // Validate email format
            if (!filter_var($mapped_data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            }

            // Validate names
            foreach (['firstname' => 'first_name', 'middlename' => 'middle_name', 'lastname' => 'last_name'] as $field => $form_field) {
                if (strlen($mapped_data[$field]) < 2) {
                    $errors[$form_field] = ucfirst(str_replace('_', ' ', $form_field)) . ' must be at least 2 characters long.';
                }
                if (!preg_match('/^[a-zA-Z\s\-\']+$/', $mapped_data[$field])) {
                    $errors[$form_field] = ucfirst(str_replace('_', ' ', $form_field)) . ' can only contain letters, spaces, hyphens, and apostrophes.';
                }
            }

            // Validate address fields (only if they have content)
            if (!empty($mapped_data['address_line_1']) && strlen($mapped_data['address_line_1']) < 5) {
                $errors['address_line_1'] = 'Address line 1 must be at least 5 characters long.';
            }
            if (!empty($mapped_data['address_line_2']) && strlen($mapped_data['address_line_2']) < 3) {
                $errors['address_line_2'] = 'Address line 2 must be at least 3 characters long.';
            }
            
            // Validate other address fields
            if (strlen($mapped_data['city']) < 2) {
                $errors['city'] = 'City must be at least 2 characters long.';
            }
            if (strlen($mapped_data['state_province']) < 2) {
                $errors['state_province'] = 'State/Province must be at least 2 characters long.';
            }
            if (strlen($mapped_data['postal_code']) < 3) {
                $errors['postal_code'] = 'Postal code must be at least 3 characters long.';
            }
            if (strlen($mapped_data['country']) < 2) {
                $errors['country'] = 'Country must be at least 2 characters long.';
            }

            // Validate postal code format (basic alphanumeric with optional spaces/hyphens)
            if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $mapped_data['postal_code'])) {
                $errors['postal_code'] = 'Postal code contains invalid characters.';
            }

            // If all validations pass, attempt registration
            if (empty($errors)) {
                $result = registerUser($mapped_data);

                if ($result['success']) {
                    redirectWithMessage('login.php', $result['message'], 'success');
                } else {
                    $errors['general'] = $result['message'];
                }
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
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .register-branding {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px 0 10px;
        }

        .register-branding img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .register-branding .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
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

        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
        }

        .strength-fair {
            background-color: #ffc107;
        }

        .strength-good {
            background-color: #20c997;
        }

        .strength-strong {
            background-color: #28a745;
        }

        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 2rem 0 1.5rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="register-container">
                    <!-- Header -->
                    <div class="register-header text-center">
                        <div class="register-branding">
                            <img src="assets/images/logo3.png" alt="JOJO Token Logo">
                            <h1 class="brand-text">JOJO Token</h1>
                        </div>
                        <p class="mb-0 pb-2">Create Your Account</p>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <!-- General Error -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <!-- Personal Information Section -->
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-user-circle me-2"></i>Personal Information
                                    </h5>
                                </div>
                            </div>

                            <div class="row">
                                <!-- First Name -->
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label">First Name <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                            id="first_name" name="first_name"
                                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                            placeholder="Enter first name" required>
                                        <?php if (isset($errors['first_name'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['first_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Middle Name -->
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label">Middle Name <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['middle_name']) ? 'is-invalid' : ''; ?>"
                                            id="middle_name" name="middle_name"
                                            value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>"
                                            placeholder="Enter middle name" required>
                                        <?php if (isset($errors['middle_name'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['middle_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Last Name -->
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                            id="last_name" name="last_name"
                                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                            placeholder="Enter last name" required>
                                        <?php if (isset($errors['last_name'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Information Section -->
                            <div class="section-divider"></div>
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                    </h5>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Address Line 1 -->
                                <div class="col-md-6 mb-3">
                                    <label for="address_line_1" class="form-label">Address Line 1 <span class="text-muted">(Street, building)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-home"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['address_line_1']) ? 'is-invalid' : ''; ?>"
                                            id="address_line_1" name="address_line_1"
                                            value="<?php echo htmlspecialchars($_POST['address_line_1'] ?? ''); ?>"
                                            placeholder="Street address, building, etc.">
                                        <?php if (isset($errors['address_line_1'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['address_line_1']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Address Line 2 -->
                                <div class="col-md-6 mb-3">
                                    <label for="address_line_2" class="form-label">Address Line 2 <span class="text-muted">(Apt, suite, unit)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['address_line_2']) ? 'is-invalid' : ''; ?>"
                                            id="address_line_2" name="address_line_2"
                                            value="<?php echo htmlspecialchars($_POST['address_line_2'] ?? ''); ?>"
                                            placeholder="Apartment, suite, unit, floor">
                                        <?php if (isset($errors['address_line_2'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['address_line_2']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Address requirement note -->
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        At least one address line is required. Use Address Line 1 for street/building and Address Line 2 for apartment/suite details.
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <!-- City -->
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-city"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>"
                                            id="city" name="city"
                                            value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>"
                                            placeholder="Enter city" required>
                                        <?php if (isset($errors['city'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['city']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- State/Province -->
                                <div class="col-md-4 mb-3">
                                    <label for="state_province" class="form-label">State/Province <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-map"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['state_province']) ? 'is-invalid' : ''; ?>"
                                            id="state_province" name="state_province"
                                            value="<?php echo htmlspecialchars($_POST['state_province'] ?? ''); ?>"
                                            placeholder="State or Province" required>
                                        <?php if (isset($errors['state_province'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['state_province']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Postal Code -->
                                <div class="col-md-4 mb-3">
                                    <label for="postal_code" class="form-label">Postal Code <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-mail-bulk"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['postal_code']) ? 'is-invalid' : ''; ?>"
                                            id="postal_code" name="postal_code"
                                            value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>"
                                            placeholder="ZIP/Postal code" required>
                                        <?php if (isset($errors['postal_code'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['postal_code']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Country -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="country" class="form-label">Country <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-globe"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['country']) ? 'is-invalid' : ''; ?>"
                                            id="country" name="country"
                                            value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>"
                                            placeholder="Enter country" required>
                                        <?php if (isset($errors['country'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['country']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Information Section -->
                            <div class="section-divider"></div>
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-lock me-2"></i>Account Information
                                    </h5>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Username -->
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-at"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                            id="username" name="username"
                                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                            placeholder="Choose a username" required>
                                        <?php if (isset($errors['username'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['username']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">3-50 characters, letters, numbers, and underscores
                                        only</small>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email"
                                            class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                            id="email" name="email"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                            placeholder="Enter your email" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password"
                                            class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                            id="password" name="password" placeholder="Enter password" required>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['password']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="password-strength mt-1" id="passwordStrength"></div>
                                    <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?>
                                        characters</small>
                                </div>

                                <!-- Confirm Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password"
                                            class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                            id="confirm_password" name="confirm_password" placeholder="Confirm password"
                                            required>
                                        <button type="button" class="btn btn-outline-secondary"
                                            id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['confirm_password']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="passwordMatch" class="mt-1"></div>
                                </div>
                            </div>

                            <!-- Sponsor Section -->
                            <div class="section-divider"></div>
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-user-friends me-2"></i>Referral Information
                                    </h5>
                                </div>
                            </div>

                            <!-- Sponsor Name -->
                            <?php
                            $fromRef = isset($_GET['ref']) && !empty(trim($_GET['ref']));
                            $preFill = $fromRef ? trim($_GET['ref']) : ($_POST['sponsor_name'] ?? '');
                            ?>
                            <div class="mb-4">
                                <label for="sponsor_name" class="form-label">Sponsor Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
                                    <input type="text"
                                           class="form-control <?= isset($errors['sponsor_name']) ? 'is-invalid' : '' ?>"
                                           id="sponsor_name"
                                           name="sponsor_name"
                                           value="<?= htmlspecialchars(
                                               $_POST['sponsor_name'] ?? (
                                                   isLoggedIn() ? getCurrentUsername() : ($_GET['ref'] ?? '')
                                               )
                                           ) ?>"
                                           placeholder="Enter sponsor (optional)">
                                    <?php if (isset($errors['sponsor_name'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['sponsor_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Leave blank to auto-assign admin as sponsor
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Create Account
                                </button>
                            </div>
                        </form>

                        <!-- Login Link -->
                        <div class="text-center">
                            <p class="mb-0">Already have an account?
                                <a href="login.php" class="text-decoration-none fw-bold">
                                    Login here <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(passwordId, toggleButtonId) {
            const password = document.getElementById(passwordId);
            const toggleButton = document.getElementById(toggleButtonId);
            const icon = toggleButton.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('togglePassword').addEventListener('click', function () {
            togglePasswordVisibility('password', 'togglePassword');
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function () {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength';
                return;
            }

            let strength = 0;
            const checks = [
                password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>,
                /[a-z]/.test(password),
                /[A-Z]/.test(password),
                /\d/.test(password),
                /[^a-zA-Z\d]/.test(password)
            ];

            strength = checks.filter(check => check).length;

            const strengthClasses = ['', 'strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
            const strengthWidths = ['0%', '20%', '40%', '60%', '80%', '100%'];

            strengthBar.style.width = strengthWidths[strength];
            strengthBar.className = 'password-strength ' + (strengthClasses[strength] || '');
        });

        // Password match indicator
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchIndicator.innerHTML = '';
                return;
            }

            if (password === confirmPassword) {
                matchIndicator.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
            } else {
                matchIndicator.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
            }
        }

        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long!');
                return false;
            }

            // Validate that at least one address line is provided
            const addressLine1 = document.getElementById('address_line_1').value.trim();
            const addressLine2 = document.getElementById('address_line_2').value.trim();

            if (!addressLine1 && !addressLine2) {
                e.preventDefault();
                alert('At least one address line is required!');
                document.getElementById('address_line_1').focus();
                return false;
            }

            // Validate other required fields (excluding address lines)
            const requiredFields = [
                'first_name', 'middle_name', 'last_name', 'username', 'email',
                'city', 'state_province', 'postal_code', 'country'
            ];

            for (let field of requiredFields) {
                const fieldElement = document.getElementById(field);
                if (!fieldElement.value.trim()) {
                    e.preventDefault();
                    alert(`${field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} is required!`);
                    fieldElement.focus();
                    return false;
                }
            }
        });
    </script>
</body>

</html>