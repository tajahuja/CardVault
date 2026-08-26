<?php
/**
 * CardVault Database Migration & Upgrades
 * Updates the schema safely for cardvault_dev without losing data.
 */

// We simulate a CLI run environment, bypass session check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script can only be run via command line (CLI).\n");
}

echo "=== CardVault Database Migrations ===\n";

// Disable error redirection temporarily to get clean CLI output
$pdo = require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Create interactions timeline table
    echo "Checking 'interactions' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `interactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `contact_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `type` ENUM('Scan', 'Note', 'Call', 'WhatsApp', 'Email', 'Meeting', 'Follow-up', 'Status Change') NOT NULL,
            `description` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            INDEX `idx_contact_interactions` (`contact_id`),
            INDEX `idx_user_interactions` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ 'interactions' table is verified.\n";

    // Helper function to check if column exists using information_schema
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :table 
              AND COLUMN_NAME = :column
        ");
        $stmt->execute(['table' => $table, 'column' => $column]);
        return $stmt->fetchColumn() !== false;
    }

    // 2. Add columns to 'contacts' table
    echo "Checking 'contacts' table columns...\n";
    if (!columnExists($pdo, 'contacts', 'industry')) {
        echo "Adding 'industry' to 'contacts'...\n";
        $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `industry` VARCHAR(100) DEFAULT NULL AFTER `postal_code`;");
    }
    if (!columnExists($pdo, 'contacts', 'lead_source')) {
        echo "Adding 'lead_source' to 'contacts'...\n";
        $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `lead_source` VARCHAR(100) DEFAULT NULL AFTER `industry`;");
    }
    echo "✓ 'contacts' columns verified.\n";

    // 3. Add columns to 'users' table
    echo "Checking 'users' table columns...\n";
    if (!columnExists($pdo, 'users', 'subscription_tier')) {
        echo "Adding 'subscription_tier' to 'users'...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `subscription_tier` VARCHAR(50) DEFAULT 'free' AFTER `password_hash`;");
    }
    if (!columnExists($pdo, 'users', 'subscription_status')) {
        echo "Adding 'subscription_status' to 'users'...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `subscription_status` VARCHAR(50) DEFAULT 'inactive' AFTER `subscription_tier`;");
    }
    if (!columnExists($pdo, 'users', 'monthly_scan_count')) {
        echo "Adding 'monthly_scan_count' to 'users'...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `monthly_scan_count` INT DEFAULT 0 AFTER `subscription_status`;");
    }
    if (!columnExists($pdo, 'users', 'scan_reset_date')) {
        echo "Adding 'scan_reset_date' to 'users'...\n";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `scan_reset_date` DATE DEFAULT NULL AFTER `monthly_scan_count`;");
    }
    echo "✓ 'users' columns verified.\n";

    echo "=== Migrations completed successfully! ===\n";

} catch (\PDOException $e) {
    die("FATAL DB ERROR: " . $e->getMessage() . "\n");
}
