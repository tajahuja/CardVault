<?php
/**
 * Migration v4 - Companies, Opportunities, and Events Setup
 */

$pdo = require dirname(__DIR__) . '/includes/db.php';

try {
    echo "Running Migration v4...\n";

    // 1. Create companies table
    $sqlCompanies = "
        CREATE TABLE IF NOT EXISTS `companies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `industry` VARCHAR(100) DEFAULT NULL,
            `website` VARCHAR(255) DEFAULT NULL,
            `location` VARCHAR(255) DEFAULT NULL,
            `lead_source` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlCompanies);
    echo "[+] Table 'companies' verified/created.\n";

    // 2. Add company_id column to contacts if not exists
    $stmtCol = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'contacts' 
          AND COLUMN_NAME = 'company_id'
    ");
    $stmtCol->execute();
    if (!$stmtCol->fetchColumn()) {
        $pdo->exec("ALTER TABLE `contacts` ADD COLUMN `company_id` INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE `contacts` ADD CONSTRAINT `fk_contact_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL");
        echo "[+] Column 'company_id' and index constraint added to 'contacts'.\n";
    } else {
        echo "[+] Column 'company_id' already exists in 'contacts'.\n";
    }

    // 3. Create opportunities table
    $sqlOpportunities = "
        CREATE TABLE IF NOT EXISTS `opportunities` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `contact_id` INT NOT NULL,
            `company_id` INT DEFAULT NULL,
            `name` VARCHAR(150) NOT NULL,
            `stage` ENUM('New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost') DEFAULT 'New Lead',
            `value` DECIMAL(15,2) DEFAULT 0.00,
            `probability` INT DEFAULT 0,
            `expected_close_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlOpportunities);
    echo "[+] Table 'opportunities' verified/created.\n";

    // 4. Create events table
    $sqlEvents = "
        CREATE TABLE IF NOT EXISTS `events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `type` ENUM('Trade Show', 'Conference', 'Meeting', 'Networking Event', 'Exhibition', 'Travel', 'Client Visit', 'Other') DEFAULT 'Meeting',
            `date` DATE NOT NULL,
            `location` VARCHAR(255) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlEvents);
    echo "[+] Table 'events' verified/created.\n";

    // 5. Create event_contacts table (Junction table)
    $sqlEventContacts = "
        CREATE TABLE IF NOT EXISTS `event_contacts` (
            `event_id` INT NOT NULL,
            `contact_id` INT NOT NULL,
            PRIMARY KEY (`event_id`, `contact_id`),
            FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlEventContacts);
    echo "[+] Table 'event_contacts' verified/created.\n";

    echo "Migration v4 completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration v4 failed: " . $e->getMessage() . "\n";
    exit(1);
}
