<?php
/**
 * cron/monthly_bonus_processor.php
 * Credits the MONTHLY bonus into bonus_wallet for packages whose mode = 'monthly'.
 * Run via CLI or cron.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

header('Content-Type: text/plain');

try {
    $pdo = getConnection();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    /* 1️⃣  Packages ready for monthly bonus (mode = monthly) */
    $stmt = $pdo->prepare("
        SELECT up.id          AS up_id,
               up.user_id,
               up.package_id,
               up.current_cycle,
               p.price,
               p.maturity_period,
               u.username
        FROM   user_packages up
        JOIN   packages p  ON p.id = up.package_id
        JOIN   users u     ON u.id = up.user_id
        WHERE  p.mode      = 'monthly'
          AND  up.status   = 'active'
          AND  u.status    = 'active'
          AND  up.current_cycle <= p.maturity_period
          AND  (up.next_bonus_date IS NULL OR up.next_bonus_date <= NOW())
    ");
    $stmt->execute();
    $due = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$due) {
        exit("No monthly packages ready for bonus.\n");
    }

    foreach ($due as $row) {
        $bonus = ($row['price'] * MONTHLY_BONUS_PERCENTAGE) / 100;

        $pdo->beginTransaction();

        // 1. Record monthly bonus
        $pdo->prepare("
            INSERT INTO bonus_wallet
              (user_id, user_package_id, package_id, cycle, amount)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $row['user_id'],
            $row['up_id'],
            $row['package_id'],
            $row['current_cycle'],
            $bonus
        ]);

        $pdo->prepare("
            UPDATE ewallet
            SET balance = balance + ?, updated_at = NOW()
            WHERE user_id = ?
        ")->execute([$bonus, $row['user_id']]);

        $pdo->prepare("
            INSERT INTO ewallet_transactions
              (user_id, type, amount, description, status, is_withdrawable)
            VALUES (?, ?, ?, ?, 'completed', 1)
        ")->execute([
            $row['user_id'], 
            'bonus',
            $bonus,
            "Daily bonus cycle {$row['current_cycle']}"
        ]);

        // 2. Advance cycle and set next bonus date
        $newCycle = $row['current_cycle'] + 1;
        $nextDate = (new DateTime('now'))->modify('+30 days')->format('Y-m-d H:i:s');

        $pdo->prepare("
            UPDATE user_packages
            SET current_cycle   = ?,
                next_bonus_date = ?,
                status          = CASE WHEN ? > ? THEN 'completed' ELSE 'active' END
            WHERE id = ?
        ")->execute([
            $newCycle,
            $nextDate,
            $newCycle,
            BONUS_MONTHS,
            $row['up_id']
        ]);

        $pdo->commit();
        echo "Credited {$bonus} USDT monthly to {$row['username']} (cycle {$row['current_cycle']}/{$row['maturity_period']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}