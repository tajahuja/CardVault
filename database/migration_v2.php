<?php
/**
 * CardVault Database Migration - Phase 1: Follow-up Center (Optimized Indexing)
 * Safely creates/upgrades the follow_ups table and index.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run via command line (CLI).\n");
}

echo "=== CardVault Database Migration: Phase 1 (Index Optimization) ===\n";

$pdo = require_once __DIR__ . '/../includes/db.php';

try {
    echo "Creating/verifying 'follow_ups' table...\n";
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
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'follow_ups' is verified.\n";

    // Safely check columns of idx_user_follow_ups
    $stmtIdx = $pdo->prepare("
        SELECT seq_in_index, column_name 
        FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
          AND table_name = 'follow_ups' 
          AND index_name = 'idx_user_follow_ups'
        ORDER BY seq_in_index ASC
    ");
    $stmtIdx->execute();
    $existingIndexColumns = $stmtIdx->fetchAll();

    $recreateIndex = true;
    if (!empty($existingIndexColumns)) {
        $cols = array_map(function($row) { return $row['column_name']; }, $existingIndexColumns);
        if ($cols === ['user_id', 'status', 'follow_up_date']) {
            echo "✓ Optimized index 'idx_user_follow_ups' already exists. Skipping recreation.\n";
            $recreateIndex = false;
        } else {
            echo "Legacy index found with different column order. Running safe index swap...\n";
            
            // 1. Create temporary index to satisfy foreign key requirement
            $pdo->exec("ALTER TABLE `follow_ups` ADD INDEX `temp_fk_idx` (`user_id`)");
            
            // 2. Drop the old composite index
            $pdo->exec("ALTER TABLE `follow_ups` DROP INDEX `idx_user_follow_ups`");
            
            // 3. Create the optimized index
            $pdo->exec("ALTER TABLE `follow_ups` ADD INDEX `idx_user_follow_ups` (`user_id`, `status`, `follow_up_date`)");
            
            // 4. Drop the temporary index
            $pdo->exec("ALTER TABLE `follow_ups` DROP INDEX `temp_fk_idx`");
            
            echo "✓ Optimized index applied via safe swap.\n";
            $recreateIndex = false;
        }
    }

    if ($recreateIndex) {
        echo "Creating composite index 'idx_user_follow_ups' (user_id, status, follow_up_date)...\n";
        $pdo->exec("
            ALTER TABLE `follow_ups` 
            ADD INDEX `idx_user_follow_ups` (`user_id`, `status`, `follow_up_date`)
        ");
        echo "✓ Optimized index created successfully.\n";
    }

    echo "=== Migration Phase 1 completed successfully! ===\n";

} catch (\PDOException $e) {
    die("FATAL DB ERROR: " . $e->getMessage() . "\n");
}
