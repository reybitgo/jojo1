<?php
// admin/users.php – add sponsor name right after username column
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$users       = [];
$total_pages = 1;
$total_users = 0;
$error       = '';
$search      = trim($_GET['search'] ?? '');

$page       = max(1, intval($_GET['page'] ?? 1));
$per_page   = 20;
$offset     = ($page - 1) * $per_page;

$where_conditions = ["u.role = 'user'"];
$params           = [];

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[]           = "%$search%";
    $params[]           = "%$search%";
}
$where_clause = implode(" AND ", $where_conditions);

try {
    $pdo = getConnection();

    // count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_clause");
    $stmt->execute($params);
    $total_users = (int)$stmt->fetchColumn();

    // fetch users + sponsor
    $users_sql = "
        SELECT u.*,
               COALESCE(SUM(CASE WHEN up.status = 'active' THEN p.price ELSE 0 END), 0) AS total_spent,
               COUNT(DISTINCT CASE WHEN up.status = 'active' THEN up.id END) AS active_packages,
               s.username AS sponsor_name
        FROM users u
        LEFT JOIN user_packages up ON u.id = up.user_id AND up.status = 'active'
        LEFT JOIN packages p       ON up.package_id = p.id
        LEFT JOIN users s          ON u.sponsor_id  = s.id
        WHERE $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($users_sql);
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $users = $stmt->fetchAll();

    $total_pages = max(1, ceil($total_users / $per_page));

} catch (Exception $e) {
    $error = "Failed to load users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - <?= SITE_NAME ?></title>
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
        style="top: 1rem; left: 1rem; z-index: 1050;"
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
      <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>withdrawals</a></li>
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
          <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>withdrawals</a></li>
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
            <h1 class="h3 fw-bold">Manage Users</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search"
                               placeholder="Search by username or email" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($search): ?>
                            <a href="users.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="text-muted"><?= number_format($total_users) ?> users found</span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($users) && !$error): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No users found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Sponsor</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Total Spent</th>
                                    <th>Packages</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['sponsor_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($user['status']) ?></span></td>
                                        <td><?= number_format($user['total_spent'], 2) ?> USDT</td>
                                        <td><?= $user['active_packages'] ?></td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <a href="user_details.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                            <form method="POST" action="update_user_status.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                                                <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-toggle-<?= $user['status'] === 'active' ? 'on' : 'off' ?>"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <nav aria-label="Users pagination">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?<?= http_build_query(['page' => $page - 1, 'search' => $search]) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="users.php?<?= http_build_query(['page' => $i, 'search' => $search]) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?<?= http_build_query(['page' => $page + 1, 'search' => $search]) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <div class="text-center text-muted">
                                Page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_users) ?> users)
                            </div>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* reveal cards on scroll – identical to reference */
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