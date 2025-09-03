<?php
/**
 * TokenMining — One-Click Full Reset (reads from reset.sql file)
 * CLI:  php reset.php
 * Web:  https://yoursite.com/reset.php?key=RESET_KEY
 */

/* ---------- CONFIG ---------- */
$DB_HOST   = 'localhost';
$DB_USER   = 'root';
$DB_PASS   = '';
$DB_NAME   = 'jojo1_db';
$RESET_KEY = 'secret123';       // change in prod!
$SQL_FILE  = 'sql/reset.sql';

// Configuration: Set to true for hardcoded password, false for random
$useHardcodedPassword = true;

/* ---------- GUARD (web only) ---------- */
if (php_sapi_name() !== 'cli' && (!isset($_GET['key']) || $_GET['key'] !== $RESET_KEY)) {
    http_response_code(403);
    exit('Forbidden');
}

/* ---------- GENERATE ADMIN PASSWORD ---------- */
if ($useHardcodedPassword) {
    $adminPlain = 'admin123'; // Hardcoded password for development/testing
    echo php_sapi_name() === 'cli' ? "Using hardcoded password for development...\n" : "<pre>Using hardcoded password for development...</pre>";
} else {
    $adminPlain = bin2hex(random_bytes(6)); // 12-char random hex string
    echo php_sapi_name() === 'cli' ? "Generated random password...\n" : "<pre>Generated random password...</pre>";
}

$adminHash = password_hash($adminPlain, PASSWORD_DEFAULT);

/* ---------- CONNECT ---------- */
try {
    $pdo = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("❌ DB connection failed: " . $e->getMessage());
}

/* ---------- READ SQL FILE ---------- */
if (!file_exists($SQL_FILE)) {
    exit("❌ SQL file not found: {$SQL_FILE}");
}

$sql = file_get_contents($SQL_FILE);
if ($sql === false) {
    exit("❌ Failed to read SQL file: {$SQL_FILE}");
}

echo php_sapi_name() === 'cli' ? "Reading clean schema from {$SQL_FILE}...\n" : "<pre>Reading clean schema from {$SQL_FILE}...</pre>";

/* ---------- EXECUTE SCHEMA ---------- */
try {
    // Execute the SQL schema from file
    echo php_sapi_name() === 'cli' ? "Executing database schema...\n" : "<pre>Executing database schema...</pre>";
    $pdo->exec($sql);
    echo php_sapi_name() === 'cli' ? "Schema created successfully!\n" : "<pre>Schema created successfully!</pre>";
    
    // Switch to the new database
    $pdo->exec("USE `{$DB_NAME}`");
    
    // Create admin user programmatically
    echo php_sapi_name() === 'cli' ? "Creating admin user...\n" : "<pre>Creating admin user...</pre>";
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@ojotokenmining.com', $adminHash, 'admin']);
    
    $adminId = $pdo->lastInsertId();
    echo php_sapi_name() === 'cli' ? "Admin user created with ID: {$adminId}\n" : "<pre>Admin user created with ID: {$adminId}</pre>";
    
    // Create ewallet for admin user
    echo php_sapi_name() === 'cli' ? "Creating admin ewallet...\n" : "<pre>Creating admin ewallet...</pre>";
    $stmt = $pdo->prepare("INSERT INTO ewallet (user_id, balance) VALUES (?, ?)");
    $stmt->execute([$adminId, 0.00]);
    
    $msg = "✅ TokenMining reset complete\n   Admin: admin / {$adminPlain}\n   DB: $DB_NAME\n   SQL: $SQL_FILE";
    
} catch (PDOException $e) {
    $msg = "❌ " . $e->getMessage();
    
    // Log detailed error for debugging
    if (php_sapi_name() === 'cli') {
        echo "Full error details:\n";
        echo "Error Code: " . $e->getCode() . "\n";
        echo "Error Message: " . $e->getMessage() . "\n";
        echo "SQL File: " . $SQL_FILE . "\n";
    }
}

/* ---------- OUTPUT ---------- */
if (php_sapi_name() === 'cli') {
    echo $msg . PHP_EOL;
} else {
    echo nl2br(htmlspecialchars($msg));
}

/* ---------- VERIFICATION ---------- */
if (php_sapi_name() === 'cli' && isset($pdo) && !isset($e)) {
    echo "\n=== DATABASE RESET SUCCESSFUL ===\n";
    echo "Admin username: admin\n";
    echo "Admin password: {$adminPlain}\n";
    echo "You can now log in with these credentials.\n";
    
    // Verify everything was created successfully
    try {
        // Check admin user
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = 'admin'");
        $stmt->execute();
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "✓ Admin user verified (ID: {$user['id']})\n";
            
            // Check ewallet
            $stmt = $pdo->prepare("SELECT balance FROM ewallet WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            if ($wallet = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "✓ Admin ewallet verified (Balance: $" . number_format($wallet['balance'], 2) . ")\n";
            } else {
                echo "⚠ Warning: Admin ewallet not found\n";
            }
            
            // Test password
            if (password_verify($adminPlain, $adminHash)) {
                echo "✓ Password verification successful\n";
            } else {
                echo "⚠ Warning: Password verification failed\n";
            }
            
            // Check packages
            $stmt = $pdo->query("SELECT COUNT(*) FROM packages");
            $packageCount = $stmt->fetchColumn();
            echo "✓ Packages loaded: $packageCount\n";
            
            // Check admin settings
            $stmt = $pdo->query("SELECT COUNT(*) FROM admin_settings");
            $settingsCount = $stmt->fetchColumn();
            echo "✓ Admin settings loaded: $settingsCount\n";
            
        } else {
            echo "⚠ Warning: Admin user not found\n";
        }
        
    } catch (PDOException $e) {
        echo "Note: Could not verify setup: " . $e->getMessage() . "\n";
    }
    
    echo "=== READY TO USE ===\n";
}

/* ---------- ADDITIONAL HELPER FUNCTIONS ---------- */

/**
 * Verify database structure after reset
 */
function verifyDatabaseStructure($pdo, $dbName) {
    try {
        $pdo->exec("USE `{$dbName}`");
        
        // Check if key tables exist
        $tables = ['users', 'packages', 'user_packages', 'ewallet', 'admin_settings'];
        $existingTables = [];
        
        foreach ($tables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($result->rowCount() > 0) {
                $existingTables[] = $table;
            }
        }
        
        return $existingTables;
    } catch (PDOException $e) {
        return false;
    }
}
?>