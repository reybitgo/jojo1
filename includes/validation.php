<?php
// Validation Functions
// includes/validation.php

/**
 * Validate name field (firstname, middlename, lastname)
 * @param string $name Name to validate
 * @param string $field_name Field name for error messages
 * @return array Result with valid status and message
 */
function validateName($name, $field_name = 'Name')
{
    if (empty($name)) {
        return ['valid' => false, 'message' => $field_name . ' is required.'];
    }

    if (strlen($name) < 2) {
        return ['valid' => false, 'message' => $field_name . ' must be at least 2 characters long.'];
    }

    if (strlen($name) > 50) {
        return ['valid' => false, 'message' => $field_name . ' must not exceed 50 characters.'];
    }

    // Only allow letters, spaces, hyphens, and apostrophes
    if (!preg_match('/^[a-zA-Z\s\-\']+$/', $name)) {
        return ['valid' => false, 'message' => $field_name . ' can only contain letters, spaces, hyphens, and apostrophes.'];
    }

    // Check for excessive spaces or special characters
    if (preg_match('/\s{2,}/', $name) || preg_match('/[-\']{2,}/', $name)) {
        return ['valid' => false, 'message' => $field_name . ' contains invalid character sequences.'];
    }

    return ['valid' => true, 'message' => $field_name . ' is valid.'];
}

/**
 * Validate username
 * @param string $username Username to validate
 * @return array Result with valid status and message
 */
function validateUsername($username)
{
    if (empty($username)) {
        return ['valid' => false, 'message' => 'Username is required.'];
    }

    if (strlen($username) < USERNAME_MIN_LENGTH) {
        return ['valid' => false, 'message' => 'Username must be at least ' . USERNAME_MIN_LENGTH . ' characters long.'];
    }

    if (strlen($username) > USERNAME_MAX_LENGTH) {
        return ['valid' => false, 'message' => 'Username must not exceed ' . USERNAME_MAX_LENGTH . ' characters.'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores.'];
    }

    return ['valid' => true, 'message' => 'Username is valid.'];
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return array Result with valid status and message
 */
function validateEmail($email)
{
    if (empty($email)) {
        return ['valid' => false, 'message' => 'Email is required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Please enter a valid email address.'];
    }

    if (strlen($email) > 100) {
        return ['valid' => false, 'message' => 'Email address is too long.'];
    }

    return ['valid' => true, 'message' => 'Email is valid.'];
}

/**
 * Validate password
 * @param string $password Password to validate
 * @return array Result with valid status and message
 */
function validatePassword($password)
{
    if (empty($password)) {
        return ['valid' => false, 'message' => 'Password is required.'];
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['valid' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'];
    }

    if (strlen($password) > 255) {
        return ['valid' => false, 'message' => 'Password is too long.'];
    }

    // Optional: Check for password strength
    $has_lowercase = preg_match('/[a-z]/', $password);
    $has_uppercase = preg_match('/[A-Z]/', $password);
    $has_number = preg_match('/\d/', $password);
    $has_special = preg_match('/[^a-zA-Z\d]/', $password);

    $strength_score = $has_lowercase + $has_uppercase + $has_number + $has_special;

    return ['valid' => true, 'message' => 'Password is valid.', 'strength' => $strength_score];
}

/**
 * Validate password confirmation
 * @param string $password Original password
 * @param string $confirm_password Confirmation password
 * @return array Result with valid status and message
 */
function validatePasswordConfirmation($password, $confirm_password)
{
    if (empty($confirm_password)) {
        return ['valid' => false, 'message' => 'Password confirmation is required.'];
    }

    if ($password !== $confirm_password) {
        return ['valid' => false, 'message' => 'Passwords do not match.'];
    }

    return ['valid' => true, 'message' => 'Password confirmation is valid.'];
}

/**
 * Validate amount (for transactions)
 * @param mixed $amount Amount to validate
 * @param float $min_amount Minimum allowed amount
 * @param float $max_amount Maximum allowed amount
 * @return array Result with valid status and message
 */
function validateAmount($amount, $min_amount = 0.01, $max_amount = 999999.99)
{
    if (empty($amount) && $amount !== '0') {
        return ['valid' => false, 'message' => 'Amount is required.'];
    }

    if (!is_numeric($amount)) {
        return ['valid' => false, 'message' => 'Amount must be a valid number.'];
    }

    $amount = floatval($amount);

    if ($amount < $min_amount) {
        return ['valid' => false, 'message' => 'Amount must be at least ' . formatCurrency($min_amount) . '.'];
    }

    if ($amount > $max_amount) {
        return ['valid' => false, 'message' => 'Amount cannot exceed ' . formatCurrency($max_amount) . '.'];
    }

    // Check decimal places
    if (round($amount, DECIMAL_PLACES) != $amount) {
        return ['valid' => false, 'message' => 'Amount can have at most ' . DECIMAL_PLACES . ' decimal places.'];
    }

    return ['valid' => true, 'message' => 'Amount is valid.'];
}

/**
 * Validate USDT wallet address
 * @param string $address USDT wallet address
 * @return array Result with valid status and message
 */
function validateUSDTAddress($address)
{
    if (empty($address)) {
        return ['valid' => false, 'message' => 'USDT wallet address is required.'];
    }

    // Basic USDT address validation (starts with T for TRON, length check)
    if (strlen($address) < 25 || strlen($address) > 50) {
        return ['valid' => false, 'message' => 'Invalid USDT wallet address length.'];
    }

    // More specific validation for TRON addresses (TRC20 USDT)
    if (!preg_match('/^T[A-Za-z0-9]{33}$/', $address)) {
        return ['valid' => false, 'message' => 'Invalid USDT wallet address format.'];
    }

    return ['valid' => true, 'message' => 'USDT wallet address is valid.'];
}

/**
 * Validate form data against rules
 * @param array $data Form data
 * @param array $rules Validation rules
 * @return array Result with valid status and errors
 */
function validateFormData($data, $rules)
{
    $errors = [];
    $valid = true;

    foreach ($rules as $field => $field_rules) {
        $value = $data[$field] ?? '';

        foreach ($field_rules as $rule => $params) {
            switch ($rule) {
                case 'required':
                    if ($params && empty($value)) {
                        $errors[$field] = ucfirst($field) . ' is required.';
                        $valid = false;
                    }
                    break;

                case 'min_length':
                    if (!empty($value) && strlen($value) < $params) {
                        $errors[$field] = ucfirst($field) . ' must be at least ' . $params . ' characters long.';
                        $valid = false;
                    }
                    break;

                case 'max_length':
                    if (!empty($value) && strlen($value) > $params) {
                        $errors[$field] = ucfirst($field) . ' must not exceed ' . $params . ' characters.';
                        $valid = false;
                    }
                    break;

                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = 'Please enter a valid email address.';
                        $valid = false;
                    }
                    break;

                case 'numeric':
                    if (!empty($value) && !is_numeric($value)) {
                        $errors[$field] = ucfirst($field) . ' must be a valid number.';
                        $valid = false;
                    }
                    break;

                case 'min_value':
                    if (!empty($value) && is_numeric($value) && floatval($value) < $params) {
                        $errors[$field] = ucfirst($field) . ' must be at least ' . $params . '.';
                        $valid = false;
                    }
                    break;

                case 'max_value':
                    if (!empty($value) && is_numeric($value) && floatval($value) > $params) {
                        $errors[$field] = ucfirst($field) . ' cannot exceed ' . $params . '.';
                        $valid = false;
                    }
                    break;

                case 'regex':
                    if (!empty($value) && !preg_match($params, $value)) {
                        $errors[$field] = ucfirst($field) . ' format is invalid.';
                        $valid = false;
                    }
                    break;
            }
        }
    }

    return ['valid' => $valid, 'errors' => $errors];
}

/**
 * Sanitize and validate registration data
 * @param array $data Registration form data
 * @return array Result with valid status, cleaned data, and errors
 */
function validateRegistrationData($data)
{
    $cleaned_data = [];
    $errors = [];

    // Sanitize inputs
    $cleaned_data['first_name'] = sanitizeInput($data['first_name'] ?? '');
    $cleaned_data['middle_name'] = sanitizeInput($data['middle_name'] ?? '');
    $cleaned_data['last_name'] = sanitizeInput($data['last_name'] ?? '');
    $cleaned_data['username'] = sanitizeInput($data['username'] ?? '');
    $cleaned_data['email'] = sanitizeInput($data['email'] ?? '');
    $cleaned_data['password'] = $data['password'] ?? '';
    $cleaned_data['confirm_password'] = $data['confirm_password'] ?? '';
    $cleaned_data['sponsor_name'] = sanitizeInput($data['sponsor_name'] ?? '');

    // Validate first name
    $first_name_validation = validateName($cleaned_data['first_name'], 'First name');
    if (!$first_name_validation['valid']) {
        $errors['first_name'] = $first_name_validation['message'];
    }

    // Validate middle name
    $middle_name_validation = validateName($cleaned_data['middle_name'], 'Middle name');
    if (!$middle_name_validation['valid']) {
        $errors['middle_name'] = $middle_name_validation['message'];
    }

    // Validate last name
    $last_name_validation = validateName($cleaned_data['last_name'], 'Last name');
    if (!$last_name_validation['valid']) {
        $errors['last_name'] = $last_name_validation['message'];
    }

    // Validate username
    $username_validation = validateUsername($cleaned_data['username']);
    if (!$username_validation['valid']) {
        $errors['username'] = $username_validation['message'];
    }

    // Validate email
    $email_validation = validateEmail($cleaned_data['email']);
    if (!$email_validation['valid']) {
        $errors['email'] = $email_validation['message'];
    }

    // Validate password
    $password_validation = validatePassword($cleaned_data['password']);
    if (!$password_validation['valid']) {
        $errors['password'] = $password_validation['message'];
    }

    // Validate password confirmation
    $confirm_validation = validatePasswordConfirmation($cleaned_data['password'], $cleaned_data['confirm_password']);
    if (!$confirm_validation['valid']) {
        $errors['confirm_password'] = $confirm_validation['message'];
    }

    // Validate sponsor (optional)
    if (!empty($cleaned_data['sponsor_name'])) {
        $sponsor_validation = validateUsername($cleaned_data['sponsor_name']);
        if (!$sponsor_validation['valid']) {
            $errors['sponsor_name'] = 'Invalid sponsor username format.';
        }
    }

    return [
        'valid' => empty($errors),
        'data' => $cleaned_data,
        'errors' => $errors
    ];
}

/**
 * Sanitize and validate login data
 * @param array $data Login form data
 * @return array Result with valid status, cleaned data, and errors
 */
function validateLoginData($data)
{
    $cleaned_data = [];
    $errors = [];

    // Sanitize inputs
    $cleaned_data['username'] = sanitizeInput($data['username'] ?? '');
    $cleaned_data['password'] = $data['password'] ?? '';

    // Validate username/email
    if (empty($cleaned_data['username'])) {
        $errors['username'] = 'Username or email is required.';
    }

    // Validate password
    if (empty($cleaned_data['password'])) {
        $errors['password'] = 'Password is required.';
    }

    return [
        'valid' => empty($errors),
        'data' => $cleaned_data,
        'errors' => $errors
    ];
}
?>