-- ===================================================
-- OjoTokenMining - Complete Database Reset (Updated with Address Fields)
-- Includes all tables from Phase 1-6 plus address fields
-- Admin user will be created programmatically by reset.php
-- ===================================================

-- Drop database if exists
DROP DATABASE IF EXISTS jojo1_db;

-- Create database
CREATE DATABASE IF NOT EXISTS jojo1_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE jojo1_db;

-- ===================================================
-- CORE TABLES
-- ===================================================

-- Users table (with all fields including address fields)
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
    address_line_1 VARCHAR(255) NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state_province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NULL,
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
    description TEXT DEFAULT NULL,
    features TEXT DEFAULT NULL,
    order_index INT(11) DEFAULT 0,
    image_path VARCHAR(255) DEFAULT NULL,
    referral_bonus_enabled TINYINT(1) DEFAULT 1,
    mode ENUM('monthly', 'daily') DEFAULT 'monthly',
    daily_percentage DECIMAL(5, 2) DEFAULT 0.00,
    target_value DECIMAL(15, 2) DEFAULT 0.00,
    maturity_period INT(11) DEFAULT 90,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- User packages (with all fields included from start)
CREATE TABLE user_packages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    purchase_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    next_bonus_date DATETIME DEFAULT NULL,
    current_cycle INT DEFAULT 1,
    total_cycles INT DEFAULT 3,
    status ENUM(
        'active',
        'completed',
        'withdrawn'
    ) DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_package (package_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================
-- E-WALLET SYSTEM
-- ===================================================

CREATE TABLE ewallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE ewallet_transactions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
    reference_id INT DEFAULT NULL,
    is_withdrawable TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ===================================================
-- REQUESTS SYSTEM
-- ===================================================

CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    usdt_amount DECIMAL(15,8) NOT NULL,
    method ENUM('usdt_bep20','jojo_token') DEFAULT 'usdt_bep20',
    wallet_address VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
    processed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ===================================================
-- BONUS SYSTEM
-- ===================================================

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
    ) DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE
);

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

-- ===================================================
-- ADMIN SYSTEM
-- ===================================================

CREATE TABLE admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

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

-- ===================================================
-- SEED DATA
-- ===================================================

-- Insert default packages (with corrected UTF-8 characters)
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
        'Perfect for beginners to start earning',
        '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses',
        1
    ),
    (
        'Bronze Plan',
        100.00,
        'Good starting investment',
        '• 100 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Multi-level referrals',
        2
    ),
    (
        'Silver Plan',
        500.00,
        'Balanced investment option',
        '• 500 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Advanced features',
        3
    ),
    (
        'Gold Plan',
        1000.00,
        'Premium investment package',
        '• 1000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Priority support',
        4
    ),
    (
        'Platinum Plan',
        2000.00,
        'High-value investment',
        '• 2000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• VIP features',
        5
    ),
    (
        'Diamond Plan',
        10000.00,
        'Ultimate investment',
        '• 10000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Exclusive benefits',
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
        'TAdminUSDTWalletAddressHere12345',
        'Admin USDT wallet address (TRC20)'
    ),
    (
        'admin_usdt_wallet_bep20',
        '0xAdminUSDTBEP20WalletAddressHere12345',
        'Admin USDT wallet address (BEP20/BSC)'
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

-- ===================================================
-- COMPLETION MESSAGE
-- ===================================================
SELECT
    'Database schema created successfully' AS status,
    'Admin user will be created by reset.php' AS note,
    'Database: jojo1_db' AS database_name;