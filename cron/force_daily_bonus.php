<?php
// cron/force_daily_bonus.php – respects per-package maturity_period & daily_percentage
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$secret = 'secret123';                        // change in prod
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    exit('Access denied.');
}

// if (php_sapi_name() !== 'cli') die("CLI only\n");
header('Content-Type: text/plain');

try {
    $pdo = getConnection();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    /* 1) Users owning daily packages */
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username
        FROM users u
        JOIN user_packages up ON up.user_id = u.id
        JOIN packages p       ON p.id = up.package_id
        WHERE p.mode = 'daily'
    ");
    $stmt->execute();
    $dailyOwners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dailyOwners) exit("No daily-package owners.\n");

    foreach ($dailyOwners as $owner) {
        $uid = $owner['id'];

        /* 2) Total daily-package target for this user */
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(p.target_value), 0)
            FROM user_packages up
            JOIN packages p ON p.id = up.package_id
            WHERE up.user_id = ? AND p.mode = 'daily'
        ");
        $stmt->execute([$uid]);
        $totalTarget = (float)$stmt->fetchColumn();

        /* 3) Lifetime earnings BEFORE today’s bonus */
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(bw.amount), 0)
            FROM bonus_wallet bw
            JOIN user_packages up ON up.id = bw.user_package_id
            JOIN packages p       ON p.id  = up.package_id
            WHERE bw.user_id = ? AND p.mode = 'daily'
        ");
        $stmt->execute([$uid]);
        $lifetimeDaily = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM ewallet_transactions
            WHERE user_id = ? AND type = 'referral' AND status = 'completed'
        ");
        $stmt->execute([$uid]);
        $lifetimeReferral = (float)$stmt->fetchColumn();
        $lifetimeSoFar = $lifetimeDaily + $lifetimeReferral;

        /* 4) Active packages ready for daily bonus (use maturity_period & daily_percentage) */
        $stmt = $pdo->prepare("
            SELECT up.id,
                   up.package_id,
                   p.price,
                   p.daily_percentage,
                   p.maturity_period,
                   up.current_cycle
            FROM user_packages up
            JOIN packages p ON p.id = up.package_id
            WHERE up.user_id   = ?
              AND p.mode       = 'daily'
              AND up.status    = 'active'
              AND (up.current_cycle - 1) <= p.maturity_period
        ");
        $stmt->execute([$uid]);
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($packages as $pkg) {
            $fullDaily = ($pkg['price'] * $pkg['daily_percentage']) / 100;

            /* 5) Exact daily bonus calculation */
            $newTotal = $lifetimeSoFar + $fullDaily;
            if ($newTotal > $totalTarget) {
                $partialDaily = max(0, $totalTarget - $lifetimeSoFar);
            } else {
                $partialDaily = $fullDaily;
            }

            if ($partialDaily <= 0) continue;

            /* 6) Award & deactivate */
            $pdo->beginTransaction();

            // award partial daily
            $pdo->prepare("
                INSERT INTO bonus_wallet
                  (user_id, user_package_id, package_id, cycle, amount)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $uid,
                $pkg['id'],
                $pkg['package_id'],
                $pkg['current_cycle'],
                $partialDaily
            ]);

            $pdo->prepare("
                UPDATE ewallet SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?
            ")->execute([$partialDaily, $uid]);

            $pdo->prepare("
                INSERT INTO ewallet_transactions
                  (user_id, type, amount, description, status, is_withdrawable)
                VALUES (?, 'bonus', ?, 'Force daily exact bonus', 'completed', 1)
            ")->execute([$uid, $partialDaily]);

            // advance cycle & mark completed if needed
            $newCycle = $pkg['current_cycle'] + 1;
            $pdo->prepare("
                UPDATE user_packages
                SET current_cycle = ?,
                    status = CASE WHEN ? >= (total_cycles + 1) THEN 'completed' ELSE 'active' END
                WHERE id = ?
            ")->execute([$newCycle, $newCycle, $pkg['id']]);    

            $pdo->commit();
            echo "Credited {$partialDaily} USDT daily to {$owner['username']} (pkg {$pkg['id']}, maturity {$pkg['maturity_period']})\n";

            // deactivate if target exactly met
            if ($lifetimeSoFar + $partialDaily >= $totalTarget) {
                $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$uid]);
                echo "Deactivated {$owner['username']} – exact daily bonus ($partialDaily) hit target\n";
            }

            $lifetimeSoFar += $partialDaily;  // keep running total
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
}