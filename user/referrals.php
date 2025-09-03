<?php
// user/referrals.php – Referral viewer + live referral link
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id      = getCurrentUserId();
$user         = getUserById($user_id);
$level_filter = $_GET['level'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, intval($_GET['page'] ?? 1));
$per_page     = 20;

$valid_levels = ['all', 1, 2, 3, 4, 5];
if (!in_array($level_filter, $valid_levels)) $level_filter = 'all';

/* ----------  BUILD DOWNLINE  ---------- */
function buildDownline($pdo, $ancestor, $maxDepth = 5, $currentDepth = 1, &$downline = [])
{
    if ($currentDepth > $maxDepth) return;
    $stmt = $pdo->prepare("SELECT id, username, email, created_at
                           FROM users
                           WHERE sponsor_id = ?");
    $stmt->execute([$ancestor]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $currentDepth;
        $pkgStmt      = $pdo->prepare("SELECT 1 FROM user_packages WHERE user_id = ? AND status = 'active' LIMIT 1");
        $pkgStmt->execute([$row['id']]);
        $row['has_active_package'] = (bool) $pkgStmt->fetchColumn();
        $downline[] = $row;
        buildDownline($pdo, $row['id'], $maxDepth, $currentDepth + 1, $downline);
    }
    return $downline;
}

try {
    $pdo = getConnection();

    // Counts per status for badges
    $status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach (['pending', 'approved', 'rejected'] as $s) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM refill_requests WHERE status = ?");
        $stmt->execute([$s]);
        $status_counts[$s] = $stmt->fetchColumn();
    }

    /* Build full downline */
    $fullDownline = buildDownline($pdo, $user_id);
    $level_counts = array_fill(1, 5, 0);
    foreach ($fullDownline as $d) if ($d['level'] <= 5) $level_counts[$d['level']]++;

    /* Apply filters */
    $filtered = array_filter($fullDownline, function ($d) use ($level_filter, $search) {
        $levelOk = $level_filter === 'all' || $d['level'] == $level_filter;
        $searchOk = $search === '' || stripos($d['username'], $search) !== false || stripos($d['email'], $search) !== false;
        return $levelOk && $searchOk;
    });

    $total_referrals = count($filtered);
    $total_pages     = max(1, ceil($total_referrals / $per_page));
    $offset          = ($page - 1) * $per_page;
    $referralsPage   = array_slice($filtered, $offset, $per_page);

    /* ----------  REAL BONUS AMOUNTS  ---------- */
    if ($referralsPage) {
        // collect all down-line ids on the current page
        $ids = array_column($referralsPage, 'id');
    
        // build safe placeholders
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
        // fetch the actual referral bonuses already credited to the sponsor
        $stmt = $pdo->prepare(
            "SELECT reference_id, SUM(amount) AS total
             FROM   ewallet_transactions
             WHERE  user_id = ?
               AND  type    = 'referral'
               AND  reference_id  IN ($placeholders)
             GROUP  BY reference_id"
        );
        // bind: 1st = sponsor (logged-in user), then list of down-lines
        $stmt->execute(array_merge([$user_id], $ids));
        $bonusMap = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'reference_id');
    } else {
        $bonusMap = [];
    }
    
    /* attach the amounts */
    foreach ($referralsPage as &$ref) {
        $ref['bonus_amount'] = $bonusMap[$ref['id']] ?? 0;
    }
    unset($ref);

} catch (Exception $e) {
    $referralsPage = [];
    $total_referrals = 0;
    $total_pages = 1;
    $level_counts = array_fill(1, 5, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Referrals - <?= SITE_NAME ?></title>
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
        .referral-card, .stats-card, .search-card, .table-card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
            transition: .4s;
            transform: translateY(20px);
            opacity: 0;
        }
        .referral-card.card-visible, .stats-card.card-visible, .search-card.card-visible, .table-card.card-visible {
            transform: translateY(0);
            opacity: 1;
        }
        .referral-card:hover, .stats-card:hover, .search-card:hover, .table-card:hover {
            transform: translateY(-12px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(0,0,0,.15);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #fff);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.5rem 1rem;
            border-radius: 1rem 1rem 0 0;
        }
        .referral-link {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 600;
        }
        .stats-card .card-body {
            padding: 1rem;
        }
        .stats-card h5 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .stats-card h4 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .nav-tabs .nav-link {
            color: #667eea;
            border-radius: .5rem;
        }
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: #fff;
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
        .avatar-circle {
            width: 32px;
            height: 32px;
            font-size: 14px;
            color: white;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-copy {
            transition: transform .3s;
        }
        .btn-copy:hover {
            transform: translateY(-3px);
        }
        .dashboard-logo{width:calc(100% - 2rem);max-width:100%;margin:0 auto;display:block;}
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
                <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white active"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
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
                <li><a href="ewallet.php" class="nav-link text-white"><i class="fas fa-wallet me-2"></i>E-Wallet</a></li>
                <li><a href="referrals.php" class="nav-link text-white active"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
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
                <h1 class="h3 fw-bold">My Referrals by Level</h1>
            </div>

            <!-- Referral Link -->
            <div class="card referral-card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-link me-2"></i>Your Referral Link</h5>
                </div>
                <div class="card-body">
                    <div class="bg-light p-2 rounded d-flex align-items-center">
                        <code id="refCode" class="referral-link flex-grow-1">
                            https://btc3.site/register.php?ref=<?= urlencode($user['username']) ?>
                        </code>
                        <button class="btn btn-sm btn-outline-primary btn-copy ms-2"
                                onclick="copyToClipboard('https://btc3.site/register.php?ref=<?= urlencode($user['username']) ?>')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mb-4">
                <?php foreach (range(1, 5) as $l): ?>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stats-card text-center bg-gradient-<?= ['primary','success','info','warning','danger'][$l-1] ?> text-white">
                            <div class="card-body">
                                <h5 class="card-title">Level <?= $l ?></h5>
                                <h4 class="mb-1"><?= $level_counts[$l] ?? 0 ?></h4>
                                <small class="opacity-75"><?= [10,1,1,1,1][$l-1] ?>% Bonus</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="card stats-card text-center bg-dark text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total</h5>
                            <h4 class="mb-1"><?= array_sum($level_counts) ?></h4>
                            <small class="opacity-75">All Levels</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $level_filter == 'all' ? 'active' : '' ?>"
                       href="referrals.php?level=all&search=<?= urlencode($search) ?>">
                        All Levels <span class="badge bg-secondary ms-1"><?= array_sum($level_counts) ?></span>
                    </a>
                </li>
                <?php foreach (range(1, 5) as $l): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $level_filter == $l ? 'active' : '' ?>"
                           href="referrals.php?level=<?= $l ?>&search=<?= urlencode($search) ?>">
                            Level <?= $l ?> <span class="badge bg-secondary ms-1"><?= $level_counts[$l] ?? 0 ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Search -->
            <div class="card search-card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($level_filter) ?>">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search"
                                   placeholder="Search by username or email" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <?php if ($search): ?>
                                <a href="referrals.php?level=<?= $level_filter ?>" class="btn btn-outline-secondary ms-2">Clear</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="text-muted"><?= $total_referrals ?> referral<?= $total_referrals != 1 ? 's' : '' ?> found</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Zero-referrals replacement -->
            <?php if (empty($referralsPage)): ?>
                <div class="card table-card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-link fa-3x text-primary mb-3"></i>
                        <h5 class="text-primary">No Referrals Yet</h5>
                        <p class="text-muted mb-3">Start inviting with your link above!</p>
                        <a href="packages.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart me-1"></i> Mine a Package
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Table -->
                <div class="card table-card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Referral List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Level</th>
                                    <th>Bonus Amount</th>
                                    <th>Package Status</th>
                                    <th>Joined</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($referralsPage as $index => $ref): ?>
                                    <tr>
                                        <td><?= ($page - 1) * $per_page + $index + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?= strtoupper(substr($ref['username'], 0, 1)) ?>
                                                </div>
                                                <span class="fw-medium"><?= htmlspecialchars($ref['username']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($ref['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= ['primary','success','info','warning','danger'][$ref['level']-1] ?>">
                                                Level <?= $ref['level'] ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold <?= $ref['bonus_amount'] > 0 ? 'text-success' : 'text-muted' ?>">
                                            <?= formatCurrency($ref['bonus_amount']) ?>
                                        </td>
                                        <td>
                                            <?php if ($ref['has_active_package']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= date('M j, Y', strtotime($ref['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Referrals pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                               href="referrals.php?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                               href="?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                               href="referrals.php?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Referral link copied to clipboard!');
            });
        }

        /* reveal cards on scroll */
        const cards = document.querySelectorAll('.referral-card, .stats-card, .search-card, .table-card');
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