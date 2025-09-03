<?php
// user/dashboard.php – Complete with Package Actions Summary
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user    = getUserById($user_id);

/* ---------------------------------------------
   Package action counters
----------------------------------------------- */
$actionStats = [
    'pullout' => 0,
    'retain'  => 0,
    'recycle' => 0,
];

try {
    $pdo = getConnection();

    // 1. Count pull-outs (status = withdrawn)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_packages 
        WHERE user_id = ? AND status = 'withdrawn'
    ");
    $stmt->execute([$user_id]);
    $actionStats['pullout'] = (int)$stmt->fetchColumn();

    // 2. Count daily packages recycled (status = completed + mode = daily)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? 
          AND up.status = 'completed'
          AND p.mode = 'daily'
    ");
    $stmt->execute([$user_id]);
    $actionStats['recycle'] = (int)$stmt->fetchColumn();

    // 3. Count retain actions (monthly package reset to cycle 1)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? 
          AND up.status = 'active'
          AND p.mode = 'monthly'
          AND up.current_cycle = 1
    ");
    $stmt->execute([$user_id]);
    $actionStats['retain'] = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    // silently ignore on failure
}

$stats = array_merge(
    getUserStats($user_id),
    $actionStats
);

try {
    $pdo = getConnection();

    /* ---------- MONTHLY PACKAGES ---------- */
    $stmt = $pdo->prepare("
        SELECT up.*, p.name, p.price, p.mode, p.maturity_period,
               (p.price * ? / 100) AS monthly_bonus,
               CASE
                   WHEN up.current_cycle > p.maturity_period THEN 'withdraw_remine'
                   WHEN up.current_cycle = p.maturity_period THEN 'last_month'
                   ELSE 'earning'
               END AS bonus_status
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ?
          AND up.status IN ('active', 'completed')
          AND p.mode = 'monthly'
        ORDER BY up.created_at DESC
    ");
    $stmt->execute([MONTHLY_BONUS_PERCENTAGE, $user_id]);
    $monthly_packages = $stmt->fetchAll();

    /* ---------- DAILY PACKAGES ---------- */
    $stmt = $pdo->prepare("
        SELECT up.*,
               p.name,
               p.price,
               p.daily_percentage,
               p.maturity_period,
               CONCAT(up.current_cycle - 1, '/', p.maturity_period) AS days_string,
               u.status,
               CASE
                   WHEN up.current_cycle >= (p.maturity_period + 1) THEN 'recycle'
                   ELSE 'earning'
               END AS bonus_status,
               CASE
                   WHEN up.current_cycle >= (p.maturity_period + 1) THEN 'mature'
                   WHEN u.status = 'active' THEN 'active'
                   ELSE 'inactive'
               END AS display_status
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        JOIN users u ON u.id = up.user_id
        WHERE up.user_id = ?
          AND up.status IN ('active', 'completed')
          AND p.mode = 'daily'
        ORDER BY up.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $daily_packages = $stmt->fetchAll();

    $packages = getUserPackageHistory($user_id);

} catch (Exception $e) {
    $monthly_packages = $daily_packages = $packages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= SITE_NAME ?></title>
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
            padding: 1.5rem 1rem;
            border-radius: 1rem 1rem 0 0;
        }
        .badge {
            font-size: .75rem;
            padding: .5em 1em;
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
<button class="btn btn-primary shadow position-fixed d-lg-none" style="top: 1rem; left: 1rem; z-index: 1050;"
        type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white" id="mobileSidebar" tabindex="-1">
    <div class="offcanvas-header">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column gap-2">
            <li><a href="dashboard.php" class="nav-link text-white active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Mining</a></li>
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
            <img src="../assets/images/logo.png" alt="<?= SITE_NAME ?>" class="dashboard-logo mb-4">
        </div>
        <ul class="nav flex-column gap-2">
            <li><a href="dashboard.php" class="nav-link text-white active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Mining</a></li>
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pt-3 mb-4">
            <h1 class="h3 fw-bold">Welcome back, <?= htmlspecialchars($user['username']) ?>!</h1>
        </div>

        <!-- Package Actions Summary -->
        <!-- <div class="row g-3 mb-4">
            <?php foreach ([
                ['icon'=>'fas fa-box-open','title'=>'Pull-out','value'=>$stats['pullout'],'color'=>'danger'],
                ['icon'=>'fas fa-redo','title'=>'Retain','value'=>$stats['retain'],'color'=>'primary'],
                ['icon'=>'fas fa-recycle','title'=>'Recycle','value'=>$stats['recycle'],'color'=>'info'],
            ] as $act): ?>
                <div class="col-md-4">
                    <div class="card text-center card-visible">
                        <div class="card-body">
                            <i class="<?= $act['icon'] ?> fa-2x text-<?= $act['color'] ?> mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $act['value'] ?></h5>
                            <small class="text-muted"><?= $act['title'] ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div> -->

        <!-- Existing Stats Row -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['icon'=>'fas fa-wallet','title'=>'E-Wallet','value'=>formatCurrency($stats['ewallet_balance'])],
                ['icon'=>'fas fa-users','title'=>'Referrals','value'=>$stats['total_referrals']],
                ['icon'=>'fas fa-box','title'=>'Active','value'=>$stats['active_packages']],
                ['icon'=>'fas fa-chart-line','title'=>'Bonuses','value'=>formatCurrency($stats['total_bonuses'])],
            ] as $stat): ?>
                <div class="col-6 col-md">
                    <div class="card border-0 shadow-sm text-center h-100 card-visible">
                        <div class="card-body">
                            <i class="<?= $stat['icon'] ?> fa-2x text-primary mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $stat['value'] ?></h5>
                            <small class="text-muted"><?= $stat['title'] ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Monthly Bonus Card -->
        <!-- <div class="card shadow-sm mb-4 card-visible">
            <div class="card-header bg-primary text-white fw-bold">Monthly Active Mining</div>
            <div class="card-body">
                <?php if (empty($monthly_packages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No monthly packages ready for bonus</p>
                        <a href="packages.php" class="btn btn-primary">Mine Package</a>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($monthly_packages as $pkg):
                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bonus_wallet WHERE user_package_id = ?");
                            $stmt->execute([$pkg['id']]);
                            $accumulated = (float)$stmt->fetchColumn();
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($pkg['name']) ?></h6>
                                            <span class="text-muted fw-bold"><?= formatCurrency($pkg['price']) ?></span>
                                        </div>
                                        <small class="text-muted d-block">Mined: <strong class="text-success"><?= formatCurrency($accumulated) ?></strong></small>
                                        <small class="text-muted d-block">Days: <?= (new DateTime($pkg['purchase_date']))->diff(new DateTime())->days ?>/<?= $pkg['maturity_period'] ?></small>
                                        <small class="text-muted d-block">Cycle: <?= $pkg['current_cycle'] ?>/<?= $pkg['total_cycles'] ?></small>
                                        <?php if ($pkg['bonus_status'] === 'withdraw_remine'): ?>
                                            <div class="mt-2">
                                                <a href="package_action.php?id=<?= $pkg['id'] ?>&action=pullout" class="btn btn-success btn-sm">Pull-out</a>
                                                <a href="package_action.php?id=<?= $pkg['id'] ?>&action=retain" class="btn btn-primary btn-sm">Retain</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- Daily Mining Bonus -->
        <div class="card shadow-sm mb-4 card-visible">
            <div class="card-header bg-success text-white fw-bold">Daily Active Mining</div>
            <div class="card-body">
                <?php if (empty($daily_packages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No daily packages ready for bonus</p>
                        <a href="packages.php" class="btn btn-primary">Mine Daily Package</a>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($daily_packages as $pkg):
                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bonus_wallet WHERE user_package_id = ?");
                            $stmt->execute([$pkg['id']]);
                            $accumulated = (float)$stmt->fetchColumn();
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($pkg['name']) ?></h6>
                                            <span class="text-muted fw-bold"><?= formatCurrency($pkg['price']) ?></span>
                                        </div>
                                        <small class="text-muted d-block">Mined: <strong class="text-success"><?= formatCurrency($accumulated) ?></strong></small>
                                        <small class="text-muted d-block">Days: <?= htmlspecialchars($pkg['days_string']) ?></small>
                                        <small class="text-muted d-block">Status: 
                                            <span class="badge bg-<?=
                                                $pkg['display_status'] === 'mature'   ? 'success' :
                                                ($pkg['display_status'] === 'active'  ? 'primary' : 'danger')
                                            ?>"><?= ucfirst($pkg['display_status']) ?></span>
                                        </small>
                                        <?php if ($pkg['bonus_status'] === 'recycle'): ?>
                                            <div class="mt-2">
                                                <a href="packages.php" class="btn btn-info btn-sm">Add Mine</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        /* ---------- Leadership Passive Card ---------- */
        $lpEnabled = getAdminSetting('leadership_enabled') == '1';
        if ($lpEnabled):
            /* Current Cycle */
            $cycle   = date('Y-m-01');
            $user_id = getCurrentUserId();

            /* Bonus Totals */
            $pdo = getConnection();
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            $stmt = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN cycle = 1 THEN amount ELSE 0 END) AS daily_bonus,
                    SUM(CASE WHEN cycle  > 1 THEN amount ELSE 0 END) AS monthly_bonus
                FROM bonus_wallet
                WHERE user_id = :uid
                  AND DATE_FORMAT(created_at, '%Y-%m-01') = :cycle
            ");
            $stmt->execute(['uid' => $user_id, 'cycle' => $cycle]);
            $bonusRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $daily   = (float)($bonusRow['daily_bonus']   ?? 0);
            $monthly = (float)($bonusRow['monthly_bonus'] ?? 0);

            /* Leadership Passive Breakdown */
            $stmt = $pdo->prepare("
                SELECT level, amount, created_at
                FROM leadership_passive
                WHERE beneficiary_id = :uid
                  AND month_cycle    = :cycle
                ORDER BY level ASC
            ");
            $stmt->execute(['uid' => $user_id, 'cycle' => $cycle]);
            $lpRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalLp = array_sum(array_column($lpRows, 'amount'));
        ?>
            <!-- <div class="card mb-4 shadow-sm card-visible">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-crown me-2"></i>Executive Bonus</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3 text-center">
                        <div class="col-md-4">
                            <div class="fw-bold">Daily Bonus</div>
                            <span class="text-success fs-5"><?= number_format($daily, 2) ?> USDT</span>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold">Monthly Bonus</div>
                            <span class="text-success fs-5"><?= number_format($monthly, 2) ?> USDT</span>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold">Leadership Passive</div>
                            <span class="text-primary fs-5"><?= number_format($totalLp, 2) ?> USDT</span>
                        </div>
                    </div>
                    <?php if ($lpRows): ?>
                        <h6 class="mt-4 mb-2">Breakdown by Level</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered align-middle">
                                <thead class="table-light">
                                    <tr><th width="20%">Level</th><th width="40%">Amount (USDT)</th><th width="40%">Received At</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lpRows as $r): ?>
                                        <tr>
                                            <td><?= $r['level'] ?></td>
                                            <td><?= number_format($r['amount'], 2) ?></td>
                                            <td><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-center mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            No Leadership Passive earned yet this month.
                        </div>
                    <?php endif; ?>
                </div>
            </div> -->
        <?php endif; ?>

        <!-- Package History -->
        <div class="card shadow-sm card-visible">
            <div class="card-header bg-secondary text-white fw-bold">Overall Mining History</div>
            <div class="card-body">
                <?php if (!$packages): ?>
                    <p class="text-muted text-center py-3">No packages yet</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr><th>Package</th><th>Price</th><th>Mode</th><th>Status</th><th>Purchased</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= formatCurrency($p['price']) ?></td>
                                        <td><?= ucfirst($p['mode']) ?></td>
                                        <td><span class="badge bg-info"><?= ucfirst($p['status'] === 'withdrawn' ? 'pullout' : $p['status']) ?></span></td>
                                        <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* reveal cards on scroll */
    document.addEventListener('DOMContentLoaded', () => {
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