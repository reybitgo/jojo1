<?php
// admin/user_details.php – modern, no top bar
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$userId    = (int)($_GET['id'] ?? 0);
$user      = getUserById($userId);

if (!$user) {
    redirectWithMessage('users.php', 'User not found.', 'error');
}

$packages    = getUserPackageHistory($userId);
$balance     = getEwalletBalance($userId);
$withdrawals = getUserWithdrawalRequests($userId);
$refills     = getUserRefillRequests($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Details - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                z-index: 1040; /* lower than modals */
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
    </style>
</head>
<body>

<!-- Mobile Toggle -->
<button class="btn btn-primary shadow position-fixed d-lg-none"
        style="top:1rem;left:1rem;z-index:1050"
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
      <li><a href="users.php" class="nav-link text-white active"><i class="fas fa-users me-2"></i>Users</a></li>
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
          <li><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
          <li><a href="users.php" class="nav-link text-white active"><i class="fas fa-users me-2"></i>Users</a></li>
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
            <h1 class="h3 fw-bold">User Details</h1>
        </div>

        <!-- Account Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white fw-bold">Account</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>First Name:</strong> <?= htmlspecialchars($user['first_name'] ?? 'N/A') ?></p>
                        <p><strong>Middle Name:</strong> <?= htmlspecialchars($user['middle_name'] ?? 'N/A') ?></p>
                        <p><strong>Last Name:</strong> <?= htmlspecialchars($user['last_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong>Status:</strong>
                            <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Packages -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white fw-bold">Packages</div>
            <div class="card-body">
                <?php if (!$packages): ?>
                    <p class="text-muted text-center py-3">No packages purchased.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr><th>Package</th><th>Price</th><th>Status</th><th>Purchased</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= formatCurrency($p['price']) ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= ucfirst($p['status'] === 'withdrawn' ? 'pullout' : $p['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5><?= formatCurrency($balance) ?></h5>
                        <small class="text-muted">Balance</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5><?= count($withdrawals) ?></h5>
                        <small class="text-muted">Withdrawals</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5><?= count($refills) ?></h5>
                        <small class="text-muted">Refills</small>
                    </div>
                </div>
            </div>
        </div>  

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* reveal cards – identical to reference */
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