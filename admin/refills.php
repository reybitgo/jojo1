<?php
/* --------------  PHP SECTION (unchanged) -------------- */
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin('../login.php');

/* 1. status filter & totals (unchanged) */
$current_status = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'approved', 'rejected'];
$current_status = in_array($current_status, $valid_statuses) ? $current_status : 'all';

$where_conditions = [];
$params = [];
if ($current_status !== 'all') { $where_conditions[] = "rr.status = ?"; $params[] = $current_status; }
$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$tabTotals     = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$refills = []; $error = '';

try {
    $pdo = getConnection();
    foreach (['pending', 'approved', 'rejected'] as $s) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM refill_requests WHERE status = ?");
        $stmt->execute([$s]); $status_counts[$s] = (int)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT status, COALESCE(SUM(amount),0) AS total FROM refill_requests GROUP BY status");
    $stmt->execute(); while ($row = $stmt->fetch()) $tabTotals[$row['status']] = (float)$row['total'];
    $tabTotals['all'] = array_sum($tabTotals);

    /* approve / reject logic (unchanged) */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) redirectWithMessage('refills.php', 'Invalid security token.', 'error');
        $request_id  = intval($_POST['request_id']);
        $action      = $_POST['action'] ?? '';
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM refill_requests WHERE id = ?");
        $stmt->execute([$request_id]); $request = $stmt->fetch();
        if (!$request) redirectWithMessage('refills.php', 'Request not found.', 'error');

        if ($action === 'approve') {
            $inTrans = $pdo->inTransaction(); if (!$inTrans) $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE refill_requests SET status='approved', admin_notes=?, approved_at=NOW() WHERE id=?")->execute([$admin_notes,$request_id]);
                processEwalletTransaction($request['user_id'],'deposit',$request['amount'],'USDT refill approved',$request_id);
                $pdo->prepare("UPDATE ewallet_transactions SET status='completed', description=CONCAT(description,' - Approved') WHERE reference_id=? AND type='deposit' AND status='pending'")->execute([$request_id]);
                if (!$inTrans) $pdo->commit();
                redirectWithMessage('refills.php','Refill approved.','success');
            } catch (Exception $e) {
                if (!$inTrans && $pdo->inTransaction()) $pdo->rollBack();
                redirectWithMessage('refills.php','Error processing refill.','error');
            }
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE refill_requests SET status='rejected', admin_notes=?, approved_at=NOW() WHERE id=?")->execute([$admin_notes,$request_id]);
            $pdo->prepare("UPDATE ewallet_transactions SET status='failed', description=CONCAT(description,' - Rejected') WHERE reference_id=? AND type='deposit' AND status='pending'")->execute([$request_id]);
            redirectWithMessage('refills.php','Refill rejected.','success');
        }
    }

    $refills = $pdo->prepare("SELECT rr.*, u.username, u.email, w.balance AS user_balance
                              FROM refill_requests rr
                              JOIN users u ON rr.user_id = u.id
                              JOIN ewallet w ON u.id = w.user_id
                              $where_clause
                              ORDER BY CASE rr.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END, rr.created_at DESC");
    $refills->execute($params); $refills = $refills->fetchAll();
} catch (Exception $e) {
    $error   = "Failed to load refills";
    $refills = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Refill Requests - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --primary-dark: #1e3c72; }
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .sidebar-desktop { display: none; }
        @media (min-width: 992px) {
            .sidebar-desktop { display: block; width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background: var(--primary-dark); color: #fff; padding-top: 1rem; z-index: 1000; }
            .main-content { margin-left: 250px; }
        }
        @media (max-width: 991px) {
            body > .main-content { padding-top: 1rem !important; margin-top: 1rem !important; }
        }
        .nav-link.active { background: var(--primary) !important; color: #fff !important; border-radius: .25rem; }
        .dashboard-logo { width: calc(100% - 2rem); max-width: 100%; display: block; margin: 0 auto .5rem auto; }
        .admin-badge { display: inline-block; background: #ff4757; color: #fff; font-size: .65rem; font-weight: 700; letter-spacing: .08em; padding: .15rem .2rem; border-radius: .375rem; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
        .tx-hash-container { display: flex; align-items: center; gap: 8px; max-width: 300px; }
        .tx-hash, .modal-tx-hash { font-family: 'Courier New', monospace; font-size: 0.85rem; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; border: 1px solid #dee2e6; word-break: break-all; cursor: pointer; flex: 1; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
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
      <li><a href="refills.php" class="nav-link text-white active"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
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
          <li><a href="users.php" class="nav-link text-white"><i class="fas fa-users me-2"></i>Users</a></li>
          <li><a href="withdrawals.php" class="nav-link text-white"><i class="fas fa-arrow-down me-2"></i>Withdrawals</a></li>
          <li><a href="refills.php" class="nav-link text-white active"><i class="fas fa-arrow-up me-2"></i>Refills</a></li>
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
      <h1 class="h3 fw-bold">Refill Requests</h1>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
      <li><a class="nav-link <?= $current_status === 'all' ? 'active' : '' ?>" href="refills.php">All Requests</a></li>
      <li><a class="nav-link <?= $current_status === 'pending' ? 'active' : '' ?>" href="refills.php?status=pending">Pending
          <?php if ($status_counts['pending'] > 0): ?><span class="badge bg-warning text-dark ms-1"><?= $status_counts['pending'] ?></span><?php endif; ?>
      </a></li>
      <li><a class="nav-link <?= $current_status === 'approved' ? 'active' : '' ?>" href="refills.php?status=approved">Approved
          <?php if ($status_counts['approved'] > 0): ?><span class="badge bg-success ms-1"><?= $status_counts['approved'] ?></span><?php endif; ?>
      </a></li>
      <li><a class="nav-link <?= $current_status === 'rejected' ? 'active' : '' ?>" href="refills.php?status=rejected">Rejected
          <?php if ($status_counts['rejected'] > 0): ?><span class="badge bg-danger ms-1"><?= $status_counts['rejected'] ?></span><?php endif; ?>
      </a></li>
    </ul>

    <!-- Refills Table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= ucfirst($current_status) ?> Refills</h5>
        <span class="fw-bold text-primary">Total: <?= formatCurrency($tabTotals[$current_status]) ?></span>
      </div>
      <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php elseif (empty($refills)): ?>
            <div class="text-center py-4"><i class="fas fa-upload fa-3x text-muted mb-3"></i>
                <p class="text-muted"><?= $current_status !== 'all' ? "No $current_status refills found" : "No refill requests found" ?></p>
            </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Transaction&nbsp;Hash</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($refills as $refill): ?>
                <tr>
                  <td><?= $refill['id'] ?></td>
                  <td><?= htmlspecialchars($refill['username']) ?><small class="text-muted d-block"><?= htmlspecialchars($refill['email']) ?></small></td>
                  <td><?= formatCurrency($refill['amount']) ?></td>
                  <td>
  <?php if ($refill['transaction_hash']): ?>
    <div class="tx-hash-container">
      <div class="tx-hash" data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-transaction-hash'))">
        <?= htmlspecialchars(substr($refill['transaction_hash'], 0, 12)) ?>…
      </div>
      <button type="button" 
              class="btn btn-outline-secondary btn-sm copy-btn" 
              data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" 
              onclick="copyToClipboard(this.getAttribute('data-transaction-hash'), this)" 
              title="Copy">
        <i class="fas fa-copy"></i>
      </button>
    </div>
  <?php else: ?>
    <span class="text-muted">N/A</span>
  <?php endif; ?>
</td>
                  <td><span class="badge bg-<?= $refill['status'] === 'pending' ? 'warning' : ($refill['status'] === 'approved' ? 'success' : 'danger') ?>"><?= ucfirst($refill['status']) ?></span></td>
                  <td><?= timeAgo($refill['created_at']) ?></td>
                  <td>
                    <?php if ($refill['status'] === 'pending'): ?>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $refill['id'] ?>"><i class="fas fa-check"></i></button>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $refill['id'] ?>"><i class="fas fa-times"></i></button>
                      </div>
                    <?php elseif ($refill['admin_notes']): ?>
                      <small class="text-muted" title="<?= htmlspecialchars($refill['admin_notes']) ?>"><i class="fas fa-sticky-note"></i></small>
                    <?php endif; ?>
                  </td>
                </tr>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal<?= $refill['id'] ?>" tabindex="-1">
  <div class="modal-dialog"><form method="POST">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Approve Refill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p>Approve refill of <?= formatCurrency($refill['amount']) ?> for <?= htmlspecialchars($refill['username']) ?>?</p>
        <?php if ($refill['transaction_hash']): ?>
          <div class="mb-3">
            <label class="form-label"><strong>Transaction&nbsp;Hash&nbsp;(click&nbsp;to&nbsp;copy)</strong></label>
            <div class="tx-hash-container">
              <div class="tx-hash" data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-transaction-hash'))">
                <?= htmlspecialchars(substr($refill['transaction_hash'], 0, 12)) ?>…
              </div>
              <button type="button" 
                      class="btn btn-outline-secondary btn-sm copy-btn" 
                      data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" 
                      onclick="copyToClipboard(this.getAttribute('data-transaction-hash'), this)" 
                      title="Copy">
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="request_id" value="<?= $refill['id'] ?>">
        <input type="hidden" name="action" value="approve">
        <label class="form-label">Admin Notes (optional)</label>
        <textarea class="form-control" name="admin_notes" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">Approve</button>
      </div>
    </div>
  </form></div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal<?= $refill['id'] ?>" tabindex="-1">
  <div class="modal-dialog"><form method="POST">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Reject Refill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p>Reject refill of <?= formatCurrency($refill['amount']) ?> for <?= htmlspecialchars($refill['username']) ?>?</p>
        <?php if ($refill['transaction_hash']): ?>
          <div class="mb-3">
            <label class="form-label"><strong>Transaction&nbsp;Hash&nbsp;(click&nbsp;to&nbsp;copy)</strong></label>
            <div class="tx-hash-container">
              <div class="tx-hash" data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-transaction-hash'))">
                <?= htmlspecialchars(substr($refill['transaction_hash'], 0, 12)) ?>…
              </div>
              <button type="button" 
                      class="btn btn-outline-secondary btn-sm copy-btn" 
                      data-transaction-hash="<?= htmlspecialchars($refill['transaction_hash'], ENT_QUOTES) ?>" 
                      onclick="copyToClipboard(this.getAttribute('data-transaction-hash'), this)" 
                      title="Copy">
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="request_id" value="<?= $refill['id'] ?>">
        <input type="hidden" name="action" value="reject">
        <label class="form-label">Reason (optional)</label>
        <textarea class="form-control" name="admin_notes" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject</button>
      </div>
    </div>
  </form></div>
</div>

                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<!-- Toast container -->
<div class="toast-container"></div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced clipboard & toast -->
<script>
/* simple in-page toast ---------------------------------------------------- */
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; transition: opacity .3s';
    toast.innerHTML = `<div class="d-flex align-items-center">
        <i class="fas fa-${type==='success'?'check':'times'}-circle me-2"></i>${msg}</div>`;
    document.body.appendChild(toast);
    setTimeout(()=>{ toast.style.opacity='0'; setTimeout(()=>toast.remove(),300); }, 3000);
}

/* copy-to-clipboard from refills.php -------------------------------------- */
function copyToClipboard(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Wallet address copied!');
            btn.classList.replace('btn-outline-secondary','btn-success');
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(()=>{btn.classList.replace('btn-success','btn-outline-secondary');btn.innerHTML='<i class="fas fa-copy"></i>';}, 2000);
        });
    } else {
        /* legacy fallback */
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select();
        try {
            document.execCommand('copy');
            showToast('Wallet address copied!');
        } catch (_) {
            showToast('Could not copy', 'danger');
        }
        document.body.removeChild(ta);
    }
}
</script>
</body>
</html>