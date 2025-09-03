<?php
// Authentication Functions
// includes/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';


/**
 * Authenticate user login
 * @param string $username Username or email
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function authenticateUser($username, $password)
{
    try {
        $pdo = getConnection();

        // Check if input is email or username
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE $field = ? AND status <> 'suspended'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Update last login (optional - add last_login column to users table)
            // $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            // $stmt->execute([$user['id']]);

            logEvent("User login successful: " . $user['username'], 'info');
            return $user;
        }

        logEvent("Failed login attempt for: $username", 'warning');
        return false;

    } catch (Exception $e) {
        logEvent("Login error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Register new user
 * @param array $data User registration data
 * @return array Result with success status and message
 */
function registerUser($data)
{
    try {
        $pdo = getConnection();

        // Validate required fields (excluding address lines - handled separately)
        $required_fields = [
            'firstname', 'middlename', 'lastname', 'username', 'email', 'password', 'confirm_password',
            'city', 'state_province', 'postal_code', 'country'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => 'All fields are required.'];
            }
        }

        // Ensure at least one address line is provided
        if (empty($data['address_line_1']) && empty($data['address_line_2'])) {
            return ['success' => false, 'message' => 'At least one address line is required.'];
        }

        // Validate password match
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        // Validate password length
        if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'];
        }

        // Validate username length
        if (strlen($data['username']) < USERNAME_MIN_LENGTH || strlen($data['username']) > USERNAME_MAX_LENGTH) {
            return ['success' => false, 'message' => 'Username must be between ' . USERNAME_MIN_LENGTH . ' and ' . USERNAME_MAX_LENGTH . ' characters.'];
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        // Validate names (minimum 2 characters, only letters, spaces, hyphens, apostrophes)
        foreach (['firstname', 'middlename', 'lastname'] as $field) {
            if (strlen($data[$field]) < 2) {
                $field_display = ucfirst(str_replace('name', ' name', $field));
                return ['success' => false, 'message' => $field_display . ' must be at least 2 characters long.'];
            }
            if (!preg_match('/^[a-zA-Z\s\-\']+$/', $data[$field])) {
                $field_display = ucfirst(str_replace('name', ' name', $field));
                return ['success' => false, 'message' => $field_display . ' can only contain letters, spaces, hyphens, and apostrophes.'];
            }
        }

        // Validate address fields (only if they're provided)
        if (!empty($data['address_line_1']) && strlen($data['address_line_1']) < 5) {
            return ['success' => false, 'message' => 'Address line 1 must be at least 5 characters long.'];
        }
        if (!empty($data['address_line_2']) && strlen($data['address_line_2']) < 3) {
            return ['success' => false, 'message' => 'Address line 2 must be at least 3 characters long.'];
        }
        if (strlen($data['city']) < 2) {
            return ['success' => false, 'message' => 'City must be at least 2 characters long.'];
        }
        if (strlen($data['state_province']) < 2) {
            return ['success' => false, 'message' => 'State/Province must be at least 2 characters long.'];
        }
        if (strlen($data['postal_code']) < 3) {
            return ['success' => false, 'message' => 'Postal code must be at least 3 characters long.'];
        }
        if (strlen($data['country']) < 2) {
            return ['success' => false, 'message' => 'Country must be at least 2 characters long.'];
        }

        // Validate postal code format (basic alphanumeric with optional spaces/hyphens)
        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $data['postal_code'])) {
            return ['success' => false, 'message' => 'Postal code contains invalid characters.'];
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists.'];
        }

        // Handle sponsor assignment with fallback to admin
        $sponsor_result = assignSponsor($data['sponsor_name']);

        if (!$sponsor_result['success'] && !empty($data['sponsor_name'])) {
            // Only fail if a specific sponsor was provided but not found
            return ['success' => false, 'message' => $sponsor_result['message']];
        }

        $sponsor_id = $sponsor_result['sponsor_id'];
        logEvent("User registration sponsor assignment: {$data['username']} -> " . $sponsor_result['message'], 'info');

        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Insert user with name fields and address fields
            $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, username, email, password, sponsor_id, address_line_1, address_line_2, city, state_province, postal_code, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['firstname'], 
                $data['middlename'], 
                $data['lastname'], 
                $data['username'], 
                $data['email'], 
                $hashed_password, 
                $sponsor_id,
                $data['address_line_1'],
                $data['address_line_2'],
                $data['city'],
                $data['state_province'],
                $data['postal_code'],
                $data['country']
            ]);

            $user_id = $pdo->lastInsertId();

            // Create ewallet for user
            $stmt = $pdo->prepare("INSERT INTO ewallet (user_id, balance) VALUES (?, 0.00)");
            $stmt->execute([$user_id]);

            $pdo->commit();

            logEvent("New user registered: " . $data['username'] . " (" . $data['firstname'] . " " . $data['lastname'] . ") from " . $data['city'] . ", " . $data['country'], 'info');
            return ['success' => true, 'message' => 'Registration successful! You can now login.'];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        logEvent("Registration error: " . $e->getMessage(), 'error');
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Get user by ID
 * @param int $user_id User ID
 * @return array|false User data on success, false on failure
 */
function getUserById($user_id)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logEvent("Get user error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get user by username
 * @param string $username Username
 * @return array|false User data on success, false on failure
 */
function getUserByUsername($username)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logEvent("Get user error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update user password
 * @param int $user_id User ID
 * @param string $new_password New password
 * @return bool Success status
 */
function updateUserPassword($user_id, $new_password)
{
    try {
        $pdo = getConnection();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user_id]);

        if ($result) {
            logEvent("Password updated for user ID: $user_id", 'info');
        }

        return $result;

    } catch (Exception $e) {
        logEvent("Password update error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update user profile
 * @param int $user_id User ID
 * @param array $data Profile data
 * @return bool Success status
 */
function updateUserProfile($user_id, $data)
{
    try {
        $pdo = getConnection();
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $user_id;

        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    } catch (Exception $e) {
        logEvent("Update profile error: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Get user statistics
 * @param int $user_id User ID
 * @return array User statistics
 */
function getUserStats($user_id)
{
    try {
        $pdo = getConnection();

        // Get total referrals
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_referrals FROM users WHERE sponsor_id = ?");
        $stmt->execute([$user_id]);
        $total_referrals = $stmt->fetchColumn();

        // Get ewallet balance
        $stmt = $pdo->prepare("SELECT balance FROM ewallet WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $ewallet_balance = $stmt->fetchColumn() ?: 0;

        // Get active packages
        $stmt = $pdo->prepare("SELECT COUNT(*) as active_packages FROM user_packages WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $active_packages = $stmt->fetchColumn();

        // Get total bonuses earned
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_bonuses FROM ewallet_transactions WHERE user_id = ? AND type IN ('bonus', 'referral') AND status = 'completed'");
        $stmt->execute([$user_id]);
        $total_bonuses = $stmt->fetchColumn();

        return [
            'total_referrals' => $total_referrals,
            'ewallet_balance' => $ewallet_balance,
            'active_packages' => $active_packages,
            'total_bonuses' => $total_bonuses
        ];

    } catch (Exception $e) {
        logEvent("Get user stats error: " . $e->getMessage(), 'error');
        return [
            'total_referrals' => 0,
            'ewallet_balance' => 0,
            'active_packages' => 0,
            'total_bonuses' => 0
        ];
    }
}

/**
 * Get admin dashboard stats
 * @return array Admin statistics
 */
function getAdminStats()
{
    try {
        $pdo = getConnection();

        return [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
            'total_earnings' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ewallet_transactions WHERE type IN ('purchase', 'bonus', 'referral')")->fetchColumn(),
            'pending_withdrawals' => $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn(),
            'pending_refills' => $pdo->query("SELECT COUNT(*) FROM refill_requests WHERE status = 'pending'")->fetchColumn(),
            'active_packages' => $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'active'")->fetchColumn()
        ];

    } catch (Exception $e) {
        logEvent("Get admin stats error: " . $e->getMessage(), 'error');
        return [];
    }
}

?>