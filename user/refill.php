<?php
// user/refill.php – supports both TRC20 & BEP20 USDT networks
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$errors  = [];
$success = '';

// Grab amount from URL and clamp to ≥ 20
$prefill = max(10, floatval($_GET['amount'] ?? 10));

// Fetch the two wallet addresses
$admin_trc20 = getAdminSetting('admin_usdt_wallet')      ?: 'TAdminUSDTWalletAddressHere12345';
$admin_bep20 = getAdminSetting('admin_usdt_wallet_bep20') ?: '0xAdminUSDTWalletAddressHere12345';

// Handle refill request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $validation = validateFormData($_POST, [
            'amount'   => ['required' => true, 'numeric' => true, 'min_value' => 10],
            'network'  => ['required' => true, 'regex' => '/^(trc20|bep20)$/']
        ]);

        if ($validation['valid']) {
            $amount         = floatval($_POST['amount']);
            $network        = $_POST['network'];               // trc20 or bep20
            $wallet_used    = $network === 'bep20' ? $admin_bep20 : $admin_trc20;
            $tx_hash        = trim($_POST['transaction_hash'] ?? '');

            try {
                $pdo = getConnection();
                $pdo->beginTransaction();

                // 1. Create refill request
                $stmt = $pdo->prepare("
                    INSERT INTO refill_requests (user_id, amount, transaction_hash, network)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $amount, $tx_hash, $network]);
                $refill_id = $pdo->lastInsertId();

                // 2. Add pending transaction
                $stmt = $pdo->prepare("
                    INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status)
                    VALUES (?, 'deposit', ?, ?, ?, 'pending')
                ");
                $desc = strtoupper($network) . " USDT refill pending approval";
                $stmt->execute([$user_id, $amount, $desc, $refill_id]);

                $pdo->commit();
                $success = 'Refill request submitted successfully. Your account will be credited once admin approves.';

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['general'] = 'DB Error: ' . $e->getMessage();   // ← will reveal the actual problem
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}

// --- pull user’s refill history ---
$refillHistory = [];
$totalRefill   = 0;
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT amount, status, created_at, admin_notes
        FROM refill_requests
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $refillHistory = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM refill_requests WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$user_id]);
    $totalRefill = (float) $stmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Funds - <?= SITE_NAME ?></title>
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
        .form-control, .form-select, .btn {
            border-radius: .5rem;
        }
        .btn-success {
            padding: .75rem 1.5rem;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-success:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,.1);
        }
        .table {
            border-radius: .5rem;
            overflow: hidden;
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 pt-3">
                <h1 class="h3 fw-bold">Add Funds to E-Wallet</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
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
                <!-- Left card: form -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-up me-2"></i>Refill Request</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <!-- Amount – now pre-filled and clamped to ≥ 10 -->
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount to Add (USDT)</label>
                                    <input type="number" 
                                           class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                           id="amount" name="amount"
                                           min="10" step="0.01"
                                           value="<?= htmlspecialchars($prefill) ?>"
                                           required>
                                    <small class="text-muted">Minimum: 10 USDT</small>
                                    <?php if (isset($errors['amount'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Network selector -->
                                <div class="mb-3">
                                    <label for="networkSelect" class="form-label">Select Network</label>
                                    <select class="form-select" id="networkSelect" name="network" required>
                                        <option value="trc20" <?= ($_POST['network'] ?? '') === 'trc20' ? 'selected' : '' ?>>TRC20</option>
                                        <option value="bep20" <?= ($_POST['network'] ?? '') === 'bep20' ? 'selected' : '' ?>>BEP20</option>
                                    </select>
                                </div>

                                <!-- Wallet address display -->
                                <div class="mb-3">
                                    <label class="form-label">Send to Wallet</label>
                                    <div class="bg-light p-3 rounded" id="trcAddressDiv">
                                        <code class="fw-bold" id="trcAddress"><?= htmlspecialchars($admin_trc20) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-primary float-end"
                                                onclick="copyToClipboard('<?= htmlspecialchars($admin_trc20) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="bg-light p-3 rounded" id="bepAddressDiv" style="display:none;">
                                        <code class="fw-bold" id="bepAddress"><?= htmlspecialchars($admin_bep20) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-primary float-end"
                                                onclick="copyToClipboard('<?= htmlspecialchars($admin_bep20) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Transaction hash -->
                                <div class="mb-3">
                                    <label for="transaction_hash" class="form-label">Transaction Hash (Optional)</label>
                                    <input type="text" class="form-control" id="transaction_hash" name="transaction_hash"
                                           placeholder="0x... or T... (hash)">
                                    <small class="text-muted">Paste your transaction hash after sending USDT</small>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-2"></i>Submit Refill Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right card: instructions -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle me-2"></i>Payment Instructions</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning border-start border-warning border-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shield-alt fa-2x me-3 text-warning"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Refill Instructions</h5>
                                        <p class="mb-0 small">Follow the steps below to ensure a smooth and secure deposit.</p>
                                    </div>
                                </div>
                            </div>
                            <ul class="list-unstyled ps-3">
                                <li class="d-flex align-items-start mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <span>Select the correct network (<strong>TRC20</strong> or <strong>BEP20</strong>) before sending.</span>
                                </li>
                                <li class="d-flex align-items-start mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <span>Transfer the <strong>exact amount</strong> shown above—any difference may delay crediting.</span>
                                </li>
                                <li class="d-flex align-items-start mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <span>Use <strong>only</strong> the displayed wallet address for the chosen network.</span>
                                </li>
                                <li class="d-flex align-items-start mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <span>Paste the transaction hash and submit the form after the transfer.</span>
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <span>Allow <strong>1–6 business hours</strong> for approval and wallet credit.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Refill History -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Refill History</h5>
                        <span class="fw-bold text-primary">Total Refill: <?= formatCurrency($totalRefill) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!$refillHistory): ?>
                            <p class="text-muted mb-0">No refill requests yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($refillHistory as $r): ?>
                                            <tr>
                                                <td><?= formatDate($r['created_at']) ?></td>
                                                <td><?= formatCurrency($r['amount']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                        $r['status'] === 'approved' ? 'success' :
                                                        ($r['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($r['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($r['admin_notes'] ?: '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sel   = document.getElementById('networkSelect');
            const trc   = document.getElementById('trcAddressDiv');
            const bep   = document.getElementById('bepAddressDiv');

            const toggle = () => {
                trc.style.display = sel.value === 'trc20' ? 'block' : 'none';
                bep.style.display = sel.value === 'bep20' ? 'block' : 'none';
            };
            sel.addEventListener('change', toggle);
            toggle();   // initial

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

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => alert('Copied!'));
        }
    </script>
</body>
</html>