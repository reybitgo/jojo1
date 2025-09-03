<?php
// api/monthly_bonus.php - JSON API for monthly bonus data
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();

try {
    $pdo = getConnection();

    // Get user's monthly bonuses
    $stmt = $pdo->prepare("
        SELECT mb.*, p.name as package_name, p.price
        FROM monthly_bonuses mb
        JOIN packages p ON mb.package_id = p.id
        WHERE mb.user_id = ?
        ORDER BY mb.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active packages with bonus status
    $stmt = $pdo->prepare("
        SELECT up.*, p.name, p.price, p.id as package_id,
               (p.price * ? / 100) as monthly_bonus_amount,
               CASE 
                   WHEN up.current_cycle > ? THEN 'withdraw_remine'
                   WHEN up.current_cycle = ? THEN 'last_month'
                   ELSE 'earning'
               END as status
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? AND up.status = 'active'
    ");
    $stmt->execute([MONTHLY_BONUS_PERCENTAGE, BONUS_MONTHS, BONUS_MONTHS, $user_id]);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'bonuses' => $bonuses,
        'packages' => $packages,
        'bonus_percentage' => MONTHLY_BONUS_PERCENTAGE,
        'bonus_months' => BONUS_MONTHS,
        'currency' => DEFAULT_CURRENCY // Add this line
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>