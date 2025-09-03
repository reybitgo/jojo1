<?php
// user/withdrawal.php – JOJO Token Edition with Method Selection
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getWithdrawableBalance($user_id);

/* ----------  JOJO PRICE (live from CoinGecko) ---------- */
function getJojoPrice()
{
    $cacheFile = sys_get_temp_dir() . '/jojo_price.json';
    $ttl       = 30; // seconds

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $data = json_decode(file_get_contents($cacheFile), true);
    } else {
        $url  = 'https://api.coingecko.com/api/v3/simple/price?ids=jojo&vs_currencies=usd';
        $json = @file_get_contents($url);
        if ($json === false) { // fallback
            return 0.000001;
        }
        $data = json_decode($json, true)['jojo']['usd'] ?? 0.000001;
        file_put_contents($cacheFile, json_encode($data));
    }
    return (float) $data;
}

$jojoPrice = getJojoPrice();

$errors  = [];
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $usdtAmount = floatval($_POST['amount'] ?? 0);
        $wallet     = trim($_POST['wallet_address'] ?? '');
        $method     = $_POST['method'] ?? 'usdt_bep20';

        // Validate
        if ($usdtAmount < 10) {
            $errors['amount'] = 'Minimum withdrawal amount is 10 USDT';
        } elseif ($usdtAmount > $balance) {
            $errors['amount'] = 'Insufficient withdrawable balance';
        } elseif (empty($wallet)) {
            $errors['wallet_address'] = 'Wallet address is required';
        } elseif ($method === 'usdt_bep20' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            $errors['wallet_address'] = 'Invalid BEP-20 wallet address';
        } elseif ($method === 'jojo_token' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            $errors['wallet_address'] = 'Invalid BEP-20 wallet address for JOJO Token';
        } else {
            $withdrawal_charge = $usdtAmount * 0.1; // 10% fee on USDT
            $netUsdt = $usdtAmount - $withdrawal_charge;
            
            // For JOJO method, convert to JOJO tokens
            if ($method === 'jojo_token') {
                $jojoAmount = $netUsdt / $jojoPrice;
                $finalAmount = $jojoAmount; // Store JOJO amount
            } else {
                $finalAmount = $netUsdt; // Store USDT amount
            }

            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("
                    INSERT INTO withdrawal_requests
                      (user_id, amount, usdt_amount, method, wallet_address)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $finalAmount, $netUsdt, $method, $wallet]);

                $withdrawalId = $pdo->lastInsertId();

                processEwalletTransaction(
                    $user_id,
                    'withdrawal',
                    -$netUsdt,
                    'Withdrawal request pending approval',
                    $withdrawalId
                );

                addEwalletTransaction(1, 'withdrawal_charge', $withdrawal_charge, "Withdrawal charge from $user_id", null, 1);

                if ($method === 'jojo_token') {
                    $success = "Withdrawal request submitted successfully. You will receive <strong>"
                             . number_format($finalAmount, 2) . "</strong> JOJO tokens.";
                } else {
                    $success = "Withdrawal request submitted successfully. You will receive <strong>"
                             . number_format($finalAmount, 2) . "</strong> USDT.";
                }
            } catch (Exception $e) {
                $errors['general'] = $e->getMessage();
            }
        }
    }
}

// --- user's withdrawal history ---
$withdrawalHistory = [];
$totalWithdrawn    = 0;
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT amount, usdt_amount, method, wallet_address, status, created_at, processed_at
        FROM withdrawal_requests
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $withdrawalHistory = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(usdt_amount),0) FROM withdrawal_requests WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $totalWithdrawn = (float) $stmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdraw - <?= SITE_NAME ?></title>
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
        .jojo-price-info {
            font-size: .9rem;
            color: #666;
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
        .btn-danger {
            padding: .75rem 1.5rem;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-danger:hover {
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
            width: calc(100% - 2rem);
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
        .method-selection {
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .method-selection.active {
            border-color: var(--primary);
            background-color: rgba(102, 126, 234, 0.05);
        }
        .method-selection:hover {
            border-color: var(--primary);
        }
        .method-selection input[type="radio"] {
            display: none;
        }
        .method-selection label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        .method-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: var(--primary);
            font-size: 1.2rem;
        }
        .method-info h6 {
            margin: 0;
            font-weight: 600;
        }
        .method-info small {
            color: #6c757d;
        }
        #jojoPreviewSection {
            display: none;
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
                <h1 class="h3 fw-bold">Withdraw Funds</h1>
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
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-down me-2"></i>Withdrawal Request</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="mb-3">
                                    <label class="form-label">Current Balance (USDT)</label>
                                    <div class="alert alert-info">
                                        <i class="fas fa-wallet me-2"></i> <?= formatCurrency($balance) ?>
                                    </div>
                                </div>

                                <!-- Withdrawal Method Selection -->
                                <div class="mb-3">
                                    <label class="form-label">Withdrawal Method</label>
                                    
                                    <div class="method-selection active" data-method="usdt_bep20">
                                        <label for="method_usdt">
                                            <input type="radio" id="method_usdt" name="method" value="usdt_bep20" checked>
                                            <div class="method-icon">
                                                <i class="fas fa-dollar-sign"></i>
                                            </div>
                                            <div class="method-info">
                                                <h6>USDT (BEP-20)</h6>
                                                <small>Receive USDT tokens directly</small>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="method-selection" data-method="jojo_token">
                                        <label for="method_jojo">
                                            <input type="radio" id="method_jojo" name="method" value="jojo_token">
                                            <div class="method-icon">
                                                <i class="fas fa-coins"></i>
                                            </div>
                                            <div class="method-info">
                                                <h6>JOJO Token</h6>
                                                <small>Convert USDT to JOJO tokens (Live rate: <?= number_format($jojoPrice, 8) ?> USDT)</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount to Withdraw (USDT)</label>
                                    <input type="number"
                                           class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                           id="amount" name="amount" min="10" max="<?= $balance ?>" step="0.01"
                                           required>
                                    <small class="form-text text-muted">Minimum withdrawal: 10 USDT (10% processing fee will be deducted)</small>
                                    <?php if (isset($errors['amount'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="wallet_address" class="form-label">Wallet Address (BEP-20)</label>
                                    <input type="text"
                                           class="form-control <?= isset($errors['wallet_address']) ? 'is-invalid' : '' ?>"
                                           id="wallet_address" name="wallet_address"
                                           placeholder="0x..." required>
                                    <?php if (isset($errors['wallet_address'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['wallet_address']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Dynamic JOJO preview (hidden by default) -->
                                <div class="mb-3" id="jojoPreviewSection">
                                    <label class="form-label">You will receive (JOJO)</label>
                                    <div class="alert alert-light" id="jojoPreview">—</div>
                                </div>

                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Withdrawal Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle me-2"></i>Withdrawal Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Minimum withdrawal: 10 USDT</li>
                                <li><i class="fas fa-check text-success me-2"></i>10% withdrawal fee</li>
                                <li><i class="fas fa-check text-success me-2"></i>Processing time: 30 min – 24 h</li>
                                <li><i class="fas fa-check text-success me-2"></i>Network: BEP-20 (BSC)</li>
                                <li><i class="fas fa-check text-success me-2"></i>USDT Contract: 0x55d398326f99059fF775485246999027B3197955</li>
                                <li><i class="fas fa-check text-success me-2"></i>JOJO Token Contract: 0x78a499a998bdd5a84cf8b5abe49100d82de12f1c</li>
                            </ul>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Ensure your BEP-20 wallet address is correct to avoid loss of funds.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Withdrawal History -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Withdrawal History</h5>
                    <span class="fw-bold text-primary">Total Withdrawals: <?= formatCurrency($totalWithdrawn) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$withdrawalHistory): ?>
                        <p class="text-muted mb-0">No withdrawal requests yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>USDT Equivalent</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawalHistory as $w): ?>
                                        <tr>
                                            <td><?= formatDate($w['created_at']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($w['method'] ?? 'usdt_bep20') === 'jojo_token' ? 'warning' : 'primary' ?>">
                                                    <?= ($w['method'] ?? 'usdt_bep20') === 'jojo_token' ? 'JOJO Token' : 'USDT BEP-20' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (($w['method'] ?? 'usdt_bep20') === 'jojo_token'): ?>
                                                    <?= number_format($w['amount'], 2) ?> JOJO
                                                <?php else: ?>
                                                    <?= number_format($w['amount'], 2) ?> USDT
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatCurrency($w['usdt_amount']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $w['status'] === 'completed' ? 'success' :
                                                    ($w['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($w['status']) ?>
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
        // Method selection handling
        const methodSelections = document.querySelectorAll('.method-selection');
        const jojoPreviewSection = document.getElementById('jojoPreviewSection');
        const amountInput = document.getElementById('amount');
        const jojoPreview = document.getElementById('jojoPreview');
        const price = <?= $jojoPrice ?>;

        methodSelections.forEach(selection => {
            selection.addEventListener('click', function() {
                // Remove active class from all
                methodSelections.forEach(s => s.classList.remove('active'));
                // Add active class to clicked
                this.classList.add('active');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Show/hide JOJO preview
                if (radio.value === 'jojo_token') {
                    jojoPreviewSection.style.display = 'block';
                    updatePreview();
                } else {
                    jojoPreviewSection.style.display = 'none';
                }
            });
        });

        // Real-time USDT → JOJO conversion
        function updatePreview() {
            const selectedMethod = document.querySelector('input[name="method"]:checked').value;
            if (selectedMethod === 'jojo_token') {
                const usdt = parseFloat(amountInput.value) || 0;
                const net = usdt * 0.9 / price; // after 10% fee
                jojoPreview.textContent = isFinite(net) ? net.toFixed(2) + ' JOJO' : '—';
            }
        }

        amountInput.addEventListener('input', updatePreview);

        // Reveal cards on scroll
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
    </script>
</body>
</html>