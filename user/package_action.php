<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$action  = $_GET['action'] ?? '';
$pkgId   = (int) ($_GET['id'] ?? 0);

if (!in_array($action, ['pullout', 'retain', 'recycle'], true)) {
    redirectWithMessage('dashboard.php', 'Invalid action.', 'error');
}

try {
    $pdo = getConnection();

    /* ---------- 1. Fetch package ---------- */
    $stmt = $pdo->prepare("
        SELECT up.*, p.price, p.mode
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.id = ? AND up.user_id = ?
    ");
    $stmt->execute([$pkgId, $user_id]);
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pkg) {
        redirectWithMessage('dashboard.php', 'Package not found.', 'error');
    }

    /* ---------- 2. Accumulated bonus ---------- */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM bonus_wallet
        WHERE user_package_id = ?
    ");
    $stmt->execute([$pkgId]);
    $totalBonus = (float) $stmt->fetchColumn();

    $pdo->beginTransaction();

    switch ($action) {
        /* ---- PULL-OUT (withdraw capital) ---- */
        case 'pullout':
            processEwalletTransaction(
                $user_id,
                'refund',
                $pkg['price'],
                "Pullout capital from package {$pkg['id']}",
                $pkgId
            );

            $pdo->prepare("UPDATE user_packages SET status = 'withdrawn' WHERE id = ?")
                ->execute([$pkgId]);
            $msg = 'Package pulled out. Capital refunded.';
            break;

        /* ---- RETAIN (reset monthly) ---- */
        case 'retain':
            if ($totalBonus > 0) {
                processEwalletTransaction(
                    $user_id,
                    'retain',
                    0,
                    "Retain capital for package {$pkg['id']}",
                    $pkgId
                );
            }

            $pdo->prepare("
                UPDATE user_packages
                SET current_cycle   = 1,
                    total_cycles    = ?,
                    status          = 'active',
                    purchase_date   = NOW(),
                    next_bonus_date = DATE_ADD(NOW(), INTERVAL 30 DAY)
                WHERE id = ?
            ")->execute([BONUS_MONTHS, $pkgId]);

            $pdo->prepare("DELETE FROM bonus_wallet WHERE user_package_id = ?")
                ->execute([$pkgId]);
            $msg = 'Monthly package retained and reset.';
            break;

        /* ---- RECYCLE (reset daily) ---- */
        case 'recycle':
            if ($pkg['mode'] !== 'daily') {
                redirectWithMessage('dashboard.php', 'Not a daily package.', 'error');
            }

            $balance = getEwalletBalance($user_id);
            if ($balance < $pkg['price']) {
                redirectWithMessage('dashboard.php', 'Insufficient balance to re-cycle.', 'error');
            }

            // 1. Debit user
            processEwalletTransaction(
                $user_id,
                'purchase',
                -$pkg['price'],
                "Re-cycle daily package {$pkg['name']}",
                $pkgId
            );

            // 2. Reset package
            $pdo->prepare("
                UPDATE user_packages
                SET current_cycle   = 1,
                    total_cycles    = ?,
                    status          = 'active',
                    purchase_date   = NOW(),
                    next_bonus_date = DATE_ADD(NOW(), INTERVAL 1 DAY)
                WHERE id = ?
            ")->execute([BONUS_DAYS, $pkgId]);

            // 3. Clean bonus history
            $pdo->prepare("DELETE FROM bonus_wallet WHERE user_package_id = ?")
                ->execute([$pkgId]);

            $msg = 'Daily package re-cycled successfully.';
            break;

        default:
            redirectWithMessage('dashboard.php', 'Invalid action.', 'error');
    }

    $pdo->commit();
    redirectWithMessage('dashboard.php', $msg, 'success');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectWithMessage('dashboard.php', 'Action failed: ' . $e->getMessage(), 'error');
}