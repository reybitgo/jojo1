<?php
// user/transfer.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);

// Fetch transfer settings
$transfer_charge_percentage = floatval(getAdminSetting('transfer_charge_percentage') ?? 0.05); // Default to 5%
$transfer_minimum_amount = floatval(getAdminSetting('transfer_minimum_amount') ?? 1.00); // Default to 1 USDT
$transfer_maximum_amount = floatval(getAdminSetting('transfer_maximum_amount') ?? 10000.00); // Default to 10000 USDT

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $recipient_username = trim($_POST['recipient_username']);
        $transfer_amount = floatval($_POST['transfer_amount']);

        if ($transfer_amount < $transfer_minimum_amount) {
            $errors['transfer_amount'] = "Transfer amount must be at least " . formatCurrency($transfer_minimum_amount) . ".";
        }

        if ($transfer_amount > $transfer_maximum_amount) {
            $errors['transfer_amount'] = "Transfer amount cannot exceed " . formatCurrency($transfer_maximum_amount) . ".";
        }

        if ($transfer_amount > $balance) {
            $errors['transfer_amount'] = 'Insufficient balance.';
        }

        $recipient_user = getUserByUsername($recipient_username);
        if (!$recipient_user) {
            $errors['recipient_username'] = 'Recipient username not found.';
        }

        if (empty($errors)) {
            $transfer_charge = $transfer_amount * $transfer_charge_percentage;
            $actual_transfer_amount = $transfer_amount - $transfer_charge;

            // Deduct amount from sender's ewallet
            if (!processEwalletTransaction($user_id, 'transfer', -$transfer_amount, "Transfer to $recipient_username")) {
                $errors['general'] = 'Failed to deduct amount from sender\'s e-wallet.';
            } else {
                // Add amount to recipient's ewallet (non-withdrawable)
                if (!addEwalletTransaction($recipient_user['id'], 'transfer', $actual_transfer_amount, "Received transfer from $user_id", null, 0)) {
                    $errors['general'] = 'Failed to add amount to recipient\'s e-wallet.';
                } else {
                    // Add transfer charge to admin's ewallet (withdrawable)
                    if (!addEwalletTransaction(1, 'transfer_charge', $transfer_charge, "Transfer charge from $user_id", null, 1)) {
                        $errors['general'] = 'Failed to add transfer charge to admin\'s e-wallet.';
                    } else {
                        $success = 'Funds transferred successfully.';
                    }
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
    <title>Transfer Funds - <?= SITE_NAME ?></title>
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
        /* card styles */
        .card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            transition: .4s;
            transform: translateY(20px);
            opacity: 0;
        }
        .card.card-visible {
            transform: translateY(0);
            opacity: 1;
        }
        .card:hover {
            transform: translateY(-12px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(0,0,0,.15);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.5rem 1rem;
            border-radius: 1rem 1rem 0 0;
        }
        .form-control, .btn {
            border-radius: .5rem;
        }
        .btn-primary {
            padding: .75rem 1.5rem;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,.1);
        }
        .alert {
            border-radius: .5rem;
        }
        .dashboard-logo {
            width: calc(100% - 2rem); /* Match nav-link width by accounting for sidebar padding */
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
                <li><a href="ewallet.php" class="nav-link text-white active"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
                <li><a href="profile.php" class="nav-link text-white"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                <li><a href="ewallet.php" class="nav-link text-white active"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
                <li><a href="profile.php" class="nav-link text-white"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
                <h1 class="h3 fw-bold">Transfer Funds</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error_key => $error_message): ?>
                            <li><?= htmlspecialchars($error_message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exchange-alt me-2"></i>Transfer Funds</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-wallet me-2"></i>Your current e-wallet balance: <?= formatCurrency($balance) ?>
                    </div>

                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>Transfer Information:
                        <ul class="mb-0">
                            <li>Minimum Transfer Amount: <?= formatCurrency($transfer_minimum_amount) ?></li>
                            <li>Maximum Transfer Amount: <?= formatCurrency($transfer_maximum_amount) ?></li>
                            <li>Transfer Charge: <?= $transfer_charge_percentage * 100 ?>%</li>
                        </ul>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label for="recipient_username" class="form-label">Recipient Username</label>
                            <input type="text" 
                                   class="form-control <?= isset($errors['recipient_username']) ? 'is-invalid' : '' ?>" 
                                   id="recipient_username" name="recipient_username" required>
                            <?php if (isset($errors['recipient_username'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['recipient_username']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="transfer_amount" class="form-label">Amount to Transfer (USDT)</label>
                            <input type="number" 
                                   class="form-control <?= isset($errors['transfer_amount']) ? 'is-invalid' : '' ?>" 
                                   id="transfer_amount" name="transfer_amount"
                                   min="<?= $transfer_minimum_amount ?>" 
                                   max="<?= min($transfer_maximum_amount, $balance) ?>" 
                                   step="0.01"
                                   required>
                            <?php if (isset($errors['transfer_amount'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['transfer_amount']) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>Transfer Funds
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* Reveal cards on scroll */
            const cards = document.querySelectorAll('.card');
            const observer = new IntersectionObserver(entries => 
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => entry.target.classList.add('card-visible'), index * 150);
                    }
                }), 
                { threshold: 0.2 }
            );
            cards.forEach(card => observer.observe(card));
        });
    </script>
</body>
</html>