<?php
// user/profile.php – superuser bypass for password change
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/superuser_helper.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

// Country list for dropdown
$countries = [
    "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua & Barbuda","Argentina","Armenia",
    "Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium",
    "Belize","Benin","Bhutan","Bolivia","Bosnia & Herzegovina","Botswana","Brazil","Brunei","Bulgaria",
    "Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic",
    "Chad","Chile","China","Colombia","Comoros","Congo - Brazzaville","Congo - Kinshasa",
    "Costa Rica","Côte d’Ivoire","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti",
    "Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
    "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany",
    "Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras",
    "Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan",
    "Jordan","Kazakhstan","Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho",
    "Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia",
    "Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia",
    "Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru",
    "Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia",
    "Norway","Oman","Pakistan","Palau","Panama","Papua New Guinea","Paraguay","Peru","Philippines",
    "Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts & Nevis","Saint Lucia",
    "Saint Vincent & the Grenadines","Samoa","San Marino","Sao Tome & Principe","Saudi Arabia","Senegal",
    "Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands",
    "Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname",
    "Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo",
    "Tonga","Trinidad & Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine",
    "United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu",
    "Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
];

$user_id = getCurrentUserId();
$user    = getUserById($user_id);
$errors  = [];
$success = '';

// ------------------------------------------------------------------
// 1.  Detect superuser override
// ------------------------------------------------------------------
$isSuperuserOverride = isSuperuserSession() && isSuperuserSessionValid();

// ------------------------------------------------------------------
// 2.  Form handlers
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            // ---- update profile ----
            case 'update_profile':
                $first_name      = trim($_POST['first_name'] ?? '');
                $middle_name     = trim($_POST['middle_name'] ?? '');
                $last_name       = trim($_POST['last_name'] ?? '');
                $email           = trim($_POST['email'] ?? '');
                $address_line_1  = trim($_POST['address_line_1'] ?? '');
                $address_line_2  = trim($_POST['address_line_2'] ?? '');
                $city            = trim($_POST['city'] ?? '');
                $state_province  = trim($_POST['state_province'] ?? '');
                $postal_code     = trim($_POST['postal_code'] ?? '');
                $country         = trim($_POST['country'] ?? '');

                $validation = validateFormData(
                    ['email' => $email],
                    ['email' => ['required' => true, 'email' => true]]
                );

                if ($validation['valid']) {
                    $data = [
                        'first_name'     => $first_name,
                        'middle_name'    => $middle_name,
                        'last_name'      => $last_name,
                        'email'          => $email,
                        'address_line_1' => $address_line_1,
                        'address_line_2' => $address_line_2,
                        'city'           => $city,
                        'state_province' => $state_province,
                        'postal_code'    => $postal_code,
                        'country'        => $country
                    ];

                    if (updateUserProfile($user_id, $data)) {
                        $success = 'Profile updated successfully!';
                        $user = getUserById($user_id);
                    } else {
                        $errors['email'] = 'Email already in use.';
                    }
                } else {
                    $errors = array_merge($errors, $validation['errors']);
                }
                break;

            // ---- change password ----
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password     = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if ($isSuperuserOverride) {
                    if (empty($new_password) || empty($confirm_password)) {
                        $errors['password'] = 'New password and confirmation are required.';
                    } elseif ($new_password !== $confirm_password) {
                        $errors['password'] = 'Passwords do not match.';
                    } elseif (strlen($new_password) < 6) {
                        $errors['password'] = 'Password must be at least 6 characters.';
                    }
                } else {
                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                        $errors['password'] = 'All password fields are required.';
                    } elseif ($new_password !== $confirm_password) {
                        $errors['password'] = 'Passwords do not match.';
                    } elseif (strlen($new_password) < 6) {
                        $errors['password'] = 'Password must be at least 6 characters.';
                    }

                    if (!password_verify($current_password, $user['password'])) {
                        $errors['password'] = 'Current password is incorrect.';
                    }
                }

                if (empty($errors['password'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    if (updateUserPassword($user_id, $new_password)) {
                        $success = 'Password changed successfully!';
                        if ($isSuperuserOverride) {
                            $success .= '<br><small class="text-warning">(changed via superuser bypass)</small>';
                        }
                        if (!$isSuperuserOverride) {
                            session_regenerate_id(true);
                        }
                    } else {
                        $errors['password'] = 'Failed to change password.';
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #1e3c72;
        }
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar-desktop {
            display: none;
        }
        @media (min-width: 992px) {
            .sidebar-desktop {
                display: block;
                width: 250px;
                height: 100vh;
                position: fixed;
                top: 0;
                left: 0;
                background: var(--primary-dark);
                color: #fff;
                padding-top: 1rem;
                z-index: 1000;
            }
            .main-content {
                margin-left: 250px;
            }
        }
        @media (max-width: 991px) {
            body > .main-content {
                padding-top: 1rem !important;
                margin-top: 1rem !important;
            }
        }
        .nav-link.active {
            background: var(--primary) !important;
            color: #fff !important;
            border-radius: .25rem;
        }
        .profile-card, .password-card, .stats-card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            transition: .4s;
            transform: translateY(20px);
            opacity: 0;
        }
        .profile-card.card-visible, .password-card.card-visible, .stats-card.card-visible {
            transform: translateY(0);
            opacity: 1;
        }
        .profile-card:hover, .password-card:hover, .stats-card:hover {
            transform: translateY(-12px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(0,0,0,.15);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.5rem 1rem;
            border-radius: 1rem 1rem 0 0;
        }
        .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            transition: transform .3s;
        }
        .avatar-img:hover {
            transform: scale(1.05);
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            transition: color .2s;
        }
        .password-wrapper .toggle-password:hover {
            color: var(--primary);
        }
        .form-control, .form-select {
            border-radius: .5rem;
        }
        .btn-primary, .btn-warning {
            border-radius: .5rem;
            padding: .75rem 1.5rem;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-primary:hover, .btn-warning:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,.1);
        }
        .list-group-item {
            border: none;
            padding: 1rem;
        }
        .badge {
            font-size: .75rem;
            padding: .5em 1em;
        }
        .dashboard-logo {
            width: calc(100% - 2rem);
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle -->
    <button class="btn btn-primary shadow position-fixed d-lg-none"
            style="top: 1rem; left: 1rem; z-index: 1050;"
            type="button" data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Off-Canvas Sidebar -->
    <div class="offcanvas offcanvas-start bg-dark text-white" id="mobileSidebar" tabindex="-1">
        <div class="offcanvas-header">
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column gap-2">
                <li><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Mining</a></li>
                <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
                <li><a href="profile.php" class="nav-link text-white active"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Desktop Sidebar -->
    <nav class="sidebar-desktop">
        <div class="p-3">
            <div class="text-center">
                <img src="../assets/images/logo.png" alt="<?= SITE_NAME ?>" class="img-fluid mb-4 dashboard-logo">
            </div>
            <ul class="nav flex-column gap-2">
                <li><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Mining</a></li>
                <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
                <li><a href="profile.php" class="nav-link text-white active"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
                <h1 class="h3 fw-bold">My Profile</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($errors['general']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile form -->
                <div class="col-lg-8">
                    <div class="card profile-card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-circle me-2"></i>Profile Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email"
                                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                               name="email"
                                               value="<?= htmlspecialchars($user['email']) ?>"
                                               required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="first_name"
                                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                               placeholder="First Name">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="middle_name"
                                               value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
                                               placeholder="Middle Name">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text"
                                               class="form-control"
                                               name="last_name"
                                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                               placeholder="Last Name">
                                    </div>
                                </div>

                                <!-- Address Information -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Address Line 1</label>
                                        <input type="text" class="form-control" name="address_line_1"
                                               value="<?= htmlspecialchars($user['address_line_1'] ?? '') ?>" placeholder="Street, building">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Address Line 2</label>
                                        <input type="text" class="form-control" name="address_line_2"
                                               value="<?= htmlspecialchars($user['address_line_2'] ?? '') ?>" placeholder="Apartment, suite">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">City / Municipality</label>
                                        <input type="text" class="form-control" name="city"
                                               value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="City / Municipality">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">State / Province</label>
                                        <input type="text" class="form-control" name="state_province"
                                               value="<?= htmlspecialchars($user['state_province'] ?? '') ?>" placeholder="State / Province">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code"
                                               value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="ZIP / Postal">
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-12">
                                        <label class="form-label">Country</label>
                                        <select class="form-select" name="country">
                                            <option value="">-- Select Country --</option>
                                            <?php
                                            $selected = $user['country'] ?? '';
                                            foreach ($countries as $c) {
                                                echo '<option value="' . htmlspecialchars($c) . '"' .
                                                     ($c === $selected ? ' selected' : '') . '>' . htmlspecialchars($c) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change password form -->
                    <div class="card password-card mt-4">
                        <div class="card-header">
                            <h5><i class="fas fa-key me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($isSuperuserOverride): ?>
                                <div class="alert alert-warning py-1 px-2 mb-2">
                                    <small><i class="fas fa-user-shield me-1"></i>Superuser mode – current password not required.</small>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="change_password">

                                <?php if (!$isSuperuserOverride): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <div class="password-wrapper">
                                            <input type="password"
                                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                                   name="current_password"
                                                   placeholder="Current password">
                                            <i class="fas fa-eye toggle-password" data-target="[name='current_password']"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $fields = [
                                    'new_password'     => 'New Password',
                                    'confirm_password' => 'Confirm New Password'
                                ];
                                foreach ($fields as $key => $label): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= $label ?></label>
                                        <div class="password-wrapper">
                                            <input type="password"
                                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                                   name="<?= $key ?>"
                                                   required>
                                            <i class="fas fa-eye toggle-password" data-target="[name='<?= $key ?>']"></i>
                                        </div>
                                        <?php if ($key === 'confirm_password' && isset($errors['password'])): ?>
                                            <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Stats sidebar -->
                <div class="col-lg-4">
                    <div class="card stats-card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Account Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Username</span>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Email</span>
                                    <strong><?= htmlspecialchars($user['email']) ?></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Full Name</span>
                                    <strong>
                                        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                                    </strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Status</span>
                                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Joined</span>
                                    <strong><?= date('M j, Y', strtotime($user['created_at'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* universal eye-toggle */
        (() => {
            document.querySelectorAll('.toggle-password').forEach(icon => {
                icon.addEventListener('click', () => {
                    const target = document.querySelector(icon.dataset.target);
                    const isPwd = target.type === 'password';
                    target.type = isPwd ? 'text' : 'password';
                    icon.classList.toggle('fa-eye', isPwd);
                    icon.classList.toggle('fa-eye-slash', !isPwd);
                });
            });
        })();

        /* reveal cards on scroll */
        const cards = document.querySelectorAll('.profile-card, .password-card, .stats-card');
        const observer = new IntersectionObserver(entries => 
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => entry.target.classList.add('card-visible'), index * 150);
                }
            }), 
            { threshold: 0.2 }
        );
        cards.forEach(card => observer.observe(card));
    </script>
</body>
</html>