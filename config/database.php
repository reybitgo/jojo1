<?php
// Database Configuration
// config/database.php

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_NAME', 'jojo1_db');
define('DB_USER', 'root');
define('DB_PASS', ''); //sWY|eP?D6a //o^t@1sU$1
define('DB_CHARSET', 'utf8mb4');

// Global database connection variable
$pdo = null;

/**
 * Get database connection
 * @return PDO Database connection object
 */
function getConnection()
{
    global $pdo;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}

/**
 * Test database connection
 * @return bool True if connection successful
 */
function testConnection()
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Close database connection
 */
function closeConnection()
{
    global $pdo;
    $pdo = null;
}

// Initialize connection on include
$pdo = getConnection();
?>