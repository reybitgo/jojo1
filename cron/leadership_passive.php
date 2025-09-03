<?php
/**
 *  Daily Leadership Passive distribution
 *  Crontab: 0 0 * * * php /path/cron/leadership_passive.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli') exit("CLI only\n");

date_default_timezone_set('Asia/Manila');
$cycle = date('Y-m-01');          // month key: YYYY-MM-01

/* ---------- System settings ---------- */
$enabled = getAdminSetting('leadership_enabled');
if ($enabled != '1') exit("Leadership Passive disabled.\n");

$quota  = (float)getAdminSetting('direct_package_quota');
$minCnt = (int)getAdminSetting('min_direct_count');
$maxLvl = min(5, (int)getAdminSetting('leadership_levels'));

$levels = [];
for ($l = 1; $l <= $maxLvl; $l++) {
    $levels[$l] = (float)getAdminSetting("leadership_level_{$l}_percentage");
}

$pdo = getConnection();
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

/* ---------- 1. Aggregate all bonus for the cycle ---------- */
$stmt = $pdo->prepare("
    SELECT user_id, SUM(amount) AS total_bonus
    FROM bonus_wallet
    WHERE DATE_FORMAT(created_at, '%Y-%m-01') = :cycle
    GROUP BY user_id
");
$stmt->execute(['cycle' => $cycle]);
$bonusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$bonusRows) exit("No bonuses to distribute.\n");

/* ---------- 2. Build sponsorship tree ---------- */
$tree = [];
foreach ($pdo->query("SELECT id, sponsor_id FROM users") as $u) {
    $tree[$u['id']] = (int)$u['sponsor_id'];
}

/* ---------- 3. Qualification helper ---------- */
function qualifies($uid, $pdo, $quota, $minCnt) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.price),0) AS total,
               COUNT(DISTINCT up.user_id) AS cnt
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

/* ---------- 4. Distribute to uplines ---------- */
$pdo->beginTransaction();
try {
    foreach ($bonusRows as $row) {
        $beneficiaryId = $row['user_id'];
        $bonus         = (float)$row['total_bonus'];
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
                    INSERT INTO leadership_passive
                        (sponsor_id, beneficiary_id, level, amount, month_cycle)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$upline, $beneficiaryId, $lvl, $pay, $cycle]);

                processEwalletTransaction(
                    $upline,
                    'leadership',
                    $pay,
                    "Leadership passive L$lvl from user $beneficiaryId (cycle $cycle)",
                    null
                );
            }
            $current = $upline;
            $lvl++;
        }
    }
    $pdo->commit();
    echo "✅ Leadership Passive distributed for cycle $cycle.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}