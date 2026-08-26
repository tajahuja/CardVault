<?php
/**
 * CardVault Database Migration - Phase 1: Follow-up Center
 * Creates the follow_ups table safely for local development.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via command line (CLI).\n");
}

echo "=== CardVault Database Migration: Phase 1 ===\n";

$pdo = require_once __DIR__ . '/../includes/db.php';

try {
    echo "Creating 'follow_ups' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `follow_ups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `contact_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `follow_up_date` DATE NOT NULL,
            `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            `status` ENUM('Pending', 'Completed', 'Snoozed') DEFAULT 'Pending',
            `notes` TEXT DEFAULT NULL,
            `completed_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            INDEX `idx_user_follow_ups` (`user_id`, `follow_up_date`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ 'follow_ups' table verified successfully.\n";
    echo "=== Migration Phase 1 completed successfully! ===\n";

} catch (\PDOException $e) {
    die("FATAL DB ERROR: " . $e->getMessage() . "\n");
}
