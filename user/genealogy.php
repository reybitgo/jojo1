<?php
// user/genealogy.php - ApexTree-powered genealogy visualization
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user    = getUserById($user_id);

/* ------------------------------------------------------------------
   Build the real downline tree for the logged-in user
------------------------------------------------------------------ */
function buildDownline($pdo, $ancestor, $maxDepth = 5, $currentDepth = 1, &$downline = [])
{
    if ($currentDepth > $maxDepth) return;

    $stmt = $pdo->prepare("SELECT id, username, email, created_at, sponsor_id
                           FROM users
                           WHERE sponsor_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ancestor]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $currentDepth;

        // active package?
        $pkgStmt = $pdo->prepare("SELECT id FROM user_packages WHERE user_id = ? AND status = 'active' LIMIT 1");
        $pkgStmt->execute([$row['id']]);
        $row['has_active_package'] = (bool) $pkgStmt->fetchColumn();

        // bonus amount
        $row['bonus_amount'] = 0;
        if ($row['has_active_package']) {
            $percentageStmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = ?");
            $percentageStmt->execute(["referral_level_{$currentDepth}_percentage"]);
            $percentage = $percentageStmt->fetchColumn();
            if (!$percentage) {
                $percentage = ($currentDepth === 1 ? 10 : ($currentDepth === 2 ? 5 : 1));
            }

            $packageStmt = $pdo->prepare("SELECT p.price FROM user_packages up
                                         JOIN packages p ON up.package_id = p.id
                                         WHERE up.user_id = ? AND up.status = 'active' LIMIT 1");
            $packageStmt->execute([$row['id']]);
            $packageValue = $packageStmt->fetchColumn() ?: 0;

            $row['bonus_amount'] = ($packageValue * $percentage) / 100;
        }

        $downline[] = $row;
        buildDownline($pdo, $row['id'], $maxDepth, $currentDepth + 1, $downline);
    }
    return $downline;
}

// Convert flat list to nested tree
function buildHierarchicalTree($downline, $rootUserId, $rootUser)
{
    $userMap = [];
    foreach ($downline as $person) $userMap[$person['id']] = $person;

    $userMap[$rootUserId] = [
        'id'   => $rootUserId,
        'username' => $rootUser['username'],
        'email'    => $rootUser['email'],
        'created_at'=> $rootUser['created_at'] ?? date('Y-m-d H:i:s'),
        'level'     => 0,
        'sponsor_id'=> null,
        'has_active_package' => true,
        'bonus_amount' => 0
    ];

    $build = function ($parentId) use (&$userMap, &$build) {
        $parent = $userMap[$parentId];
        $level  = $parent['level'];

        return [
            'id'      => 'user_'.$parentId,
            'data'    => [
                'name'  => $parent['username'],
                'level' => $level,
                'joined'=> $parent['created_at'],
                'hasPackage'=> $parent['has_active_package'],
                'bonusAmount'=> $parent['bonus_amount'] ?? 0,
                'isRoot'=> $level == 0
            ],
            'options' => [
                'nodeBGColor'=> $level == 0 ? '#ff6b6b' : ($level == 1 ? '#4ecdc4' : ($level == 2 ? '#45b7d1' : ($level == 3 ? '#96ceb4' : ($level == 4 ? '#feca57' : '#ff9ff3')))),
                'nodeBGColorHover'=> $level == 0 ? '#ff5252' : ($level == 1 ? '#3db8b0' : ($level == 2 ? '#3498b1' : ($level == 3 ? '#7bb89a' : ($level == 4 ? '#feb83f' : '#ff85e6'))))
            ],
            'children'=> array_values(array_map($build, array_column(array_filter($userMap, fn($u)=>$u['sponsor_id']==$parentId),'id')))
        ];
    };

    return $build($rootUserId);
}

// ---------- fetch data ----------
try {
    $pdo         = getConnection();
    $fullDownline= buildDownline($pdo, $user_id);
    $treeData    = buildHierarchicalTree($fullDownline, $user_id, $user);

    $totalReferrals = count($fullDownline);
    // Fix for the max() error - check if array is empty first
    $levels = array_column($fullDownline, 'level');
    $maxDepth = !empty($levels) ? max($levels) : 0;
    $totalEarnings = array_sum(array_column($fullDownline, 'bonus_amount'));
    
    $levelCounts = array_fill(1, 5, 0);
    foreach ($fullDownline as $p) {
        if ($p['level'] <= 5) {
            $levelCounts[$p['level']]++;
        }
    }
} catch (Exception $e) {
    error_log("Genealogy error: ".$e->getMessage());
    $treeData = [
        'id' => 'user_'.$user_id,
        'data' => [
            'name' => $user['username'],
            'level' => 0,
            'joined' => $user['created_at'],
            'isRoot' => true,
            'hasPackage' => true,
            'bonusAmount' => 0
        ],
        'options' => [
            'nodeBGColor' => '#ff6b6b',
            'nodeBGColorHover' => '#ff5252'
        ],
        'children' => []
    ];
    $totalReferrals = $maxDepth = $totalEarnings = 0;
    $levelCounts = array_fill(1, 5, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Genealogy Tree - <?= SITE_NAME ?></title>
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
        .stats-card,.tree-card{background:#fff;border:none;border-radius:1rem;box-shadow:0 4px 20px rgba(0,0,0,.08);transition:.4s;transform:translateY(20px);opacity:0;}
        .stats-card.card-visible,.tree-card.card-visible{transform:translateY(0);opacity:1;}
        .stats-card:hover,.tree-card:hover{transform:translateY(-12px) rotateX(5deg);box-shadow:0 20px 40px rgba(0,0,0,.15);}
        .card-header{background:linear-gradient(135deg,#f8f9fa,#fff);border-bottom:1px solid rgba(0,0,0,.05);padding:1.5rem 1rem;border-radius:1rem 1rem 0 0;}
        .stats-card .card-body{padding:1rem;text-align:center;}
        .stats-card h4{font-size:clamp(1.25rem,4vw,1.5rem);font-weight:700;color:var(--primary);}
        #genealogy-container{position:relative;background:#f8f9fa;border-radius:8px;padding:10px;margin:0 auto;width:100%;height:clamp(400px,70vh,800px);overflow:auto;-webkit-overflow-scrolling:touch;}
        .tree-legend{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;justify-content:center;}
        .legend-item{display:flex;align-items:center;gap:5px;padding:5px 10px;background:rgba(255,255,255,.9);border-radius:20px;font-size:clamp(10px,2.5vw,12px);font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,.1);}
        .legend-circle{width:clamp(10px,3vw,12px);height:clamp(10px,3vw,12px);border-radius:50%;}
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
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white active"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
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
                <li><a href="referrals.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Referral Bonus</a></li>
                <li><a href="genealogy.php" class="nav-link text-white active"><i class="fas fa-sitemap me-2"></i>Genealogy</a></li>
                <li><a href="profile.php" class="nav-link text-white"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </nav>

<main class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 pt-3"><h1 class="h3 fw-bold">Referral Genealogy Tree</h1></div>

        <!-- Stats -->
        <div class="row mb-4 g-3">
            <div class="col-lg-3 col-md-6 col-6"><div class="card stats-card"><div class="card-header"><h5><i class="fas fa-users me-2"></i>Total Referrals</h5></div><div class="card-body"><h4><?= $totalReferrals ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 col-6"><div class="card stats-card"><div class="card-header"><h5><i class="fas fa-layer-group me-2"></i>Max Depth</h5></div><div class="card-body"><h4><?= $maxDepth ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 col-6"><div class="card stats-card"><div class="card-header"><h5><i class="fas fa-dollar-sign me-2"></i>Total Earnings</h5></div><div class="card-body"><h4><?= function_exists('formatCurrency')?formatCurrency($totalEarnings):'$'.number_format($totalEarnings,2) ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 col-6"><div class="card stats-card"><div class="card-header"><h5><i class="fas fa-crown me-2"></i>Root Node</h5></div><div class="card-body"><h4>YOU</h4></div></div></div>
        </div>

        <!-- Tree -->
        <div class="row">
            <div class="col-12">
                <div class="card tree-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-sitemap me-2"></i>Your Referral Network</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
                    </div>
                    <div class="card-body">
                        <div class="tree-legend">
                            <div class="legend-item"><div class="legend-circle" style="background:#ff6b6b"></div><span>You (Root)</span></div>
                            <?php if($totalReferrals>0): ?>
                            <div class="legend-item"><div class="legend-circle" style="background:#4ecdc4"></div><span>Level 1</span></div>
                            <div class="legend-item"><div class="legend-circle" style="background:#45b7d1"></div><span>Level 2</span></div>
                            <div class="legend-item"><div class="legend-circle" style="background:#96ceb4"></div><span>Level 3</span></div>
                            <div class="legend-item"><div class="legend-circle" style="background:#feca57"></div><span>Level 4</span></div>
                            <div class="legend-item"><div class="legend-circle" style="background:#ff9ff3"></div><span>Level 5+</span></div>
                            <?php endif; ?>
                        </div>
                        <div id="genealogy-container">
                            <div id="genealogy-tree"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/apextree.min.js"></script>
<script>
/* ----------  js  ---------- */
const treeData = <?= json_encode($treeData) ?>;
const options  = {
    contentKey: 'data',
    direction: 'top',
    enableDrag: true,
    enableZoom: true,
    enableExpandCollapse: true,
    enableToolbar: true,          // native controls
    zoomFactor: .1,
    minScale: .5,
    maxScale: 2,
    nodeWidth: Math.max(100, Math.min(120, window.innerWidth * .30)),
    nodeHeight: 80,
    nodeTemplate: c => `
        <div style="display:flex;flex-direction:column;justify-content:center;align-items:center;height:100%">
            <b style="font-size:clamp(10px,2.5vw,12px)">${c.name}</b>
            ${c.bonusAmount&&!c.isRoot?`<span style="color:#ffd700;font-size:clamp(8px,2.2vw,10px)">$${c.bonusAmount.toFixed(2)}</span>`:''}
        </div>`,
    canvasStyle: 'border:1px solid #e0e0e0;background:#f6f6f6;border-radius:8px'
};

let tree;
const initTree = () => {
    const c = document.getElementById('genealogy-container');
    options.width  = Math.max(800, c.clientWidth);
    options.height = Math.min(800, Math.max(400, window.innerHeight * .70));
    tree?.destroy();
    tree = new ApexTree(document.getElementById('genealogy-tree'), options);
    tree.render(treeData);
    setTimeout(()=>tree.center(),50);   // centre in viewport
};

window.addEventListener('resize', ()=>{ clearTimeout(window.t); window.t=setTimeout(initTree,150); });
document.addEventListener('DOMContentLoaded', ()=> {
    initTree();
    document.querySelectorAll('.stats-card,.tree-card').forEach(card=>{
        new IntersectionObserver(entries=>{
            if(entries[0].isIntersecting) card.classList.add('card-visible');
        },{threshold:.2}).observe(card);
    });
});
</script>
</body>
</html>