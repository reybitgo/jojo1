-- Active: 1755594857749@@127.0.0.1@3306@jojo1_db
-- sql/database_schema.sql
-- OjoTokenMining Database Schema - Corrected Version
-- This version avoids duplicate column errors and properly structures all tables

DROP DATABASE IF EXISTS jojo1_db;

CREATE DATABASE IF NOT EXISTS jojo1_db;

USE jojo1_db;

-- ==========================================
-- CORE TABLES
-- ==========================================

-- Users table (with all fields included from start)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    password VARCHAR(255) NOT NULL,
    sponsor_id INT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM(
        'active',
        'inactive',
        'suspended'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES users (id) ON DELETE SET NULL
);

-- Packages table (with all fields included from start)
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    description TEXT NULL,
    features TEXT NULL,
    order_index INT DEFAULT 0,
    image_path VARCHAR(255) NULL,
    referral_bonus_enabled TINYINT(1) DEFAULT 1,
    mode ENUM('monthly', 'daily') DEFAULT 'monthly',
    daily_percentage DECIMAL(5, 2) DEFAULT 0.00,
    target_value DECIMAL(15, 2) DEFAULT 0.00,
    maturity_period INT DEFAULT 90,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User packages (purchases) - with all fields included from start
CREATE TABLE user_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_bonus_date DATETIME NULL,
    current_cycle INT DEFAULT 1,
    total_cycles INT DEFAULT 3,
    status ENUM(
        'active',
        'completed',
        'withdrawn'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_package (package_id)
);

-- Ewallet system
CREATE TABLE ewallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Ewallet transactions (with all fields included from start)
CREATE TABLE ewallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM(
        'deposit',
        'withdrawal',
        'withdrawal_charge',
        'bonus',
        'retain',
        'referral',
        'purchase',
        'refund',
        'transfer',
        'transfer_charge',
        'leadership'
    ) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description TEXT,
    status ENUM(
        'pending',
        'completed',
        'failed'
    ) DEFAULT 'completed',
    reference_id INT NULL,
    is_withdrawable TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- Withdrawal requests (with all fields included from start)
CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    usdt_amount DECIMAL(15, 8) NOT NULL,
    wallet_address VARCHAR(255) NOT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'completed'
    ) DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Refill requests (with all fields included from start)
CREATE TABLE refill_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_hash VARCHAR(255) NULL,
    network ENUM('trc20', 'bep20') NOT NULL DEFAULT 'trc20',
    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Monthly bonuses tracking
CREATE TABLE monthly_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    user_package_id INT NOT NULL,
    month_number INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM(
        'pending',
        'paid',
        'withdrawn'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE
);

-- Bonus wallet
CREATE TABLE bonus_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_package_id INT NOT NULL,
    package_id INT NOT NULL,
    cycle TINYINT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
);

-- Referral bonuses
CREATE TABLE referral_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    level INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    percentage DECIMAL(5, 2) NOT NULL,
    package_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
);

-- Leadership passive earnings
CREATE TABLE leadership_passive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT NOT NULL,
    beneficiary_id INT NOT NULL,
    level TINYINT NOT NULL CHECK (level BETWEEN 1 AND 5),
    amount DECIMAL(15, 2) NOT NULL,
    month_cycle DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_beneficiary (beneficiary_id),
    FOREIGN KEY (sponsor_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (beneficiary_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Password resets
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Admin settings
CREATE TABLE admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- SEED DATA
-- ==========================================

-- Insert default packages
INSERT INTO
    packages (
        name,
        price,
        description,
        features,
        order_index
    )
VALUES (
        'Starter Plan',
        20.00,
        'Perfect for beginners',
        '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses',
        1
    ),
    (
        'Bronze Plan',
        100.00,
        'Good starting investment',
        '• 100 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses',
        2
    ),
    (
        'Silver Plan',
        500.00,
        'Balanced investment',
        '• 500 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Advanced features',
        3
    ),
    (
        'Gold Plan',
        1000.00,
        'Premium package',
        '• 1000 USDT\n• 50% monthly bonus\n• 3-month cycle\n• Priority support',
        4
    ),
    (
        'Platinum Plan',
        2000.00,
        'High-value investment',
        '• 2000 USDT\n• 50% monthly bonus\n• 3-month cycle\n• VIP features',
        5
    ),
    (
        'Diamond Plan',
        10000.00,
        'Ultimate package',
        '• 10000 USDT\n• 50% monthly bonus\n• 3-month cycle\n• Exclusive benefits',
        6
    );

-- Insert comprehensive admin settings
INSERT INTO
    admin_settings (
        setting_name,
        setting_value,
        description
    )
VALUES (
        'monthly_bonus_percentage',
        '50',
        'Monthly bonus percentage'
    ),
    (
        'referral_level_2_percentage',
        '10',
        'Level 2 referral bonus percentage'
    ),
    (
        'referral_level_3_percentage',
        '1',
        'Level 3 referral bonus percentage'
    ),
    (
        'referral_level_4_percentage',
        '1',
        'Level 4 referral bonus percentage'
    ),
    (
        'referral_level_5_percentage',
        '1',
        'Level 5 referral bonus percentage'
    ),
    (
        'admin_usdt_wallet',
        'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx',
        'Admin USDT wallet address for refills'
    ),
    (
        'usdt_rate',
        '1.00',
        'USDT conversion rate'
    ),
    (
        'default_currency',
        'USDT',
        'Default currency for the system'
    ),
    (
        'default_sponsor_enabled',
        '1',
        'Enable automatic admin sponsor assignment'
    ),
    (
        'orphan_prevention',
        '1',
        'Prevent orphaned users by assigning default sponsor'
    ),
    (
        'transfer_charge_percentage',
        '5',
        'Transfer fee percentage'
    ),
    (
        'transfer_minimum_amount',
        '1',
        'Minimum transfer amount in USDT'
    ),
    (
        'transfer_maximum_amount',
        '10000',
        'Maximum transfer amount in USDT'
    ),
    (
        'leadership_enabled',
        '1',
        'Enable Leadership Passive earnings'
    ),
    (
        'direct_package_quota',
        '1000',
        'Minimum total USDT of 1st-level packages'
    ),
    (
        'min_direct_count',
        '3',
        'Minimum direct referrals that must own a package'
    ),
    (
        'leadership_levels',
        '5',
        'Maximum depth to pay Leadership Passive'
    ),
    (
        'leadership_level_1_percentage',
        '10',
        '% of monthly bonus paid to sponsor (level-1)'
    ),
    (
        'leadership_level_2_percentage',
        '5',
        '% of monthly bonus paid to grand-sponsor (level-2)'
    ),
    (
        'leadership_level_3_percentage',
        '3',
        '% of monthly bonus paid to great-grand-sponsor (level-3)'
    ),
    (
        'leadership_level_4_percentage',
        '2',
        '% of monthly bonus paid to sponsor of level-3 (level-4)'
    ),
    (
        'leadership_level_5_percentage',
        '1',
        '% of monthly bonus paid to sponsor of level-4 (level-5)'
    );

-- Create default admin user (password: admin123)
INSERT INTO
    users (
        username,
        email,
        password,
        role
    )
VALUES (
        'admin',
        'admin@ojotokenmining.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin'
    );

-- Create ewallet for admin user
INSERT INTO ewallet (user_id, balance) VALUES (1, 0.00);

-- 1. Remove the old / any existing column
-- ALTER TABLE withdrawal_requests DROP COLUMN IF EXISTS method;

-- 2. Add the correct column in the right spot
ALTER TABLE withdrawal_requests
    ADD COLUMN method ENUM('usdt_bep20','jojo_token') DEFAULT 'jojo_token' AFTER usdt_amount;

        -- Add these columns to your users table
ALTER TABLE users ADD COLUMN address_line_1 VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN address_line_2 VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN state_province VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN postal_code VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN country VARCHAR(100) NULL;

-- ==========================================
-- COMPLETION MESSAGE
-- ==========================================
SELECT
    '✅ TokenMining database schema created successfully!' AS status,
    'Default admin user: admin / admin123' AS credentials,
    'Database: jojo1_db' AS database_name;