<?php
/**
 * API - Guest Contact Exchange Submission Handler
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require dirname(__DIR__) . '/includes/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate Guest CSRF Token
validate_csrf();

$slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
$fullName = isset($_POST['full_name']) ? trim(strip_tags($_POST['full_name'])) : '';
$company = isset($_POST['company']) ? trim(strip_tags($_POST['company'])) : '';
$designation = isset($_POST['designation']) ? trim(strip_tags($_POST['designation'])) : '';
$phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
$notes = isset($_POST['notes']) ? trim(strip_tags($_POST['notes'])) : '';

// Validation
if (empty($slug) || empty($fullName) || empty($phone) || empty($email)) {
    json_response(false, 'Please fill in all required fields.', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please provide a valid email address.', [], 400);
}

// Get Client IP Address
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

try {
    // 1. Resolve Profile Owner
    $stmt = $pdo->prepare("SELECT user_id FROM user_profiles WHERE slug = :slug LIMIT 1");
    $stmt->execute(['slug' => $slug]);
    $ownerId = $stmt->fetchColumn();

    if (!$ownerId) {
        json_response(false, 'Target profile owner not found.', [], 404);
    }

    // 2. IP Rate Limiting (Max 3 submissions per 15 minutes per IP)
    $cleanTime = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    // Clean old logs to save database space
    $stmtDel = $pdo->prepare("DELETE FROM guest_rate_limits WHERE request_time < :clean_time");
    $stmtDel->execute(['clean_time' => $cleanTime]);

    // Check count for current IP
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM guest_rate_limits WHERE ip_address = :ip AND request_time >= :clean_time");
    $stmtCount->execute(['ip' => $ip, 'clean_time' => $cleanTime]);
    $requestCount = $stmtCount->fetchColumn();

    if ($requestCount >= 3) {
        json_response(false, 'You have reached the submission limit. Please try again in 15 minutes.', [], 429);
    }

    // Register this request
    $stmtLimit = $pdo->prepare("INSERT INTO guest_rate_limits (ip_address) VALUES (:ip)");
    $stmtLimit->execute(['ip' => $ip]);

    // Split Name into First/Last
    $parts = explode(' ', $fullName, 2);
    $firstName = $parts[0];
    $lastName = isset($parts[1]) ? $parts[1] : '';

    $pdo->beginTransaction();

    // 3. Duplicate Detection (check by email for the owner)
    $stmtCheck = $pdo->prepare("SELECT id FROM contacts WHERE email = :email AND user_id = :owner_id LIMIT 1");
    $stmtCheck->execute(['email' => $email, 'owner_id' => $ownerId]);
    $contactId = $stmtCheck->fetchColumn();

    if ($contactId) {
        // Contact already exists under this owner - log interaction history instead of duplicate creation
        $logMsg = "Digital Contact Exchange updated: Guest submitted details again. Designation: $designation, Company: $company.";
        
        $stmtLog = $pdo->prepare("
            INSERT INTO interactions (contact_id, user_id, type, description) 
            VALUES (:contact_id, :user_id, 'Scan', :description)
        ");
        $stmtLog->execute([
            'contact_id' => $contactId,
            'user_id' => $ownerId,
            'description' => $logMsg
        ]);

        if (!empty($notes)) {
            $stmtNoteLog = $pdo->prepare("
                INSERT INTO notes (contact_id, user_id, note) 
                VALUES (:contact_id, :user_id, :note)
            ");
            $stmtNoteLog->execute([
                'contact_id' => $contactId,
                'user_id' => $ownerId,
                'note' => "Guest message: " . $notes
            ]);
            
            // Log guest message interaction
            $stmtLog->execute([
                'contact_id' => $contactId,
                'user_id' => $ownerId,
                'description' => "Guest Note Added: $notes"
            ]);
        }
    } else {
        // Create new contact
        $sql = "INSERT INTO contacts (
                    user_id, first_name, last_name, full_name, job_title, company, 
                    phone, email, lead_source, source, status
                ) VALUES (
                    :user_id, :first_name, :last_name, :full_name, :job_title, :company, 
                    :phone, :email, 'Digital Business Card', 'Imported', 'New'
                )";
        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute([
            'user_id' => $ownerId,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'full_name' => $fullName !== '' ? $fullName : null,
            'job_title' => $designation !== '' ? $designation : null,
            'company' => $company !== '' ? $company : null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null
        ]);

        $contactId = $pdo->lastInsertId();

        // Log Exchange interaction
        $stmtLog = $pdo->prepare("
            INSERT INTO interactions (contact_id, user_id, type, description) 
            VALUES (:contact_id, :user_id, 'Scan', 'Digital Contact Exchange completed via public profile.')
        ");
        $stmtLog->execute([
            'contact_id' => $contactId,
            'user_id' => $ownerId
        ]);

        // Insert Guest note
        if (!empty($notes)) {
            $stmtNote = $pdo->prepare("
                INSERT INTO notes (contact_id, user_id, note) 
                VALUES (:contact_id, :user_id, :note)
            ");
            $stmtNote->execute([
                'contact_id' => $contactId,
                'user_id' => $ownerId,
                'note' => $notes
            ]);

            // Log Note interaction
            $stmtLogNote = $pdo->prepare("
                INSERT INTO interactions (contact_id, user_id, type, description) 
                VALUES (:contact_id, :user_id, 'Note', :description)
            ");
            $stmtLogNote->execute([
                'contact_id' => $contactId,
                'user_id' => $ownerId,
                'description' => "Added guest meeting note: $notes"
            ]);
        }
    }

    $pdo->commit();
    json_response(true, 'Contact details shared successfully.', ['contact_id' => $contactId], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("DBC Guest Exchange Error: " . $e->getMessage());
    json_response(false, 'An error occurred while saving details. Please try again.', [], 500);
}
