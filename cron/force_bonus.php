<?php
// cron/force_bonus.php
// Forces one cycle per active package → saves bonus into bonus_wallet
// URL: https://your-domain.com/cron/force_bonus.php?key=YOUR_SECRET_KEY

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$secret = 'secret123'; // ← change to a strong random string
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    exit('Access denied.');
}

header('Content-Type: text/plain');

try {
    $pdo = getConnection();

    // All active packages that still have cycles left
    $stmt = $pdo->prepare("
        SELECT up.id          AS up_id,
               up.user_id,
               up.package_id,
               up.current_cycle,
               p.price,
               u.username
        FROM   user_packages up
        JOIN   packages p  ON p.id = up.package_id
        JOIN   users u     ON u.id = up.user_id
        WHERE  up.status = 'active'
          AND  u.status = 'active'      
          AND  up.current_cycle <= :max_cycle
    ");
    $stmt->execute(['max_cycle' => BONUS_MONTHS]);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$packages) {
        exit("No eligible packages.\n");
    }

    foreach ($packages as $row) {
        $bonus = ($row['price'] * MONTHLY_BONUS_PERCENTAGE) / 100;

        $pdo->beginTransaction();

        // 1. Store bonus in bonus_wallet
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
            "Force bonus cycle {$row['current_cycle']}"
        ]);

        // 2. Advance cycle & mark completed if needed
        $newCycle = $row['current_cycle'] + 1;
        $pdo->prepare("
            UPDATE user_packages
            SET current_cycle = ?,
                next_bonus_date = NULL,
                status = CASE WHEN ? > ? THEN 'completed' ELSE 'active' END
            WHERE id = ?
        ")->execute([
            $newCycle,
            $newCycle,
            BONUS_MONTHS,
            $row['up_id']
        ]);

        $pdo->commit();
        echo "Forced {$bonus} " . DEFAULT_CURRENCY . " into bonus_wallet for {$row['username']} (cycle {$row['current_cycle']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}