<?php
// Utility Functions
// includes/functions.php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Get all packages
 * @return array Array of packages
 */
function getAllPackages()
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT * FROM packages WHERE status = 'active' ORDER BY price ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get packages error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get package by ID
 * @param int $package_id Package ID
 * @return array|false Package data or false if not found
 */
function getPackageById($package_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND status = 'active'");
        $stmt->execute([$package_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logEvent("Get package error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get user's ewallet balance
 * @param int $user_id User ID
 * @return float Ewallet balance
 */
function getEwalletBalance($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT balance FROM ewallet WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();
        return $result !== false ? floatval($result) : 0.00;
    } catch (Exception $e) {
        logEvent("Get ewallet balance error: " . $e->getMessage(), 'error');
        return 0.00;
    }
}

/**
 * Get user's withdrawable balance
 * @param int $user_id User ID
 * @return float Withdrawable balance
 */
function getWithdrawableBalance($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as withdrawable_balance
            FROM ewallet_transactions
            WHERE user_id = ? AND is_withdrawable = 1
        ");
        $stmt->execute([$user_id]);
        return floatval($stmt->fetchColumn());
    } catch (Exception $e) {
        logEvent("Get withdrawable balance error: " . $e->getMessage(), 'error');
        return 0.00;
    }
}

/**
 * Update ewallet balance
 * @param int $user_id User ID
 * @param float $new_balance New balance
 * @return bool Success status
 */
function updateEwalletBalance($user_id, $new_balance)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        return $stmt->execute([$new_balance, $user_id]);
    } catch (Exception $e) {
        logEvent("Update ewallet balance error: " . $e->getMessage(), 'error');
        return false;
    }
}

function addEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null, $is_withdrawable = 0)
{
    try {
        error_log("Adding ewallet transaction: user=$user_id, type=$type, amount=$amount, description=$description, reference_id=$reference_id, is_withdrawable=$is_withdrawable");

        $pdo = getConnection();
        $pdo->beginTransaction(); // Start a transaction

        $current_balance = getEwalletBalance($user_id);
        $new_balance = $current_balance + $amount;

        // Update balance
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        if (!$stmt->execute([$new_balance, $user_id])) {
            error_log("Failed to update balance for user $user_id");
            $pdo->rollBack(); // Rollback transaction
            return false;
        }

        // Determine the status based on the type
        $status = in_array($type, ['referral', 'bonus', 'retain', 'transfer', 'transfer_charge', 'withdrawal_charge', 'purchase', 'refund', 'leadership']) ? 'completed' : 'pending';

        // Ensure is_withdrawable is a valid integer (0 or 1)
        $is_withdrawable = (int) $is_withdrawable;

        // Add transaction
        $stmt = $pdo->prepare("
            INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status, is_withdrawable) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt->execute([$user_id, $type, $amount, $description, $reference_id, $status, $is_withdrawable])) {
            error_log("Failed to add transaction");
            $pdo->rollBack(); // Rollback transaction
            return false;
        }

        $pdo->commit(); // Commit transaction
        error_log("Transaction added successfully for user $user_id");
        return true;

    } catch (Exception $e) {
        error_log("Transaction error: " . $e->getMessage());
        $pdo->rollBack(); // Rollback transaction on error
        return false;
    }
}

function processEwalletTransaction($user_id, $type, $amount, $description, $reference_id = null)
{
    try {
        $pdo = getConnection();

        // Check if already in transaction
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        $current_balance = getEwalletBalance($user_id);
        $new_balance = $current_balance + $amount;

        if ($amount < 0 && $new_balance < 0) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        // Update balance
        $stmt = $pdo->prepare("UPDATE ewallet SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        if (!$stmt->execute([$new_balance, $user_id])) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        // Add transaction
        $status = in_array($type, ['referral', 'bonus', 'retain', 'transfer', 'transfer_charge', 'withdrawal_charge', 'purchase', 'refund', 'leadership']) ? 'completed' : 'pending';
        $is_withdrawable = ($type === 'transfer' || $type === 'purchase' || $type === 'deposit') ? 0 : 1;

        $stmt = $pdo->prepare("
            INSERT INTO ewallet_transactions (user_id, type, amount, description, reference_id, status, is_withdrawable) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt->execute([$user_id, $type, $amount, $description, $reference_id, $status, $is_withdrawable])) {
            if ($shouldBegin)
                $pdo->rollBack();
            return false;
        }

        if ($shouldBegin) {
            $pdo->commit();
        }

        return true;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Get user's transaction history
 * @param int $user_id User ID
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Transaction history
 */
function getTransactionHistory($user_id, $limit = 20, $offset = 0)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ewallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get transaction history error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Purchase a package (monthly or daily aware)
 * @param int   $user_id
 * @param int   $package_id
 * @return array ['success'=>bool,'message'=>string]
 */
function purchasePackage($user_id, $package_id)
{
    try {
        $pdo = getConnection();
        $package = getPackageById($package_id);
        if (!$package) {
            return ['success' => false, 'message' => 'Package not found.'];
        }

        $balance = getEwalletBalance($user_id);
        if ($balance < $package['price']) {
            return ['success' => false, 'message' => 'Insufficient e-wallet balance.'];
        }

        $pdo->beginTransaction();

        // Debit user
        $debitOk = processEwalletTransaction(
            $user_id,
            'purchase',
            -$package['price'],
            "Package purchase: {$package['name']}",
            $package_id
        );
        if (!$debitOk) {
            throw new Exception('Could not debit e-wallet.');
        }

        // Determine cycle/next-date
        $mode           = $package['mode'] ?? 'monthly';
        $total_cycles   = $package['maturity_period'];
        $next_bonus     = ($mode === 'daily')
            ? (new DateTime('now'))->modify('+1 day')->format('Y-m-d H:i:s')
            : (new DateTime('now'))->modify('+30 days')->format('Y-m-d H:i:s');

        // Insert user_package
        $stmt = $pdo->prepare(
            "INSERT INTO user_packages 
               (user_id, package_id, purchase_date, total_cycles, status, next_bonus_date) 
             VALUES (?, ?, NOW(), ?, 'active', ?)"
        );
        $stmt->execute([$user_id, $package_id, $total_cycles, $next_bonus]);

        $pdo->commit();

        // Referral bonuses (unchanged)
        processReferralBonuses($user_id, $package['price'], $package_id);

        return [
            'success' => true,
            'message' => "Package '{$package['name']}' purchased successfully!"
        ];

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logEvent("purchasePackage error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Purchase failed. Please try again.'];
    }
}

/**
 * Get sponsor chain for a user
 * @param int $user_id User ID
 * @param int $max_levels Maximum levels to retrieve
 * @return array Sponsor chain [level => sponsor_id]
 */
function getSponsorChain($user_id, $max_levels = 5)
{
    try {
        $pdo = getConnection();
        $chain = [];
        $current_user_id = $user_id;
        $level = 2; // Start from level 2 (level 1 is direct purchase)

        while ($level <= ($max_levels + 1) && $current_user_id) {
            $stmt = $pdo->prepare("SELECT sponsor_id FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $sponsor_id = $stmt->fetchColumn();

            if ($sponsor_id) {
                $chain[$level] = $sponsor_id;
                $current_user_id = $sponsor_id;
                $level++;
            } else {
                break;
            }
        }

        return $chain;

    } catch (Exception $e) {
        logEvent("Get sponsor chain error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's active packages
 * @param int $user_id User ID
 * @return array Active packages
 */
function getUserActivePackages($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT up.*, p.name, p.price 
            FROM user_packages up 
            JOIN packages p ON up.package_id = p.id 
            WHERE up.user_id = ? AND up.status = 'active' 
            ORDER BY up.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user active packages error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's package history including mode column
 */
function getUserPackageHistory($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT up.*, p.name, p.price, p.mode
            FROM user_packages up
            JOIN packages p ON up.package_id = p.id
            WHERE up.user_id = ?
            ORDER BY up.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user package history error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y g:i A')
{
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 * @param string $date Date string
 * @return string Time ago string
 */
function timeAgo($date)
{
    $time = time() - strtotime($date);

    if ($time < 60)
        return 'just now';
    if ($time < 3600)
        return floor($time / 60) . ' minutes ago';
    if ($time < 86400)
        return floor($time / 3600) . ' hours ago';
    if ($time < 2592000)
        return floor($time / 86400) . ' days ago';
    if ($time < 31104000)
        return floor($time / 2592000) . ' months ago';
    return floor($time / 31104000) . ' years ago';
}

/**
 * Truncate text
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append
 * @return string Truncated text
 */
function truncateText($text, $length = 50, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate random string
 * @param int $length String length
 * @return string Random string
 */
function generateRandomString($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get default admin sponsor ID
 * @return int|null Default admin sponsor ID
 */
function getDefaultSponsorId()
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ? intval($result) : null;
    } catch (Exception $e) {
        logEvent("Get default sponsor error: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Assign sponsor to user (with fallback to admin)
 * @param string $sponsor_username Sponsor username (optional)
 * @return array Result with sponsor_id and message
 */
function assignSponsor($sponsor_username = null)
{
    try {
        $pdo = getConnection();

        if (!empty($sponsor_username)) {
            // Try to find the specified sponsor
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$sponsor_username]);
            $sponsor = $stmt->fetch();

            if ($sponsor) {
                return [
                    'success' => true,
                    'sponsor_id' => $sponsor['id'],
                    'sponsor_username' => $sponsor['username'],
                    'message' => "Sponsor assigned: {$sponsor['username']}"
                ];
            } else {
                // Sponsor not found, check if we should fallback to admin
                if (getAdminSetting('default_sponsor_enabled') === '1') {
                    $admin_sponsor = getDefaultSponsorId();
                    if ($admin_sponsor) {
                        return [
                            'success' => true,
                            'sponsor_id' => $admin_sponsor,
                            'sponsor_username' => 'admin',
                            'message' => "Specified sponsor not found. Assigned to admin as default sponsor."
                        ];
                    }
                }
                return [
                    'success' => false,
                    'sponsor_id' => null,
                    'message' => "Sponsor username '$sponsor_username' not found."
                ];
            }
        } else {
            // No sponsor specified, use admin if enabled
            if (getAdminSetting('orphan_prevention') === '1') {
                $admin_sponsor = getDefaultSponsorId();
                if ($admin_sponsor) {
                    return [
                        'success' => true,
                        'sponsor_id' => $admin_sponsor,
                        'sponsor_username' => 'admin',
                        'message' => "No sponsor specified. Assigned to admin as default sponsor."
                    ];
                }
            }

            // No sponsor assignment
            return [
                'success' => true,
                'sponsor_id' => null,
                'sponsor_username' => null,
                'message' => "No sponsor assigned."
            ];
        }

    } catch (Exception $e) {
        logEvent("Assign sponsor error: " . $e->getMessage(), 'error');
        return [
            'success' => false,
            'sponsor_id' => null,
            'message' => "Error assigning sponsor."
        ];
    }
}

/**
 * Check if admin setting exists and get its value
 * @param string $setting_name Setting name
 * @return mixed Setting value or null if not found
 */
function getAdminSetting($setting_name)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = ?");
        $stmt->execute([$setting_name]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        logEvent("Get admin setting error: " . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Update admin setting
 * @param string $setting_name Setting name
 * @param mixed $setting_value Setting value
 * @return bool Success status
 */
function updateAdminSetting($setting_name, $setting_value)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        return $stmt->execute([$setting_name, $setting_value, $setting_value]);
    } catch (Exception $e) {
        logEvent("Update admin setting error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get withdrawal requests for user
 * @param int $user_id User ID
 * @return array Withdrawal requests
 */
function getUserWithdrawalRequests($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM withdrawal_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get withdrawal requests error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get refill requests for user
 * @param int $user_id User ID
 * @return array Refill requests
 */
function getUserRefillRequests($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM refill_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get refill requests error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's monthly bonus history
 * @param int $user_id User ID
 * @return array Bonus history
 */
function getUserMonthlyBonuses($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT mb.*, p.name as package_name, p.price
            FROM monthly_bonuses mb
            JOIN packages p ON mb.package_id = p.id
            WHERE mb.user_id = ?
            ORDER BY mb.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get user bonuses error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Check if package is eligible for withdraw/remine
 * @param int $package_id Package ID
 * @return bool Eligible status
 */
function isPackageEligibleForWithdrawRemine($package_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM user_packages 
            WHERE id = ? AND current_cycle > ? AND status = 'active'
        ");
        $stmt->execute([$package_id, BONUS_MONTHS]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        logEvent("Check withdraw eligibility error: " . $e->getMessage(), 'error');
        return false;
    }
}

function processWithdrawRemine($user_id, $package_id, $action)
{
    try {
        $pdo = getConnection();

        // Check transaction state
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        try {
            // Get package details
            $stmt = $pdo->prepare("
                SELECT up.*, p.price, p.name
                FROM user_packages up
                JOIN packages p ON up.package_id = p.id
                WHERE up.id = ? AND up.user_id = ? AND up.status = 'active'
                AND up.current_cycle > ?
            ");
            $stmt->execute([$package_id, $user_id, BONUS_MONTHS]);
            $package = $stmt->fetch();

            if (!$package) {
                if ($shouldBegin)
                    $pdo->rollBack();
                return ['success' => false, 'message' => 'Package not eligible'];
            }

            if ($action === 'withdraw') {
                // Use processEwalletTransaction (which handles its own transactions)
                $success = processEwalletTransaction(
                    $user_id,
                    'refund',
                    $package['price'],
                    "Withdraw completed package: {$package['name']}",
                    $package['id']
                );

                if ($success) {
                    $stmt = $pdo->prepare("UPDATE user_packages SET status = 'withdrawn' WHERE id = ?");
                    $stmt->execute([$package_id]);
                }

            } elseif ($action === 'remine') {
                $success = processEwalletTransaction(
                    $user_id,
                    'purchase',
                    -$package['price'],
                    "Remine package: {$package['name']}",
                    $package['id']
                );

                if ($success) {
                    $stmt = $pdo->prepare("
                        UPDATE user_packages 
                        SET current_cycle = 1, status = 'active' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$package_id]);
                }
            }

            if ($shouldBegin) {
                $pdo->commit();
            }

            return ['success' => $success, 'message' => ucfirst($action) . ' processed successfully'];

        } catch (Exception $e) {
            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Processing failed'];
    }
}

/**
 * Get user's referral tree with levels
 * @param int $user_id User ID
 * @param int $max_levels Maximum levels to retrieve
 * @return array Referral tree
 */
function getUserReferralTree($user_id, $max_levels = 5)
{
    try {
        $pdo = getConnection();
        $tree = [];

        function buildReferralTree($pdo, $sponsor_id, $level = 1, $max_levels = 5)
        {
            if ($level > $max_levels)
                return [];

            $stmt = $pdo->prepare("
                SELECT id, username, email, created_at
                FROM users
                WHERE sponsor_id = ? AND status = 'active'
                ORDER BY created_at ASC
            ");
            $stmt->execute([$sponsor_id]);
            $referrals = $stmt->fetchAll();

            foreach ($referrals as &$ref) {
                $ref['level'] = $level;
                $ref['children'] = buildReferralTree($pdo, $ref['id'], $level + 1, $max_levels);
                $ref['total_bonus'] = getUserReferralBonus($ref['id']);
            }

            return $referrals;
        }

        return buildReferralTree($pdo, $user_id);

    } catch (Exception $e) {
        logEvent("Get referral tree error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get user's referral statistics
 * @param int $user_id User ID
 * @return array Referral stats
 */
function getUserReferralStats($user_id)
{
    try {
        $pdo = getConnection();

        // Get total referrals by level
        $stats = [
            'total_referrals' => 0,
            'level_stats' => [
                2 => ['count' => 0, 'bonus' => 0],
                3 => ['count' => 0, 'bonus' => 0],
                4 => ['count' => 0, 'bonus' => 0],
                5 => ['count' => 0, 'bonus' => 0]
            ]
        ];

        // Get counts
        $stmt = $pdo->prepare("
            SELECT level, COUNT(*) as count, SUM(amount) as total_bonus
            FROM referral_bonuses
            WHERE user_id = ?
            GROUP BY level
        ");
        $stmt->execute([$user_id]);

        while ($row = $stmt->fetch()) {
            if ($row['level'] >= 2 && $row['level'] <= 5) {
                $stats['level_stats'][$row['level']] = [
                    'count' => $row['count'],
                    'bonus' => $row['total_bonus']
                ];
                $stats['total_referrals'] += $row['count'];
            }
        }

        return $stats;

    } catch (Exception $e) {
        logEvent("Get referral stats error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get referral bonus for a user
 * @param int $user_id User ID
 * @return float Total bonus
 */
function getUserReferralBonus($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_bonus
            FROM referral_bonuses
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();

    } catch (Exception $e) {
        logEvent("Get referral bonus error: " . $e->getMessage(), 'error');
        return 0;
    }
}

function processReferralBonuses(int $buyer_id, float $amount, int $package_id): void
{   
    try {
        $pdo = getConnection();

        // Build sponsor chain (levels 2-5)
        $sponsors = [];
        $current  = getUserById($buyer_id)['sponsor_id'] ?? 0;
        for ($level = 2; $level <= 5 && $current; $level++) {
            $sponsors[$level] = $current;
            $current = getUserById($current)['sponsor_id'] ?? 0;
        }

        foreach ($sponsors as $level => $sponsorId) {
            /* 1️⃣  Does this sponsor own ANY active daily package?  */
            $stmt = $pdo->prepare("
                SELECT 1
                FROM user_packages up
                JOIN packages p ON up.package_id = p.id
                WHERE up.user_id   = ?
                  AND p.mode       = 'daily'
                  AND up.status    = 'active'
                LIMIT 1
            ");
            $stmt->execute([$sponsorId]);
            $hasDaily = (bool)$stmt->fetchColumn();

            if (!$hasDaily) {
                /* Legacy mode – pay full % forever */
                $bonus = ($amount * (REFERRAL_BONUSES[$level] ?? 0)) / 100;
            } else {
                /* Exact-target mode – pay only what tops the daily target */
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(p.target_value),0)
                    FROM user_packages up
                    JOIN packages p ON up.package_id = p.id
                    WHERE up.user_id = ? AND p.mode = 'daily'
                ");
                $stmt->execute([$sponsorId]);
                $totalTarget = (float)$stmt->fetchColumn();

                // Lifetime already paid
                $lifetime = 0;

                // Daily bonuses in bonus_wallet
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount),0) FROM bonus_wallet bw
                    JOIN user_packages up ON bw.user_package_id = up.id
                    JOIN packages p       ON up.package_id = p.id
                    WHERE bw.user_id = ? AND p.mode = 'daily'
                ");
                $stmt->execute([$sponsorId]);
                $lifetime += (float)$stmt->fetchColumn();

                // Referral bonuses in ewallet_transactions
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount),0) FROM ewallet_transactions
                    WHERE user_id = ? AND type = 'referral' AND status = 'completed'
                ");
                $stmt->execute([$sponsorId]);
                $lifetime += (float)$stmt->fetchColumn();

                $fullBonus = ($amount * (REFERRAL_BONUSES[$level] ?? 0)) / 100;
                $needed    = max(0, $totalTarget - $lifetime);
                $bonus     = min($fullBonus, $needed);

                if ($bonus <= 0) continue;

                // Deactivate sponsor once target is hit
                if ($lifetime + $bonus >= $totalTarget) {
                    $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")
                        ->execute([$sponsorId]);
                    logEvent("User $sponsorId deactivated – exact bonus hit target", 'info');
                }
            }

            /* 2️⃣  Record and credit the bonus */
            $pdo->prepare("
                INSERT INTO referral_bonuses
                  (user_id, referred_user_id, level, amount, percentage, package_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $sponsorId, $buyer_id, $level, $bonus,
                REFERRAL_BONUSES[$level] ?? 0, $package_id
            ]);

            processEwalletTransaction(
                $sponsorId,
                'referral',
                $bonus,
                "Exact referral bonus L$level from user $buyer_id",
                $buyer_id
            );
        }
    } catch (Throwable $e) {
        logEvent("processReferralBonuses error: " . $e->getMessage(), 'error');
    }
}

/**
 * Generate identicon avatar from username
 * @param string $username Username to generate identicon for
 * @return string Base64 data URL of identicon
 */
function generateIdenticon($username)
{
    // Simple deterministic hash from username
    $hash = 0;
    for ($i = 0; $i < strlen($username); $i++) {
        $hash = $username[$i] . (($hash << 5) - $hash);
    }

    // Create canvas
    $canvas = imagecreatetruecolor(50, 50);

    // Background
    $bg = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $bg);

    // Color from hash
    $hue = abs($hash) % 360;
    $color = imagecolorallocate(
        $canvas,
        ($hue * 2) % 255,
        ($hue * 3) % 255,
        ($hue * 5) % 255
    );

    // 5x5 symmetric pattern
    $grid = 5;
    $cell = 10;
    for ($x = 0; $x < 5; $x++) {
        for ($y = 0; $y < 5; $y++) {
            if ((abs($hash) >> ($x * 5 + $y)) & 1) {
                // Mirror for symmetry
                imagefilledrectangle(
                    $canvas,
                    $x * $cell,
                    $y * $cell,
                    $x * $cell + $cell - 1,
                    $y * $cell + $cell - 1,
                    $color
                );
                imagefilledrectangle(
                    $canvas,
                    (4 - $x) * $cell,
                    $y * $cell,
                    (4 - $x) * $cell + $cell - 1,
                    $y * $cell + $cell - 1,
                    $color
                );
            }
        }
    }

    // Convert to base64
    ob_start();
    imagepng($canvas);
    $data = ob_get_clean();
    imagedestroy($canvas);

    return 'data:image/png;base64,' . base64_encode($data);
}

function debugLog($message)
{
    $file = __DIR__ . '/../logs/debug_' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] $message\n", FILE_APPEND | LOCK_EX);
}

/**
 * Execute code within a transaction context
 * @param callable $callback Function to execute
 * @return mixed Result of callback
 */
function executeInTransaction($callback)
{
    // Clean usage pattern
    // $result = executeInTransaction(function ($pdo) use ($user_id, $amount) {
    //     // Your transactional code here
    //     $stmt = $pdo->prepare("UPDATE ewallet SET balance = balance + ? WHERE user_id = ?");
    //     $stmt->execute([$amount, $user_id]);
    //     return true;
    // });

    try {
        $pdo = getConnection();
        $inTransaction = $pdo->inTransaction();
        $shouldBegin = !$inTransaction;

        if ($shouldBegin) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;

        } catch (Exception $e) {
            if ($shouldBegin && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $class = $type === 'error' ? 'alert-danger' : 'alert-success';
        echo "<div class='alert $class'>" . htmlspecialchars($_SESSION['flash_message']) . "</div>";
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}

/**
 * Send email using the proven methods from superuser.php
 * (Borrowed from working superuser implementation)
 */
function sendEmail($to, $subject, $body, $debug = false) {
    // Define constants if not already set
    if (!defined('SITE_EMAIL')) define('SITE_EMAIL', 'noreply@' . $_SERVER['HTTP_HOST']);
    if (!defined('SITE_NAME')) define('SITE_NAME', 'Your Site');
    
    $email_sent = false;
    $error_msg = '';
    
    if ($debug) {
        echo "<div class='alert alert-info'><strong>DEBUG MODE:</strong><br>";
        echo "To: " . htmlspecialchars($to) . "<br>";
        echo "Subject: " . htmlspecialchars($subject) . "<br>";
        echo "From: " . htmlspecialchars(SITE_EMAIL) . "<br>";
    }
    
    // Method 1: Try PHPMailer if available (recommended)
    // if (class_exists('PHPMailer\PHPMailer\PHPMailer') && defined('SMTP_HOST')) {
    //     if ($debug) echo "Trying PHPMailer...<br>";
        
    //     try {
    //         $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    //         $mail->isSMTP();
    //         $mail->Host = SMTP_HOST;
    //         $mail->SMTPAuth = true;
    //         $mail->Username = SMTP_USERNAME;
    //         $mail->Password = SMTP_PASSWORD;
    //         $mail->SMTPSecure = SMTP_ENCRYPTION ?? 'tls';
    //         $mail->Port = SMTP_PORT ?? 587;
            
    //         $mail->setFrom(SITE_EMAIL, SITE_NAME);
    //         $mail->addAddress($to);
    //         $mail->Subject = $subject;
    //         $mail->Body = $body;
            
    //         $mail->send();
    //         $email_sent = true;
    //         if ($debug) echo "PHPMailer: SUCCESS<br>";
    //     } catch (Exception $e) {
    //         $error_msg = "PHPMailer error: " . $e->getMessage();
    //         if ($debug) echo "PHPMailer failed: " . htmlspecialchars($error_msg) . "<br>";
    //     }
    // }
    
    // Method 2: Try built-in mail() function as fallback (exact same as superuser)
    if (!$email_sent) {
        if ($debug) echo "Trying native mail() function...<br>";
        
        // Improved headers for better deliverability (copied from superuser.php)
        $headers = array();
        $headers[] = "From: " . SITE_NAME . " <" . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']) . ">";
        $headers[] = "Reply-To: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
        $headers[] = "Return-Path: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/plain; charset=UTF-8";
        $headers[] = "X-Priority: 1";
        
        $headers_string = implode("\r\n", $headers);
        
        if ($debug) {
            echo "Headers: " . htmlspecialchars($headers_string) . "<br>";
        }
        
        if (mail($to, $subject, $body, $headers_string)) {
            $email_sent = true;
            if ($debug) echo "Native mail(): SUCCESS<br>";
            
            // Additional check - log mail attempts (from superuser)
            error_log("Password reset email sent to: $to");
        } else {
            $error = error_get_last();
            $error_msg = "Mail function failed. " . ($error['message'] ?? 'Unknown error');
            error_log("Password reset email failed: $error_msg");
            if ($debug) echo "Native mail(): FAILED - " . htmlspecialchars($error_msg) . "<br>";
        }
    }
    
    if ($debug) {
        if ($email_sent) {
            echo "<strong>EMAIL SENT SUCCESSFULLY!</strong><br>";
        } else {
            echo "<strong>ALL EMAIL METHODS FAILED</strong><br>";
            echo "Error: " . htmlspecialchars($error_msg) . "<br>";
            
            // Show email content for debugging (like superuser fallback)
            echo "--- EMAIL CONTENT ---<br>";
            echo "To: " . htmlspecialchars($to) . "<br>";
            echo "Subject: " . htmlspecialchars($subject) . "<br>";
            echo "Body: " . nl2br(htmlspecialchars($body)) . "<br>";
            echo "--- END EMAIL ---<br>";
        }
        echo "</div>";
    }
    
    return $email_sent;
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    echo "<div class='container mt-4'>";
    echo "<div class='card'>";
    echo "<div class='card-header'><h5>Email Configuration Test</h5></div>";
    echo "<div class='card-body'>";
    
    // Check PHP mail function
    echo "<h6>1. PHP mail() function:</h6>";
    if (function_exists('mail')) {
        echo "<span class='text-success'>✓ Available</span><br>";
    } else {
        echo "<span class='text-danger'>✗ Not available</span><br>";
    }
    
    // Check PHPMailer
    echo "<h6>2. PHPMailer:</h6>";
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<span class='text-success'>✓ PHPMailer class found</span><br>";
        
        // Check SMTP configuration
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            echo "<span class='text-success'>✓ SMTP_HOST configured: " . SMTP_HOST . "</span><br>";
        } else {
            echo "<span class='text-warning'>⚠ SMTP_HOST not configured</span><br>";
        }
        
        if (defined('SMTP_USERNAME') && !empty(SMTP_USERNAME)) {
            echo "<span class='text-success'>✓ SMTP_USERNAME configured</span><br>";
        } else {
            echo "<span class='text-warning'>⚠ SMTP_USERNAME not configured</span><br>";
        }
        
        if (defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD)) {
            echo "<span class='text-success'>✓ SMTP_PASSWORD configured</span><br>";
        } else {
            echo "<span class='text-warning'>⚠ SMTP_PASSWORD not configured</span><br>";
        }
        
    } else {
        echo "<span class='text-warning'>⚠ PHPMailer not installed</span><br>";
    }
    
    // Check server configuration
    echo "<h6>3. Server Configuration:</h6>";
    echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "sendmail_path: " . ini_get('sendmail_path') . "<br>";
    echo "SMTP (php.ini): " . ini_get('SMTP') . "<br>";
    echo "smtp_port (php.ini): " . ini_get('smtp_port') . "<br>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

?>