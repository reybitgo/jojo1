<?php
// cron/auto_complete.php
// https://btc3.site/cron/auto_complete.php?key=secret123&status=pending&type=withdrawal_charge
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$secret = 'secret123';          // change this
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    exit('Forbidden');
}

$type   = trim($_GET['type']   ?? '');
$status = trim($_GET['status'] ?? '');

if ($type === '' || $status === '') {
    exit("Missing 'type' or 'status' parameter.\n");
}

if ($status !== 'pending') {
    exit("Only 'pending' rows can be auto-completed.\n");
}

header('Content-Type: text/plain');

try {
    $pdo = getConnection();

    /* Fetch pending rows of the requested type */
    $stmt = $pdo->prepare("
        SELECT id, user_id, amount, description, reference_id
        FROM ewallet_transactions
        WHERE type = ? AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->execute([$type]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        exit("No pending '$type' transactions found.\n");
    }

    $completed = 0;

    foreach ($rows as $tx) {
        $pdo->beginTransaction();

        try {
            /* 1. Update transaction status → completed */
            $upd = $pdo->prepare("
                UPDATE ewallet_transactions
                SET status = 'completed', description = CONCAT(description, ' - auto-completed')
                WHERE id = ?
            ");
            $upd->execute([$tx['id']]);

            /* 2. Credit user e-wallet */
            $updBal = $pdo->prepare("
                UPDATE ewallet
                SET balance = balance + ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $updBal->execute([$tx['amount'], $tx['user_id']]);

            $pdo->commit();
            $completed++;

            echo "Completed tx #{$tx['id']} for user {$tx['user_id']} amount {$tx['amount']} " . DEFAULT_CURRENCY . "\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error processing tx #{$tx['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "Total rows auto-completed: $completed\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}