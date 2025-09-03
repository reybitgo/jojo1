<?php
// user/packages.php  –  future-proof mining-rig image support
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Manila');
requireLogin('../login.php');

$user = getUserById(getCurrentUserId());

/* ------------------------------------------------------------------
   1️⃣  Find the mode of any package the user has already purchased
------------------------------------------------------------------ */
$pdo = getConnection();
$existingMode = null;

$stmt = $pdo->prepare("
    SELECT p.mode
    FROM user_packages up
    JOIN packages p ON p.id = up.package_id
    WHERE up.user_id = ?
    ORDER BY up.purchase_date DESC
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$existingMode = $stmt->fetchColumn();   // false | 'daily' | 'monthly'

/* ------------------------------------------------------------------
   2️⃣  Build the package list
------------------------------------------------------------------ */
$where = "WHERE p.status = 'active'";
$params = [];

if ($existingMode === 'daily') {
    $where .= " AND p.mode = 'daily'";
} elseif ($existingMode === 'monthly') {
    $where .= " AND p.mode = 'monthly'";
}

$stmt = $pdo->prepare("SELECT * FROM packages p $where ORDER BY p.price ASC");
$stmt->execute($params);
$packages = $stmt->fetchAll();

/* ---------- helpers ---------- */
function getMiningRigSpecs($name, $price) {
    $hr = max(50, floor($price / 100) * 15);
    $pw = $hr * 30 + rand(200, 500);
    return [
        'hashrate'   => $hr . ' TH/s',
        'power'      => number_format($pw) . 'W',
        'efficiency' => number_format($pw / $hr, 1) . ' J/TH',
        'cooling'    => 'Dual Fan Air Cooling',
        'chipset'    => 'ASIC SHA-256'
    ];
}

function getPackageImage(string $packageName): string
{
    $safe = strtolower(preg_replace('/[^a-z0-9]/', '_', $packageName));
    $specific = "../assets/images/miners/{$safe}.png";
    return file_exists($specific) ? $specific : "../assets/images/miner.png";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mining Packages - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        :root{--primary:#667eea;--primary-dark:#1e3c72;}
        body{background:#f5f7fa;font-family:'Segoe UI',sans-serif;}
        .sidebar-desktop{display:none;}
        @media(min-width:992px){.sidebar-desktop{display:block;width:250px;height:100vh;position:fixed;top:0;left:0;background:var(--primary-dark);color:#fff;padding-top:1rem;z-index:1000;}.main-content{margin-left:250px;}}
        @media(max-width:991px){body>.main-content{padding-top:1rem!important;margin-top:1rem!important;}}
        .nav-link.active{background:var(--primary)!important;color:#fff!important;border-radius:.25rem;}
        /* card & image */
        .mining-package-card{background:#fff;border:none;border-radius:1rem;box-shadow:0 4px 20px rgba(0,0,0,.08);transition:.4s;transform:translateY(20px);opacity:0;}
        .mining-package-card.rig-visible{transform:translateY(0);opacity:1;}
        .mining-package-card:hover{transform:translateY(-12px) rotateX(5deg);box-shadow:0 20px 40px rgba(0,0,0,.15);}
        .mining-rig-header{background:linear-gradient(135deg,#f8f9fa,#fff);border-bottom:1px solid rgba(0,0,0,.05);padding:1.5rem 1rem;border-radius:1rem 1rem 0 0;text-align:center;}
        .mining-rig-hardware{width:120px;height:90px;object-fit:contain;filter:drop-shadow(0 6px 20px rgba(0,0,0,.15));border-radius:8px;background:#fff;padding:8px;}
        .mining-package-name{
            background:linear-gradient(45deg,#667eea,#764ba2);
            -webkit-background-clip:text;
            background-clip:text;
            -webkit-text-fill-color:transparent;
            font-weight:600;
            margin:.5rem 0;
            font-size:1.25rem;
        }
        .mining-package-price{font-size:1.75rem;font-weight:700;color:#667eea;}
        .hardware-spec-badges{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:.5rem;}
        .spec-badge{color:#fff;padding:4px 8px;border-radius:12px;font-size:.75rem;font-weight:500;background:linear-gradient(45deg,#667eea,#764ba2);}
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

<main class="main-content"><div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center pt-3 mb-4"><h1 class="h3 fw-bold">Mining Packages</h1></div>

    <?php if (!empty($existingMode)): ?>
        <div class="alert alert-info mb-4">
            You already own a <strong><?= ucfirst($existingMode) ?></strong> package, therefore only <?= $existingMode ?> packages are shown.
        </div>
    <?php endif; ?>

    <?php if (empty($packages)): ?>
        <div class="text-center py-5">
            <i class="fas fa-box fa-3x text-muted mb-3"></i>
            <p class="text-muted">No packages are currently available.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($packages as $package): ?>
                <?php $specs = getMiningRigSpecs($package['name'], $package['price']); ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 mining-package-card">
                        <!-- Card Header -->
                        <div class="card-header mining-rig-header">
                            <!-- Mode Label -->
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge rounded-pill <?= $package['mode'] === 'daily' ? 'bg-warning text-dark' : 'bg-info text-white' ?>">
                                    <?= strtoupper($package['mode']) ?>
                                </span>
                            </div>
                            <img src="<?= getPackageImage($package['name']) ?>" alt="<?= htmlspecialchars($package['name']) ?> Mining Rig" class="mining-rig-hardware">
                            <h4 class="mining-package-name"><?= htmlspecialchars($package['name']) ?></h4>
                            <div class="mining-package-price"><?= formatCurrency($package['price']) ?></div>
                            <div class="hardware-spec-badges">
                                <span class="spec-badge"><?= $specs['hashrate'] ?></span>
                                <span class="spec-badge"><?= $specs['power'] ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($package['description'])): ?>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($package['description'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($package['features'])): ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach (explode("\n", trim($package['features'])) as $line): ?>
                                        <?php if (trim($line)): ?>
                                            <li><i class="fas fa-check text-success me-2"></i><?= htmlspecialchars(trim($line)) ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <form method="POST" action="checkout.php" class="d-grid">
                                <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <?php
                                $isInactive = ($existingMode === 'daily' && $user['status'] === 'inactive');
                                ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-pickaxe me-2"></i>Add Mine
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* reveal cards on scroll */
const cards = document.querySelectorAll('.mining-package-card');
const obs = new IntersectionObserver(entries =>
    entries.forEach((e, i) =>
        e.isIntersecting && setTimeout(() => e.target.classList.add('rig-visible'), i * 150)
    ), { threshold: 0.2 });
cards.forEach(c => obs.observe(c));

/* inactive → reactivate flow */
<?php if ($existingMode === 'daily' && $user['status'] === 'inactive'): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[action="checkout.php"]').forEach(form => {
        form.addEventListener('submit', e => {
            // allow normal checkout; server-side will flip status after success
        });
    });
});
<?php else: ?>
// keep original inactive-blocker
document.querySelectorAll('form[action="checkout.php"]').forEach(form => {
    form.addEventListener('submit', e => {
        <?php if ($user['status'] !== 'active'): ?>
            e.preventDefault();
            alert('Your account is inactive. Please contact support.');
        <?php endif; ?>
    });
});
<?php endif; ?>
</script>
</body>
</html>