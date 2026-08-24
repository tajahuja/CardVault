<?php
/**
 * API - Contacts List & CSV Export Endpoint
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Fetch all contacts belonging to the user
        $stmt = $pdo->prepare("
            SELECT 
                full_name, first_name, last_name, job_title, company, 
                phone, alternate_phone, email, alternate_email, 
                website, linkedin_url, address, city, state, country, postal_code, 
                date_met, place_met, follow_up_date, status, source, created_at 
            FROM contacts 
            WHERE user_id = :user_id 
            ORDER BY full_name ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Define CSV headers
        $headers = [
            'Full Name', 'First Name', 'Last Name', 'Job Title', 'Company',
            'Phone', 'Alternate Phone', 'Email', 'Alternate Email',
            'Website', 'LinkedIn URL', 'Address', 'City', 'State', 'Country', 'Postal Code',
            'Date Met', 'Place Met', 'Follow-up Date', 'Status', 'Source', 'Created At'
        ];
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cardvault_contacts_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open file pointer to output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compliance
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write CSV column headers
        fputcsv($output, $headers);
        
        // Write contact data rows
        foreach ($contacts as $contact) {
            fputcsv($output, $contact);
        }
        
        fclose($output);
        exit;
        
    } catch (\PDOException $e) {
        error_log("CSV Export DB Error: " . $e->getMessage());
        die("An error occurred while generating the CSV export: " . htmlspecialchars($e->getMessage()));
    }
}

// Otherwise, if someone visits this endpoint, return JSON contacts list
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->prepare("SELECT id, full_name, company, email, phone, status FROM contacts WHERE user_id = :user_id ORDER BY full_name ASC");
    $stmt->execute(['user_id' => $userId]);
    $contacts = $stmt->fetchAll();
    
    json_response(true, 'Contacts retrieved successfully.', ['contacts' => $contacts]);
} catch (\PDOException $e) {
    error_log("Get Contacts API Error: " . $e->getMessage());
    json_response(false, 'Unable to retrieve contacts.', [], 500);
}
