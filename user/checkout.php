<?php
// user/checkout.php – daily-aware & inactive-re-activation
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
$package    = $package_id ? getPackageById($package_id) : null;

if (!$package) {
    redirectWithMessage('packages.php', 'Invalid package selected.', 'error');
}

$user_id = getCurrentUserId();
$user    = getUserById($user_id);
$balance = getEwalletBalance($user_id);

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_purchase'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('packages.php', 'Invalid security token.', 'error');
    }

    // 1️⃣  Re-activate user if inactive
    if ($user['status'] === 'inactive') {
        try {
            $pdo = getConnection();
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$user_id]);
            logEvent("User $user_id re-activated via new package purchase", 'info');
        } catch (Exception $e) {
            redirectWithMessage('packages.php', 'Could not reactivate account.', 'error');
        }
    }

    // 2️⃣  If inactive user buys another daily package, withdraw previous daily ones
    if ($user['status'] === 'inactive' && $package['mode'] === 'daily') {
        try {
            $pdo = getConnection();
            $pdo->prepare("
                UPDATE user_packages up
                JOIN packages p ON up.package_id = p.id
                SET up.status = 'withdrawn'
                WHERE up.user_id = ?
                  AND p.mode = 'daily'
                  AND up.status IN ('active','completed')
            ")->execute([$user_id]);
        } catch (Exception $e) {
            // silently continue
        }
    }

    // 3️⃣  Attempt purchase
    $result = purchasePackage($user_id, $package['id']);
    if ($result['success']) {
        redirectWithMessage('dashboard.php', $result['message'], 'success');
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        :root { --primary:#667eea; --primary-dark:#1e3c72; }
        body  { background:#f5f7fa; font-family:'Segoe UI',sans-serif; }

        /* ---- Sidebar responsiveness (from packages.php) ---- */
        .sidebar-desktop{display:none;}
        @media(min-width:992px){
            .sidebar-desktop{display:block;width:250px;height:100vh;position:fixed;top:0;left:0;background:var(--primary-dark);color:#fff;padding-top:1rem;z-index:1000;}
            .main-content{margin-left:250px;}
        }
        @media(max-width:991px){
            body>.main-content{padding-top:1rem!important;margin-top:1rem!important;}
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
      <li><a href="packages.php" class="nav-link text-white active"><i class="fas fa-box me-2"></i>Mining</a></li>
      <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
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
      <li><a href="packages.php" class="nav-link text-white active"><i class="fas fa-box me-2"></i>Mining</a></li>
      <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
      <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
      <li><a href="genealogy.php" class="nav-link text-white"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
      <li><a href="profile.php" class="nav-link text-white"><i class="fas fa-user me-2"></i>Profile</a></li>
      <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
  </div>
</nav>

<main class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 pt-4">
            <h1 class="h3 fw-bold">Package Checkout</h1>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-box"></i> Package Details</h5>
                    </div>
                    <div class="card-body">
                        <h4><?= htmlspecialchars($package['name']) ?></h4>
                        <p class="text-muted">Price: <strong><?= formatCurrency($package['price']) ?></strong></p>
                        <p class="text-muted">Mode: <strong><?= ucfirst($package['mode']) ?></strong></p>
                        <?php if ($package['mode'] === 'daily'): ?>
                            <p class="text-muted">Daily Bonus: <strong><?= $package['daily_percentage'] ?>%</strong></p>
                        <?php else: ?>
                            <p class="text-muted">Monthly Bonus: <strong><?= MONTHLY_BONUS_PERCENTAGE ?>%</strong> for <?= BONUS_MONTHS ?> months</p>
                        <?php endif; ?>
                        <p class="text-muted">Your Balance: <strong><?= formatCurrency($balance) ?></strong></p>

                        <?php if ($balance < $package['price']): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Insufficient balance. 
                                <a href="refill.php?amount=<?= max(10, $package['price']) ?>" class="alert-link">
                                    Add funds
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shopping-cart"></i> Purchase Confirmation</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($balance >= $package['price']): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                <input type="hidden" name="confirm_purchase" value="1">

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Confirm Purchase
                                    </button>
                                    <a href="packages.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <a href="refill.php?amount=<?= max(20, $package['price']) ?>" 
                                   class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus"></i> Add Funds to E-Wallet (<?= formatCurrency($package['price']) ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>