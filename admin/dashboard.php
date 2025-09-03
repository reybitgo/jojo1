<?php
// admin/dashboard.php – Global Package Actions + existing stats
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');
date_default_timezone_set('Asia/Manila');

/* ----------  GLOBAL METRICS  ---------- */
try {
    $pdo = getConnection();

    // Core metrics
    $total_users         = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $active_packages     = $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'active'")->fetchColumn();
    $pullout_packages    = $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'withdrawn'")->fetchColumn();
    $retain_packages     = $pdo->query("
        SELECT COUNT(*) 
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE p.mode = 'monthly' 
          AND up.status = 'active' 
          AND up.current_cycle = 1
    ")->fetchColumn();
    $recycle_packages    = $pdo->query("
        SELECT COUNT(*) 
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE p.mode = 'daily' 
          AND up.status = 'completed'
    ")->fetchColumn();
    $active_users        = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_packages WHERE status = 'active'")->fetchColumn();
    $total_earnings      = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ewallet_transactions WHERE type IN ('purchase','transfer_charge')")->fetchColumn();
    $pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
    $pending_refills     = $pdo->query("SELECT COUNT(*) FROM refill_requests WHERE status = 'pending'")->fetchColumn();

    // Recent activity
    $recent_users        = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_transactions = $pdo->query("SELECT et.*, u.username FROM ewallet_transactions et JOIN users u ON et.user_id = u.id ORDER BY et.created_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) {
    $error = "Failed to load admin data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
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
        /* --- SIDEBAR RESPONSIVENESS (exact copy from user side) --- */
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

        /* --- CARD ANIMATIONS (identical to user side) --- */
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
    <div class="d-flex align-items-center gap-2 w-100">
        <span class="admin-badge mx-auto">ADMIN</span>
        <img src="../assets/images/logo.png" alt="<?= SITE_NAME ?>" style="height: 28px;" class="ms-auto">
    </div>
    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column gap-2">
            <li><a href="dashboard.php" class="nav-link text-white active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
            <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>Withdrawals</a></li>
            <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
            <li><a href="settings.php" class="nav-link text-white"><i class="fas fa-cog me-2"></i>Settings</a></li>
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
            <li><a href="dashboard.php" class="nav-link text-white active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
            <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
            <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>Withdrawals</a></li>
            <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
            <li><a href="settings.php" class="nav-link text-white"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li><a href="packages.php" class="nav-link text-white"><i class="fas fa-box me-2"></i>Packages</a></li>
            <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</nav>

<main class="main-content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pt-3 mb-4">
            <h1 class="h3 fw-bold">Admin Dashboard</h1>
        </div>

        <!-- Global Package Actions Summary -->
        <!-- <div class="row g-3 mb-4">
            <?php foreach ([
                ['icon'=>'fas fa-box-open','title'=>'Pull-outs','value'=>$pullout_packages,'color'=>'danger'],
                ['icon'=>'fas fa-redo','title'=>'Retains','value'=>$retain_packages,'color'=>'primary'],
                ['icon'=>'fas fa-recycle','title'=>'Recycles','value'=>$recycle_packages,'color'=>'info'],
            ] as $act): ?>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="<?= $act['icon'] ?> fa-2x text-<?= $act['color'] ?> mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $act['value'] ?></h5>
                            <small class="text-muted"><?= $act['title'] ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div> -->

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <?php foreach ([
              ['icon'=>'fas fa-users','title'=>'Total Members','value'=>number_format($total_users),'color'=>'primary'],
              ['icon'=>'fas fa-box','title'=>'Active Packages','value'=>number_format($active_packages),'color'=>'primary'],
              ['icon'=>'fas fa-user-check','title'=>'Active Users','value'=>number_format($active_users),'color'=>'success'],
              ['icon'=>'fas fa-money-bill-wave','title'=>'Total Entries','value'=>formatCurrency($total_earnings * (-1)),'color'=>'primary'],
              ['icon'=>'fas fa-exclamation-triangle','title'=>'Pending Transactions','value'=>$pending_withdrawals + $pending_refills,'color'=>'warning'],
            ] as $stat): ?>
                <div class="col-md-4 col-lg mb-3">
                    <div class="card border-0 shadow-sm text-center h-100">
                        <div class="card-body">
                            <i class="<?= $stat['icon'] ?> fa-2x text-primary mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $stat['value'] ?></h5>
                            <small class="text-muted"><?= $stat['title'] ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pending Requests -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-arrow-down me-2"></i>Pending Withdrawals</div>
                    <div class="card-body">
                        <?php if ($pending_withdrawals > 0): ?>
                            <a href="withdrawals.php?status=pending" class="btn btn-warning">
                                View <?= $pending_withdrawals ?> pending withdrawal<?= $pending_withdrawals > 1 ? 's' : '' ?>
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-0">No pending withdrawals</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white fw-bold"><i class="fas fa-arrow-up me-2"></i>Pending Refills</div>
                    <div class="card-body">
                        <?php if ($pending_refills > 0): ?>
                            <a href="refills.php?status=pending" class="btn btn-info">
                                View <?= $pending_refills ?> pending refill<?= $pending_refills > 1 ? 's' : '' ?>
                            </a>
                        <?php else: ?>
                            <p class="text-muted mb-0">No pending refills</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-bold"><i class="fas fa-users me-2"></i>Recent Users</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead><tr><th>Username</th><th>Registered</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= timeAgo($user['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-bold"><i class="fas fa-history me-2"></i>Recent Transactions</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead><tr><th>User</th><th>Amount</th><th>Type</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_transactions as $tx): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tx['username']) ?></td>
                                            <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $tx['amount'] > 0 ? '+' : '' ?><?= formatCurrency($tx['amount']) ?>
                                            </td>
                                            <td><?= ucfirst(match ($tx['type']) {
                                                'refund' => 'withdrawal',
                                                'bonus'  => 'mined',
                                                default  => $tx['type'],
                                            }) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* reveal cards on scroll – identical to user side */
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