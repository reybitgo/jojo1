<?php
// user/ewallet.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);
$transactions = getTransactionHistory($user_id, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>E-Wallet - <?= SITE_NAME ?></title>
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
        .balance-card, .action-card, .history-card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            transition: .4s;
            transform: translateY(20px);
            opacity: 0;
        }
        .balance-card.card-visible, .action-card.card-visible, .history-card.card-visible {
            transform: translateY(0);
            opacity: 1;
        }
        .balance-card:hover, .action-card:hover, .history-card:hover {
            transform: translateY(-12px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(0,0,0,.15);
        }
        .balance-header, .action-header {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.5rem 1rem;
            border-radius: 1rem 1rem 0 0;
            text-align: center;
        }
        .balance-icon {
            font-size: 2.5rem;
            color: var(--primary);
        }
        .balance-amount {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }
        .action-btn {
            font-size: 1.1rem;
            font-weight: 500;
            border-radius: .5rem;
            padding: .75rem;
            transition: transform .3s, box-shadow .3s;
        }
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,.1);
        }
        .wallet-links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
        }
        .wallet-links img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            transition: transform .3s;
        }
        .wallet-links img:hover {
            transform: scale(1.1);
        }
        .table-responsive {
            border-radius: .5rem;
            overflow-x: auto;
        }
        .table th, .table td {
            vertical-align: middle;
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
            <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
                <h1 class="h3 fw-bold">E-Wallet Management</h1>
                <div class="wallet-links">
                    <h3 class="mb-0" style="font-size: 1rem; font-weight: bold;">Preferred Wallets:</h3>
                    <a href="https://www.mexc.com/" target="_blank" rel="noopener" title="MEXC">
                        <img src="../assets/images/mexc.png" alt="MEXC">
                    </a>
                    <a href="https://trustwallet.com/" target="_blank" rel="noopener" title="Trust Wallet">
                        <img src="../assets/images/trust_wallet.png" alt="Trust Wallet">
                    </a>
                    <a href="https://okx.com/" target="_blank" rel="noopener" title="OKX">
                        <img src="../assets/images/okx.png" alt="OKX">
                    </a>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card balance-card h-100">
                        <div class="balance-header">
                            <i class="fas fa-wallet balance-icon mb-3"></i>
                            <h3 class="balance-amount"><?= formatCurrency($balance) ?></h3>
                            <p class="text-muted mb-0">Available Balance</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-8 col-md-6">
                    <div class="card action-card h-100">
                        <div class="action-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <a href="withdrawal.php" class="btn btn-danger action-btn w-100">
                                        <i class="fas fa-arrow-down me-2"></i>Withdraw
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="refill.php" class="btn btn-success action-btn w-100">
                                        <i class="fas fa-arrow-up me-2"></i>Add Funds
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="transfer.php" class="btn btn-warning action-btn w-100">
                                        <i class="fas fa-arrow-right me-2"></i>Transfer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="card history-card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Transaction History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted">No transactions yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?= formatDate($tx['created_at']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $tx['type'] === 'deposit' ? 'success' :
                                                    ($tx['type'] === 'withdrawal' ? 'danger' :
                                                        ($tx['type'] === 'purchase' ? 'warning' : 'info'))
                                                    ?>">
                                                    <?= ucfirst(match ($tx['type']) {
                                                        'refund' => 'withdrawal',
                                                        'bonus'  => 'mined',
                                                        default  => $tx['type'],
                                                    }) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($tx['description']) ?></td>
                                            <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $tx['amount'] > 0 ? '+' : '' ?><?= formatCurrency($tx['amount']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $tx['status'] === 'completed' ? 'success' :
                                                    ($tx['status'] === 'pending' ? 'warning' : 'danger')
                                                    ?>">
                                                    <?= ucfirst($tx['status']) ?>
                                                </span>
                                            </td>
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
        const cards = document.querySelectorAll('.balance-card, .action-card, .history-card');
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