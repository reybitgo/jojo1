<?php
// cron/monthly_bonus_processor.php – daily 30-day bonus runner
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// if (php_sapi_name() !== 'cli') {
//     die("CLI only\n");
// }

echo "Daily bonus check started …\n";

try {
    $pdo = getConnection();

    // 1) find packages whose 30-day window is due
    $stmt = $pdo->prepare("
        SELECT up.id          AS up_id,
               up.user_id,
               up.package_id,
               up.current_cycle,
               p.price,
               u.username
        FROM   user_packages up
        JOIN   packages p ON p.id = up.package_id
        JOIN   users    u ON u.id = up.user_id
        WHERE  up.status = 'active'
          AND  up.current_cycle <= :max_cycle
          AND  up.next_bonus_date <= NOW()
    ");
    $stmt->execute(['max_cycle' => BONUS_MONTHS]);
    $due = $stmt->fetchAll();

    echo count($due) . " packages ready for bonus\n";

    foreach ($due as $row) {

        $bonus = ($row['price'] * MONTHLY_BONUS_PERCENTAGE) / 100;

        // 2) run inside a small transaction
        $pdo->beginTransaction();

        // 2-a) record the bonus
        $pdo->prepare("
            INSERT INTO monthly_bonuses
              (user_id, package_id, user_package_id, month_number, amount)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
                    $row['user_id'],
                    $row['package_id'],
                    $row['up_id'],
                    $row['current_cycle'],
                    $bonus
                ]);

        // 2-b) credit e-wallet
        $pdo->prepare("
            UPDATE ewallet
            SET balance = balance + ?, updated_at = NOW()
            WHERE user_id = ?
        ")->execute([$bonus, $row['user_id']]);

        // 2-c) add transaction log
        $pdo->prepare("
            INSERT INTO ewallet_transactions
              (user_id, type, amount, description, status, is_withdrawable)
            VALUES (?, 'bonus', ?, ?, 'completed', 1)
        ")->execute([
                    $row['user_id'],
                    $bonus,
                    "Monthly bonus cycle {$row['current_cycle']}"
                ]);

        // 2-d) advance cycle & next bonus date
        $newCycle = $row['current_cycle'] + 1;
        $nextDate = (new DateTime())->modify('+30 days')->format('Y-m-d H:i:s');

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
        echo "Paid {$bonus} USDT to {$row['username']} (cycle {$row['current_cycle']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Daily bonus check finished\n";
?>