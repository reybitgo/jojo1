<?php
// admin/packages.php - Complete with fixed edit modal
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// 1. Handle status parameter
$current_status = $_GET['status'] ?? 'active';
$valid_statuses = ['active', 'inactive'];
$current_status = in_array($current_status, $valid_statuses) ? $current_status : 'active';

// 2. Handle package updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('packages.php', 'Invalid security token.', 'error');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getConnection();
        
        if ($action === 'update') {
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $status = $_POST['status'];
            $description = trim($_POST['description'] ?? '');
            $features = trim($_POST['features'] ?? '');
            $mode = $_POST['mode'] ?? 'monthly';
            $daily_percentage = isset($_POST['daily_percentage']) ? floatval($_POST['daily_percentage']) : 0.00;
            $target_value = isset($_POST['target_value'])
                ? floatval($_POST['target_value'])
                : 0.00;
            $maturity = !empty($_POST['maturity_period']) ? (int)$_POST['maturity_period'] : BONUS_DAYS;

            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, price = ?, status = ?, description = ?, features = ?, mode = ?, daily_percentage = ?, target_value = ?, maturity_period = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $status, $description, $features, $mode, $daily_percentage, $target_value, $maturity, $package_id]);
            
            redirectWithMessage('packages.php', 'Package updated successfully.', 'success');
            
        } elseif ($action === 'add') {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $description = trim($_POST['description'] ?? '');
            $features = trim($_POST['features'] ?? '');
            $mode = $_POST['mode'] ?? 'monthly';
            $daily_percentage = isset($_POST['daily_percentage']) ? floatval($_POST['daily_percentage']) : 0.00;
            $target_value = isset($_POST['target_value'])
                ? floatval($_POST['target_value'])
                : 0.00;
            $maturity = !empty($_POST['maturity_period']) ? (int)$_POST['maturity_period'] : BONUS_DAYS;

            $stmt = $pdo->prepare("
                INSERT INTO packages
                (name, price, status, description, features, mode, daily_percentage, target_value, maturity_period)
                VALUES (?, ?, 'active', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $description, $features, $mode, $daily_percentage, $target_value, $maturity]);
            
            redirectWithMessage('packages.php', 'Package added successfully.', 'success');
            
        } elseif ($action === 'toggle') {
            $package_id = intval($_POST['package_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $pdo->prepare("UPDATE packages SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $package_id]);
            
            redirectWithMessage('packages.php', 'Package status updated.', 'success');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['package_id'] ?? 0);
            if (!$id) redirectWithMessage('packages.php', 'Invalid package.', 'error');
        
            try {
                $pdo->beginTransaction();
                // Ensure no purchases exist
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_packages WHERE package_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    redirectWithMessage('packages.php', 'Cannot delete – package has purchases.', 'error');
                }
                $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
                $pdo->commit();
                redirectWithMessage('packages.php', 'Package deleted.', 'success');
            } catch (Exception $e) {
                redirectWithMessage('packages.php', 'Delete failed.', 'error');
            }
        }
        
    } catch (Exception $e) {
        redirectWithMessage('packages.php', 'Error processing request.', 'error');
    }
}

// Build list of package IDs that have been purchased
$purchased = [];
try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT DISTINCT package_id FROM user_packages");
    $purchased = array_column($stmt->fetchAll(), 'package_id');
} catch (Exception $e) {}

// 3. Get packages with status filter
$where_clause = $current_status !== 'all' ? "WHERE status = ?" : "";
$params = $current_status !== 'all' ? [$current_status] : [];

$status_counts = ['active' => 0, 'inactive' => 0];
$error = '';
$packages = [];

try {
    foreach (['active', 'inactive'] as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE status = ?");
        $stmt->execute([$status]);
        $status_counts[$status] = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT * FROM packages 
        $where_clause
        ORDER BY price ASC
    ");
    $stmt->execute($params);
    $packages = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Failed to load packages: " . $e->getMessage();
    $packages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Packages - <?= SITE_NAME ?></title>
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

    /* ------ SIDEBAR RESPONSIVENESS ------ */
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

    /* ------ CARD ANIMATIONS ------ */
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

    /* Modal improvements */
    .modal-lg {
      max-width: 800px;
    }
    .modal-dialog-scrollable .modal-body {
      overflow-y: auto;
    }
    .modal-header {
      border-bottom: 2px solid #e9ecef;
      background-color: #f8f9fa;
    }
    .modal-footer {
      border-top: 2px solid #e9ecef;
      background-color: #f8f9fa;
    }
    .form-label {
      font-weight: 600;
      color: #495057;
    }
    .text-danger {
      color: #dc3545 !important;
    }
    .modal {
      z-index: 1055;
    }
    .modal-backdrop {
      z-index: 1050;
    }
    .mb-3:last-child {
      margin-bottom: 0 !important;
    }
    textarea.form-control {
      resize: vertical;
      min-height: 80px;
    }
    .modal-footer .btn + .btn {
      margin-left: 0.5rem;
    }
    .card-header h5 { margin: 0; }
    .input-group-text { min-width: 46px; }
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
      <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
      <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>Withdrawals</a></li>
      <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
      <li><a href="settings.php" class="nav-link text-white"><i class="fas fa-cog me-2"></i>Settings</a></li>
      <li><a href="packages.php" class="nav-link text-white active"><i class="fas fa-box me-2"></i>Packages</a></li>
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
          <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
          <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>Withdrawals</a></li>
          <li><a href="refills.php" class="nav-link text-white"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
          <li><a href="settings.php" class="nav-link text-white"><i class="fas fa-cog me-2"></i>Settings</a></li>
          <li><a href="packages.php" class="nav-link text-white active"><i class="fas fa-box me-2"></i>Packages</a></li>
          <li><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
      </ul>
  </div>
</nav>

<main class="main-content">
  <div class="container-fluid p-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center pt-3 mb-4">
      <h1 class="h3 fw-bold">Manage Packages</h1>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
        <i class="fas fa-plus"></i> Add New Package
      </button>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
          <a class="nav-link <?= $current_status === 'active' ? 'active' : '' ?>" 
             href="packages.php?status=active">
              Active
              <?php if ($status_counts['active'] > 0): ?>
                  <span class="badge bg-success ms-1"><?= $status_counts['active'] ?></span>
              <?php endif; ?>
          </a>
      </li>
      <li class="nav-item">
          <a class="nav-link <?= $current_status === 'inactive' ? 'active' : '' ?>" 
             href="packages.php?status=inactive">
              Inactive
              <?php if ($status_counts['inactive'] > 0): ?>
                  <span class="badge bg-secondary ms-1"><?= $status_counts['inactive'] ?></span>
              <?php endif; ?>
          </a>
      </li>
    </ul>

    <!-- Packages Table -->
    <div class="card">
      <div class="card-body">
          <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php elseif (empty($packages)): ?>
              <div class="text-center py-4">
                  <i class="fas fa-box fa-3x text-muted mb-3"></i>
                  <p class="text-muted">
                      <?php if ($current_status !== 'all'): ?>
                          No <?= $current_status ?> packages found
                      <?php else: ?>
                          No packages found
                      <?php endif; ?>
                  </p>
              </div>
          <?php else: ?>
              <div class="table-responsive">
                  <table class="table table-hover">
                      <thead>
                          <tr>
                              <th>ID</th>
                              <th>Name</th>
                              <th>Price</th>
                              <th>Status</th>
                              <th>Mode</th>
                              <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($packages as $package): ?>
                          <tr>
                              <td><?= $package['id'] ?></td>
                              <td><?= htmlspecialchars($package['name']) ?></td>
                              <td><?= formatCurrency($package['price']) ?></td>
                              <td>
                                  <span class="badge bg-<?= $package['status'] === 'active' ? 'success' : 'secondary' ?>">
                                      <?= ucfirst($package['status']) ?>
                                  </span>
                              </td>
                              <td>
                                  <span class="badge bg-<?= $package['mode'] === 'daily' ? 'warning' : 'info' ?>">
                                      <?= ucfirst($package['mode']) ?>
                                  </span>
                              </td>
                              <td>
                                  <div class="btn-group btn-group-sm">
                                      <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                              data-bs-target="#editModal<?= $package['id'] ?>">
                                          <i class="fas fa-edit"></i>
                                      </button>
                                      <form method="POST" action="packages.php" class="d-inline">
                                          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                          <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                          <input type="hidden" name="action" value="toggle">
                                          <input type="hidden" name="new_status" value="<?= $package['status'] === 'active' ? 'inactive' : 'active' ?>">
                                          <button type="submit" class="btn btn-sm btn-warning">
                                              <i class="fas fa-toggle-<?= $package['status'] === 'active' ? 'on' : 'off' ?>"></i>
                                          </button>
                                      </form>
                                      <?php if (!in_array($package['id'], $purchased)): ?>
                                          <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this package?')">
                                              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                              <input type="hidden" name="action" value="delete">
                                              <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                              <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                  <i class="fas fa-trash"></i>
                                              </button>
                                          </form>
                                      <?php endif; ?>
                                  </div>
                              </td>
                          </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table>
              </div>
          <?php endif; ?>
      </div>
    </div>

    <!-- Edit Modals - Generated for each package -->
    <?php foreach ($packages as $package): ?>
    <div class="modal fade" id="editModal<?= $package['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Package: <?= htmlspecialchars($package['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?= htmlspecialchars($package['name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price (USDT) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="price" 
                                           value="<?= $package['price'] ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= $package['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $package['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Maturity Period (days)</label>
                                    <input type="number" class="form-control" name="maturity_period"
                                           value="<?= htmlspecialchars($package['maturity_period'] ?? BONUS_DAYS) ?>"
                                           min="1" max="9999">
                                    <small class="text-muted">Leave empty for default (<?= BONUS_DAYS ?> days)</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select class="form-select" name="mode" id="modeSelect<?= $package['id'] ?>">
                                <option value="monthly" <?= $package['mode'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="daily" <?= $package['mode'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                            </select>
                        </div>

                        <!-- Daily Mode Fields -->
                        <div id="dailyFields<?= $package['id'] ?>" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Daily Percentage (%)</label>
                                        <input type="number" class="form-control" name="daily_percentage" 
                                               value="<?= htmlspecialchars($package['daily_percentage'] ?? '') ?>" 
                                               min="0" max="100" step="0.01">
                                        <small class="text-muted">Daily return percentage</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Target Value (USDT)</label>
                                        <input type="number" class="form-control" name="target_value"
                                               value="<?= htmlspecialchars($package['target_value'] ?? '') ?>"
                                               min="0" step="0.01">
                                        <small class="text-muted">Target investment value</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Enter package description..."><?= htmlspecialchars($package['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Features</label>
                            <textarea class="form-control" name="features" rows="4" 
                                      placeholder="Enter features (one per line)..."><?= htmlspecialchars($package['features'] ?? '') ?></textarea>
                            <small class="text-muted">Enter each feature on a new line</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price (USDT) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maturity Period (days)</label>
                            <input type="number" class="form-control" name="maturity_period"
                                   placeholder="90" min="1" max="9999" value="<?= BONUS_DAYS ?>">
                            <small class="text-muted">Leave empty to use system default (<?= BONUS_DAYS ?> days)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <select class="form-select" name="mode" id="addModeSelect">
                                <option value="monthly" selected>Monthly</option>
                                <option value="daily">Daily</option>
                            </select>
                        </div>

                        <div id="addDailyFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Daily Percentage (%)</label>
                                        <input type="number" class="form-control" name="daily_percentage" min="0" max="100" step="0.01">
                                        <small class="text-muted">Daily return percentage</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Target Value (USDT)</label>
                                        <input type="number" class="form-control" name="target_value" min="0" step="0.01">
                                        <small class="text-muted">Target investment value</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Enter package description..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Features</label>
                            <textarea class="form-control" name="features" rows="4" 
                                      placeholder="Enter features (one per line)..."></textarea>
                            <small class="text-muted">Enter each feature on a new line</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Package
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

  </div>
</main>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle edit modal field toggling for each package
    <?php if (!empty($packages)): ?>
        <?php foreach ($packages as $package): ?>
            (function() {
                const modeSelect<?= $package['id'] ?> = document.getElementById('modeSelect<?= $package['id'] ?>');
                const dailyFields<?= $package['id'] ?> = document.getElementById('dailyFields<?= $package['id'] ?>');

                if (modeSelect<?= $package['id'] ?> && dailyFields<?= $package['id'] ?>) {
                    function toggleDailyFields<?= $package['id'] ?>() {
                        const isDailyMode = modeSelect<?= $package['id'] ?>.value === 'daily';
                        dailyFields<?= $package['id'] ?>.style.display = isDailyMode ? 'block' : 'none';
                    }
                    
                    modeSelect<?= $package['id'] ?>.addEventListener('change', toggleDailyFields<?= $package['id'] ?>);
                    toggleDailyFields<?= $package['id'] ?>(); // Initialize on load
                }
            })();
        <?php endforeach; ?>
    <?php endif; ?>

    // Handle add modal field toggling
    const addModeSelect = document.getElementById('addModeSelect');
    const addDailyFields = document.getElementById('addDailyFields');

    if (addModeSelect && addDailyFields) {
        function toggleAddDaily() {
            const show = addModeSelect.value === 'daily';
            addDailyFields.style.display = show ? 'block' : 'none';
        }
        addModeSelect.addEventListener('change', toggleAddDaily);
        toggleAddDaily(); // Initialize on load
    }

    // Card reveal animation on scroll
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

    // Form validation for add package
    const addPackageForm = document.querySelector('#addPackageModal form');
    if (addPackageForm) {
        addPackageForm.addEventListener('submit', function(e) {
            const name = this.querySelector('input[name="name"]').value.trim();
            const price = parseFloat(this.querySelector('input[name="price"]').value);
            const mode = this.querySelector('select[name="mode"]').value;
            
            if (!name) {
                e.preventDefault();
                alert('Package name is required.');
                return false;
            }
            
            if (price <= 0) {
                e.preventDefault();
                alert('Package price must be greater than 0.');
                return false;
            }
            
            if (mode === 'daily') {
                const dailyPercentage = parseFloat(this.querySelector('input[name="daily_percentage"]').value || 0);
                const targetValue = parseFloat(this.querySelector('input[name="target_value"]').value || 0);
                
                if (dailyPercentage <= 0) {
                    e.preventDefault();
                    alert('Daily percentage must be greater than 0 for daily packages.');
                    return false;
                }
                
                if (targetValue <= 0) {
                    e.preventDefault();
                    alert('Target value must be greater than 0 for daily packages.');
                    return false;
                }
            }
        });
    }

    // Form validation for edit package
    <?php if (!empty($packages)): ?>
        <?php foreach ($packages as $package): ?>
            (function() {
                const editForm<?= $package['id'] ?> = document.querySelector('#editModal<?= $package['id'] ?> form');
                if (editForm<?= $package['id'] ?>) {
                    editForm<?= $package['id'] ?>.addEventListener('submit', function(e) {
                        const name = this.querySelector('input[name="name"]').value.trim();
                        const price = parseFloat(this.querySelector('input[name="price"]').value);
                        const mode = this.querySelector('select[name="mode"]').value;
                        
                        if (!name) {
                            e.preventDefault();
                            alert('Package name is required.');
                            return false;
                        }
                        
                        if (price <= 0) {
                            e.preventDefault();
                            alert('Package price must be greater than 0.');
                            return false;
                        }
                        
                        if (mode === 'daily') {
                            const dailyPercentage = parseFloat(this.querySelector('input[name="daily_percentage"]').value || 0);
                            const targetValue = parseFloat(this.querySelector('input[name="target_value"]').value || 0);
                            
                            if (dailyPercentage <= 0) {
                                e.preventDefault();
                                alert('Daily percentage must be greater than 0 for daily packages.');
                                return false;
                            }
                            
                            if (targetValue <= 0) {
                                e.preventDefault();
                                alert('Target value must be greater than 0 for daily packages.');
                                return false;
                            }
                        }
                    });
                }
            })();
        <?php endforeach; ?>
    <?php endif; ?>

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
    });

    // Confirmation for status toggle
    const toggleForms = document.querySelectorAll('form[method="POST"] input[name="action"][value="toggle"]');
    toggleForms.forEach(input => {
        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const newStatus = this.querySelector('input[name="new_status"]').value;
                const packageId = this.querySelector('input[name="package_id"]').value;
                
                if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this package?`)) {
                    e.preventDefault();
                }
            });
        }
    });

    // Enhanced table interactions
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });

    // Modal focus management
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input[type="text"], input[type="number"], textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            // Reset form when modal is closed
            const form = this.querySelector('form');
            if (form && !form.querySelector('input[name="package_id"]')) { // Only reset add form
                form.reset();
                // Hide daily fields if add modal
                if (this.id === 'addPackageModal') {
                    const addDailyFields = document.getElementById('addDailyFields');
                    if (addDailyFields) {
                        addDailyFields.style.display = 'none';
                    }
                }
            }
        });
    });

    // Responsive table scroll indicator
    const tableContainer = document.querySelector('.table-responsive');
    if (tableContainer) {
        function checkScroll() {
            const isScrollable = tableContainer.scrollWidth > tableContainer.clientWidth;
            if (isScrollable && !tableContainer.dataset.scrollHintShown) {
                const hint = document.createElement('div');
                hint.className = 'alert alert-info alert-dismissible fade show mt-2';
                hint.innerHTML = `
                    <small><i class="fas fa-arrow-right"></i> Scroll horizontally to see all columns</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                tableContainer.parentNode.insertBefore(hint, tableContainer.nextSibling);
                tableContainer.dataset.scrollHintShown = 'true';
            }
        }
        
        checkScroll();
        window.addEventListener('resize', checkScroll);
    }

    // Loading states for forms
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Re-enable after 10 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 10000);
            }
        });
    });
});

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(amount);
}

// Print functionality (if needed)
function printPackages() {
    const printContent = document.querySelector('.table-responsive').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Packages Report - <?= SITE_NAME ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .btn, .modal, .sidebar-desktop { display: none !important; }
                        .table { font-size: 12px; }
                    }
                </style>
            </head>
            <body>
                <div class="container mt-4">
                    <h2>Packages Report - <?= SITE_NAME ?></h2>
                    <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    ${printContent}
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Export to CSV functionality (if needed)
function exportToCSV() {
    const table = document.querySelector('table');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => {
            let content = cell.textContent.trim();
            // Remove action buttons content
            if (cell.querySelector('.btn-group')) {
                content = '';
            }
            return `"${content.replace(/"/g, '""')}"`;
        }).join(',');
    }).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `packages_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

</body>
</html>     