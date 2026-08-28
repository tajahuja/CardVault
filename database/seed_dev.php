<?php
/**
 * Developer Local Account Seed Script
 * ONLY executes if connected to 'cardvault_dev' database.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via command line (CLI).\n");
}

echo "=== CardVault Developer Account Seeder ===\n";

$pdo = require dirname(__DIR__) . '/includes/db.php';

try {
    // 1. Enforce strict database guard
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if ($dbName !== 'cardvault_dev') {
        die("FATAL ERROR: Seeder refused to run. Active database is '{$dbName}', but must be 'cardvault_dev'.\n");
    }

    echo "Verified database environment: cardvault_dev\n";

    // Seed configuration
    $email = 'dev@cardvault.local';
    $password = 'CardVaultDev123!';
    $name = 'Developer Local';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 2. Check if user already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->execute(['email' => $email]);
    $userId = $stmtCheck->fetchColumn();

    if ($userId) {
        // Update user to ensure credentials are synced
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET name = :name, password_hash = :hash, subscription_tier = 'Pro', subscription_status = 'Active' 
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            'name' => $name,
            'hash' => $passwordHash,
            'id' => $userId
        ]);
        echo "[+] Existing developer account updated: {$email} / {$password}\n";
    } else {
        // Insert new developer account
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, subscription_tier, subscription_status, monthly_scan_count) 
            VALUES (:name, :email, :hash, 'Pro', 'Active', 0)
        ");
        $stmtInsert->execute([
            'name' => $name,
            'email' => $email,
            'hash' => $passwordHash
        ]);
        echo "[+] New developer account seeded: {$email} / {$password}\n";
    }

    echo "Seed completed successfully!\n";
} catch (Exception $e) {
    die("FATAL ERROR: " . $e->getMessage() . "\n");
}
