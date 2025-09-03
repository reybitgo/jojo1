<?php
/**
 *  CLI ONLY
 *  Force-rebuild Leadership Passive for a given cycle.
 *
 *  Usage:
 *    php leadership_passive_force.php               # entire cycle
 *    php leadership_passive_force.php --user-id=42  # single user (still walks tree)
 *
 *  Safe to run multiple times; existing rows for the cycle are deleted first.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

date_default_timezone_set('Asia/Manila');

/* ------------- ARGUMENT PARSING ------------- */
$opts = getopt('', ['user-id::']);
$forceUserId = isset($opts['user-id']) ? (int)$opts['user-id'] : null;

/* ------------- LOAD CONFIG ------------- */
$enabled = getAdminSetting('leadership_enabled');
if ($enabled != '1') exit("Leadership Passive disabled.\n");

$quota   = (float)getAdminSetting('direct_package_quota');
$minCnt  = (int)getAdminSetting('min_direct_count');
$maxLvl  = min(5, (int)getAdminSetting('leadership_levels'));

$levels = [];
for ($l = 1; $l <= $maxLvl; $l++) {
    $levels[$l] = (float)getAdminSetting("leadership_level_{$l}_percentage");
}

$pdo = getConnection();
$cycle = date('Y-m-01');          // use current month; change if you need another

/* ------------- BONUS AGGREGATION ------------- */
$sqlWhere = $forceUserId ? "AND bw.user_id = $forceUserId" : '';
$stmt = $pdo->prepare("
    SELECT bw.user_id,
           SUM(CASE WHEN bw.type = 'daily_bonus'   THEN bw.amount ELSE 0 END) AS daily_bonus,
           SUM(CASE WHEN bw.type = 'monthly_bonus' THEN bw.amount ELSE 0 END) AS monthly_bonus
    FROM bonus_wallet bw
    WHERE DATE_FORMAT(bw.created_at, '%Y-%m-01') = :cycle
      $sqlWhere
    GROUP BY bw.user_id
");
$stmt->execute(['cycle' => $cycle]);
$bonusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$bonusRows) exit("No bonuses to distribute.\n");

$totalBonuses = [];
foreach ($bonusRows as $row) {
    $totalBonuses[$row['user_id']] = $row['daily_bonus'] + $row['monthly_bonus'];
}

/* ------------- SPONSOR TREE ------------- */
$tree = [];
$users = $pdo->query("SELECT id, sponsor_id FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) $tree[$u['id']] = (int)$u['sponsor_id'];

/* ------------- QUALIFICATION HELPER ------------- */
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

/* ------------- DELETE EXISTING (for the cycle / user) ------------- */
if ($forceUserId) {
    $pdo->prepare("DELETE FROM leadership_passive WHERE month_cycle = ? AND beneficiary_id = ?")
        ->execute([$cycle, $forceUserId]);
} else {
    $pdo->prepare("DELETE FROM leadership_passive WHERE month_cycle = ?")
        ->execute([$cycle]);
}

/* ------------- DISTRIBUTE ------------- */
$pdo->beginTransaction();
try {
    foreach ($totalBonuses as $beneficiaryId => $bonus) {
        if ($bonus <= 0) continue;

        $current = $beneficiaryId;
        $lvl = 1;
        while ($lvl <= $maxLvl && isset($tree[$current]) && $tree[$current]) {
            $upline = $tree[$current];
            if (!qualifies($upline, $pdo, $quota, $minCnt)) break;

            $pct = $levels[$lvl] ?? 0;
            $pay = $bonus * $pct / 100;
            if ($pay > 0) {
                $pdo->prepare("
                    INSERT INTO leadership_passive (sponsor_id, beneficiary_id, level, amount, month_cycle)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$upline, $beneficiaryId, $lvl, $pay, $cycle]);

                processEwalletTransaction(
                    $upline,
                    'leadership',
                    $pay,
                    "Leadership passive (FORCE) L$lvl from user $beneficiaryId (cycle $cycle)",
                    null
                );
            }
            $current = $upline;
            $lvl++;
        }
    }
    $pdo->commit();
    echo "✅ Leadership Passive FORCE-completed for cycle $cycle" .
         ($forceUserId ? " (user $forceUserId)" : " (all users)") . "\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}