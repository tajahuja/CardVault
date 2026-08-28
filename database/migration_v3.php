<?php
/**
 * Migration v3 - Digital Business Cards Table Setup
 */

$pdo = require dirname(__DIR__) . '/includes/db.php';

try {
    echo "Running Migration v3...\n";
    
    // Create user_profiles table
    $sqlProfiles = "
        CREATE TABLE IF NOT EXISTS `user_profiles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `slug` VARCHAR(100) NOT NULL,
            `full_name` VARCHAR(255) NOT NULL,
            `designation` VARCHAR(150) DEFAULT NULL,
            `company` VARCHAR(150) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `website` VARCHAR(255) DEFAULT NULL,
            `linkedin_url` VARCHAR(255) DEFAULT NULL,
            `bio` TEXT DEFAULT NULL,
            `profile_photo` VARCHAR(255) DEFAULT NULL,
            `public_fields_json` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            UNIQUE KEY `uq_profile_slug` (`slug`),
            UNIQUE KEY `uq_user_profile` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlProfiles);
    echo "[+] Table 'user_profiles' verified/created.\n";
    
    // Create guest_rate_limits table
    $sqlLimits = "
        CREATE TABLE IF NOT EXISTS `guest_rate_limits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `request_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_ip_time` (`ip_address`, `request_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sqlLimits);
    echo "[+] Table 'guest_rate_limits' verified/created.\n";
    
    echo "Migration v3 completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration v3 failed: " . $e->getMessage() . "\n";
    exit(1);
}
