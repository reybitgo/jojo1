<?php
// api/referral_bonus.php - JSON API for referral data with all levels
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$action = $_GET['action'] ?? 'stats';

try {
    $pdo = getConnection();

    switch ($action) {
        case 'tree':
            // Get complete referral tree data for D3.js (all levels)
            $user = getUserById($user_id);
            $tree = [
                'name' => $user['username'],
                'email' => $user['email'],
                'created_at' => $user['created_at'],
                'id' => $user['id'],
                'level' => 0,
                'children' => []
            ];

            // Recursively build the complete tree
            $tree['children'] = buildReferralTree($pdo, $user_id, 1);

            echo json_encode($tree);
            break;

        case 'stats':
            // Get comprehensive referral statistics
            $allReferrals = getAllReferrals($pdo, $user_id);

            $stats = [
                'total_referrals' => count($allReferrals),
                'level1_count' => 0,
                'level2_count' => 0,
                'level3_count' => 0,
                'level4_count' => 0,
                'level5_count' => 0,
                'level6_plus_count' => 0,
                'max_depth' => 0,
                'level2_bonus' => 0,
                'level3_bonus' => 0,
                'level4_bonus' => 0,
                'level5_bonus' => 0,
                'total_bonus' => 0
            ];

            foreach ($allReferrals as $referral) {
                $level = $referral['level'];
                $stats['max_depth'] = max($stats['max_depth'], $level);

                switch ($level) {
                    case 1:
                        $stats['level1_count']++;
                        break;
                    case 2:
                        $stats['level2_count']++;
                        break;
                    case 3:
                        $stats['level3_count']++;
                        break;
                    case 4:
                        $stats['level4_count']++;
                        break;
                    case 5:
                        $stats['level5_count']++;
                        break;
                    default:
                        $stats['level6_plus_count']++;
                        break;
                }
            }

            // Get bonus amounts from referral_bonuses table
            $stmt = $pdo->prepare("
                SELECT 
                    level,
                    SUM(amount) as total_amount
                FROM referral_bonuses
                WHERE user_id = ?
                GROUP BY level
            ");
            $stmt->execute([$user_id]);
            $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($bonuses as $bonus) {
                switch ($bonus['level']) {
                    case 2:
                        $stats['level2_bonus'] = floatval($bonus['total_amount']);
                        break;
                    case 3:
                        $stats['level3_bonus'] = floatval($bonus['total_amount']);
                        break;
                    case 4:
                        $stats['level4_bonus'] = floatval($bonus['total_amount']);
                        break;
                    case 5:
                        $stats['level5_bonus'] = floatval($bonus['total_amount']);
                        break;
                }
                $stats['total_bonus'] += floatval($bonus['total_amount']);
            }

            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'referrals':
            // Get detailed referral list with all levels
            $allReferrals = getAllReferralsDetailed($pdo, $user_id);
            echo json_encode(['success' => true, 'referrals' => $allReferrals]);
            break;

        case 'level':
            // Get referrals for a specific level
            $level = intval($_GET['level'] ?? 1);
            $referrals = getReferralsByLevel($pdo, $user_id, $level);
            echo json_encode(['success' => true, 'level' => $level, 'referrals' => $referrals]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Recursively build the complete referral tree
 */
function buildReferralTree($pdo, $sponsor_id, $level, $maxDepth = 10)
{
    if ($level > $maxDepth) {
        return []; // Prevent infinite recursion
    }

    $stmt = $pdo->prepare("
        SELECT id, username, email, created_at, sponsor_id
        FROM users
        WHERE sponsor_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$sponsor_id]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $children = [];
    foreach ($referrals as $referral) {
        $child = [
            'name' => $referral['username'],
            'email' => $referral['email'],
            'created_at' => $referral['created_at'],
            'id' => $referral['id'],
            'level' => $level,
            'children' => []
        ];

        // Recursively get children for this referral
        $child['children'] = buildReferralTree($pdo, $referral['id'], $level + 1, $maxDepth);
        $children[] = $child;
    }

    return $children;
}

/**
 * Get all referrals for a user (all levels) with level information
 */
function getAllReferrals($pdo, $user_id, $currentLevel = 1, $maxDepth = 10)
{
    if ($currentLevel > $maxDepth) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT id, username, email, created_at, sponsor_id
        FROM users
        WHERE sponsor_id = ?
    ");
    $stmt->execute([$user_id]);
    $directReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allReferrals = [];

    foreach ($directReferrals as $referral) {
        $referral['level'] = $currentLevel;
        $allReferrals[] = $referral;

        // Get indirect referrals recursively
        $indirectReferrals = getAllReferrals($pdo, $referral['id'], $currentLevel + 1, $maxDepth);
        $allReferrals = array_merge($allReferrals, $indirectReferrals);
    }

    return $allReferrals;
}

/**
 * Get detailed referral information with counts
 */
function getAllReferralsDetailed($pdo, $user_id)
{
    $allReferrals = getAllReferrals($pdo, $user_id);

    $detailed = [];
    foreach ($allReferrals as $referral) {
        // Count how many referrals this person has
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as referral_count
            FROM users
            WHERE sponsor_id = ?
        ");
        $stmt->execute([$referral['id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);

        $referral['referral_count'] = intval($count['referral_count']);
        $detailed[] = $referral;
    }

    return $detailed;
}

/**
 * Get referrals for a specific level
 */
function getReferralsByLevel($pdo, $user_id, $targetLevel)
{
    $allReferrals = getAllReferrals($pdo, $user_id);

    return array_filter($allReferrals, function ($referral) use ($targetLevel) {
        return $referral['level'] == $targetLevel;
    });
}
?>