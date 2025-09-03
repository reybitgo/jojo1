<?php
// admin/settings.php – responsive-sidebar version (1-to-1 from reference)
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$errors  = [];
$success = '';

/* ---------- Handle form submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $settings = [
            'admin_usdt_wallet'           => trim($_POST['admin_usdt_wallet']),
            'admin_usdt_wallet_bep20'     => trim($_POST['admin_usdt_wallet_bep20']),
            'usdt_rate'                   => max(0.01, floatval($_POST['usdt_rate'])),
            'monthly_bonus_percentage'    => max(0, min(100, intval($_POST['monthly_bonus_percentage']))),
            'referral_level_2_percentage' => max(0, min(100, intval($_POST['referral_level_2_percentage']))),
            'referral_level_3_percentage' => max(0, min(100, intval($_POST['referral_level_3_percentage']))),
            'referral_level_4_percentage' => max(0, min(100, intval($_POST['referral_level_4_percentage']))),
            'referral_level_5_percentage' => max(0, min(100, intval($_POST['referral_level_5_percentage']))),
            'default_sponsor_enabled'     => isset($_POST['default_sponsor_enabled']) ? '1' : '0',
            'orphan_prevention'           => isset($_POST['orphan_prevention']) ? '1' : '0',
            'transfer_charge_percentage'  => max(0, min(100, floatval($_POST['transfer_charge_percentage']))),
            'transfer_minimum_amount'     => max(0, floatval($_POST['transfer_minimum_amount'])),
            'transfer_maximum_amount'     => max(0, floatval($_POST['transfer_maximum_amount'])),

            // Leadership Passive
            'leadership_enabled'               => ($_POST['leadership_enabled'] ?? '0') === '1' ? '1' : '0',
            'direct_package_quota'             => max(0, floatval($_POST['direct_package_quota'])),
            'min_direct_count'                 => max(1, intval($_POST['min_direct_count'])),
            'leadership_levels'                => max(1, min(5, intval($_POST['leadership_levels']))),
            'leadership_level_1_percentage'    => max(0, min(100, floatval($_POST['leadership_level_1_percentage']))),
            'leadership_level_2_percentage'    => max(0, min(100, floatval($_POST['leadership_level_2_percentage']))),
            'leadership_level_3_percentage'    => max(0, min(100, floatval($_POST['leadership_level_3_percentage']))),
            'leadership_level_4_percentage'    => max(0, min(100, floatval($_POST['leadership_level_4_percentage']))),
            'leadership_level_5_percentage'    => max(0, min(100, floatval($_POST['leadership_level_5_percentage']))),
        ];

        try {
            foreach ($settings as $key => $value) updateAdminSetting($key, $value);
            $success = 'Settings updated successfully.';
        } catch (Exception $e) {
            $errors['general'] = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

/* ---------- Get current values ---------- */
$keys = [
    'admin_usdt_wallet','admin_usdt_wallet_bep20','usdt_rate','monthly_bonus_percentage',
    'referral_level_2_percentage','referral_level_3_percentage','referral_level_4_percentage','referral_level_5_percentage',
    'default_sponsor_enabled','orphan_prevention','transfer_charge_percentage','transfer_minimum_amount','transfer_maximum_amount',
    // Leadership Passive
    'leadership_enabled','direct_package_quota','min_direct_count','leadership_levels',
    'leadership_level_1_percentage','leadership_level_2_percentage','leadership_level_3_percentage',
    'leadership_level_4_percentage','leadership_level_5_percentage',
];
$settings = [];
foreach ($keys as $k) $settings[$k] = getAdminSetting($k) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        /* ------ SIDEBAR RESPONSIVENESS (1-to-1 from reference) ------ */
        .sidebar-desktop { display: none; }
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
            .main-content { margin-left: 250px; }
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

        /* ------ CARD ANIMATIONS (identical to reference) ------ */
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
        .dashboard-logo {
            width: calc(100% - 2rem);
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
        .admin-badge {
            display: inline-block;
            background: #ff4757;
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .08em;
            padding: .15rem .2rem;
            border-radius: .375rem;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(0,0,0,.2);
        }

        /* small tweaks for settings form */
        .card-header h5 { margin: 0; }
        .input-group-text { min-width: 46px; }
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

<!-- Mobile Sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white" id="mobileSidebar" tabindex="-1">
  <div class="offcanvas-header">
      <div class="d-flex align-items-center gap-2 w-100">
          <span class="admin-badge mx-auto">ADMIN</span>
          <img src="../assets/images/logo.png" alt="<?= SITE_NAME ?>" style="height: 28px;" class="ms-auto">
      </div>
      <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav flex-column gap-2">
      <li><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
      <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
      <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>withdrawals</a></li>
      <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
      <li><a href="settings.php" class="nav-link text-white active"><i class="fas fa-cog me-2"></i>Settings</a></li>
      <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Packages</a></li>
      <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
  </div>
</div>

<!-- Desktop Sidebar -->
<nav class="sidebar-desktop">
  <div class="p-3">
      <span class="admin-badge">ADMIN</span>
      <div class="text-center mb-4">
          <img src="../assets/images/logo.png" alt="<?= SITE_NAME ?>" class="dashboard-logo">
      </div>
      <ul class="nav flex-column gap-2">
          <li><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
          <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
          <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>withdrawals</a></li>
          <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
          <li><a href="settings.php" class="nav-link text-white active"><i class="fas fa-cog me-2"></i>Settings</a></li>
          <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Packages</a></li>
          <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
      </ul>
  </div>
</nav>

<main class="main-content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pt-3 mb-4">
            <h1 class="h3 fw-bold">System Settings</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <!-- Payment -->
            <div class="card">
                <div class="card-header bg-primary text-white"><h5><i class="fas fa-wallet me-2"></i>Payment</h5></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Admin USDT Wallet Address (TRC20)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" name="admin_usdt_wallet"
                                   value="<?= htmlspecialchars($settings['admin_usdt_wallet']) ?>"
                                   placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx" maxlength="50">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Admin USDT Wallet Address (BEP20)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" name="admin_usdt_wallet_bep20"
                                   value="<?= htmlspecialchars($settings['admin_usdt_wallet_bep20'] ?? '') ?>"
                                   placeholder="0xXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" maxlength="42">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">USDT Rate</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                            <input type="number" class="form-control" name="usdt_rate" step="0.01" min="0.1"
                                   value="<?= htmlspecialchars($settings['usdt_rate']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bonus -->
            <div class="card mt-4">
                <div class="card-header bg-success text-white"><h5><i class="fas fa-gift me-2"></i>Bonuses (%)</h5></div>
                <div class="card-body row g-3">
                    <?php foreach ([
                        'monthly_bonus_percentage'=>'Monthly Bonus',
                        'referral_level_2_percentage'=>'Level 2',
                        'referral_level_3_percentage'=>'Level 3',
                        'referral_level_4_percentage'=>'Level 4',
                        'referral_level_5_percentage'=>'Level 5',
                    ] as $k => $label): ?>
                        <div class="col-sm-6 col-md-4 col-lg">
                            <label class="form-label"><?= $label ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="<?= $k ?>" min="0" max="100"
                                       value="<?= htmlspecialchars($settings[$k]) ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Transfer -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark"><h5><i class="fas fa-exchange-alt me-2"></i>Transfer Limits</h5></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Charge (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="transfer_charge_percentage" step="0.01" min="0"
                                   value="<?= htmlspecialchars($settings['transfer_charge_percentage']) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Minimum (<?= DEFAULT_CURRENCY ?>)</label>
                        <input type="number" class="form-control" name="transfer_minimum_amount" step="0.01" min="0"
                               value="<?= htmlspecialchars($settings['transfer_minimum_amount']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Maximum (<?= DEFAULT_CURRENCY ?>)</label>
                        <input type="number" class="form-control" name="transfer_maximum_amount" step="0.01" min="0"
                               value="<?= htmlspecialchars($settings['transfer_maximum_amount']) ?>">
                    </div>
                </div>
            </div>

            <!-- Leadership Passive -->
            <div class="card mt-4">
                <div class="card-header bg-danger text-white"><h5><i class="fas fa-crown me-2"></i>Leadership Passive</h5></div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Enable</label>
                        <?php $leadership_enabled = (string)($settings['leadership_enabled'] ?? '0'); ?>
                        <select class="form-select" name="leadership_enabled">
                            <option value="1" <?= $leadership_enabled === '1' ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= $leadership_enabled !== '1' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Direct Package Quota (USDT)</label>
                        <input type="number" class="form-control" name="direct_package_quota" min="0" step="1"
                               value="<?= htmlspecialchars($settings['direct_package_quota'] ?? '1000') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Minimum Direct Count</label>
                        <input type="number" class="form-control" name="min_direct_count" min="1"
                               value="<?= htmlspecialchars($settings['min_direct_count'] ?? '3') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max Levels</label>
                        <input type="number" class="form-control" name="leadership_levels" min="1" max="5"
                               value="<?= htmlspecialchars($settings['leadership_levels'] ?? '5') ?>">
                    </div>
                    <?php for ($l = 1; $l <= 5; $l++): ?>
                        <div class="col-md-2">
                            <label class="form-label">Level <?= $l ?> %</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="leadership_level_<?= $l ?>_percentage" min="0" max="100"
                                       value="<?= htmlspecialchars($settings["leadership_level_{$l}_percentage"] ?? ($l == 1 ? 10 : ($l == 2 ? 5 : ($l == 3 ? 3 : ($l == 4 ? 2 : 1))))) ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- System -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white"><h5><i class="fas fa-cogs me-2"></i>System</h5></div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="default_sponsor_enabled" value="1"
                               <?= $settings['default_sponsor_enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Auto-assign admin sponsor</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="orphan_prevention" value="1"
                               <?= $settings['orphan_prevention'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Prevent orphaned users</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">
                <i class="fas fa-save me-2"></i>Save Settings
            </button>
        </form>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* reveal cards on scroll – identical to reference */
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.card');
    const observer = new IntersectionObserver(entries =>
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('card-visible'), i * 150);
            }
        }),
        { threshold: 0.2 }
    );
    cards.forEach(card => observer.observe(card));
});
</script>
</body>
</html>