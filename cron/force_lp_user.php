<?php
/**
 *  Force-run Leadership Passive for ONE user via URL
 *  Only admins (or CLI) may execute it.
 *
 *  GET/POST  ?username=test1  OR  ?user_id=123
 *            &cycle=2024-05-01   (optional, defaults to current month)
 *            &dry=1              (optional, preview only)
 *
 *  Examples:
 *    https://yoursite.com/force_lp_user.php?username=test1
 *    https://yoursite.com/force_lp_user.php?user_id=42&cycle=2024-05-01&dry=1
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

/* ---------- AUTH CHECK ---------- */
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI && !isAdmin()) {           // isAdmin() checks $_SESSION['role'] == 'admin'
    http_response_code(403);
    exit('Access denied.');
}

/* ---------- INPUT ---------- */
$username = $_REQUEST['username'] ?? null;
$userId   = $_REQUEST['user_id']   ?? null;
$cycle    = $_REQUEST['cycle']     ?? date('Y-m-01');
$dry      = isset($_REQUEST['dry']);

$pdo = getConnection();

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

/* ---------- FIND USER ---------- */
if ($username) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $userId = $stmt->fetchColumn();
    if (!$userId) {
        exit("❌ User '$username' not found.");
    }
} elseif (!$userId) {
    exit("❌ Provide username or user_id.");
}

/* ---------- CONFIG ---------- */
$enabled = getAdminSetting('leadership_enabled');
if ($enabled != '1') exit("Leadership Passive disabled.\n");

$quota  = (float)getAdminSetting('direct_package_quota');
$minCnt = (int)getAdminSetting('min_direct_count');
$maxLvl = min(5, (int)getAdminSetting('leadership_levels'));

$levels = [];
for ($l = 1; $l <= $maxLvl; $l++) {
    $levels[$l] = (float)getAdminSetting("leadership_level_{$l}_percentage");
}

/* ---------- BONUS AGGREGATION ---------- */
$stmt = $pdo->prepare("
    SELECT SUM(amount) AS total_bonus
    FROM bonus_wallet
    WHERE user_id = :uid
      AND DATE_FORMAT(created_at, '%Y-%m-01') = :cycle
");
$stmt->execute(['uid' => $userId, 'cycle' => $cycle]);
$totalBonus = (float)($stmt->fetchColumn() ?: 0);

if ($totalBonus <= 0) {
    exit("No bonuses to distribute for user $userId cycle $cycle.");
}

/* ---------- SPONSOR TREE ---------- */
$tree = [];
foreach ($pdo->query("SELECT id, sponsor_id FROM users") as $u) {
    $tree[$u['id']] = (int)$u['sponsor_id'];
}

/* ---------- QUALIFY HELPER ---------- */
function qualifies($uid, $pdo, $quota, $minCnt) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.price),0) AS total, COUNT(DISTINCT up.user_id) AS cnt
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id IN (
            SELECT id FROM users WHERE sponsor_id = ?
        ) AND up.status = 'active'
    ");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    return ($row['cnt'] >= $minCnt && $row['total'] >= $quota);
}

/* ---------- PREVIEW / EXECUTE ---------- */
$report = [];
$current = $userId;
$lvl = 1;

while ($lvl <= $maxLvl && isset($tree[$current]) && $tree[$current]) {
    $upline = $tree[$current];
    if (!qualifies($upline, $pdo, $quota, $minCnt)) break;

    $pct = $levels[$lvl] ?? 0;
    $pay = $totalBonus * $pct / 100;
    if ($pay > 0) {
        $report[] = [
            'level'  => $lvl,
            'upline' => $upline,
            'amount' => $pay
        ];
    }
    $current = $upline;
    $lvl++;
}

if ($dry || $isCLI) {
    echo "DRY RUN for user $userId cycle $cycle\n";
    print_r($report);
    exit;
}

/* ---------- REAL RUN ---------- */
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM leadership_passive WHERE month_cycle = ? AND beneficiary_id = ?")
        ->execute([$cycle, $userId]);

    foreach ($report as $r) {
        $pdo->prepare("
            INSERT INTO leadership_passive (sponsor_id, beneficiary_id, level, amount, month_cycle)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$r['upline'], $userId, $r['level'], $r['amount'], $cycle]);

        processEwalletTransaction(
            $r['upline'],
            'leadership',
            $r['amount'],
            "Leadership force-run L{$r['level']} from user $userId (cycle $cycle)",
            null
        );
    }
    $pdo->commit();
    echo "✅ Leadership Passive FORCE-run complete for user $userId cycle $cycle\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>