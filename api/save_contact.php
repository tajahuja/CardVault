<?php
/**
 * API - Save Contact (Insert) Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF token
validate_csrf();

$userId = $_SESSION['user_id'];

// Retrieve and sanitize fields
$firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$jobTitle = isset($_POST['job_title']) ? trim($_POST['job_title']) : '';
$company = isset($_POST['company']) ? trim($_POST['company']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$alternatePhone = isset($_POST['alternate_phone']) ? trim($_POST['alternate_phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$alternateEmail = isset($_POST['alternate_email']) ? trim($_POST['alternate_email']) : '';
$website = isset($_POST['website']) ? trim($_POST['website']) : '';
$linkedinUrl = isset($_POST['linkedin_url']) ? trim($_POST['linkedin_url']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';
$country = isset($_POST['country']) ? trim($_POST['country']) : '';
$postalCode = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
$placeMet = isset($_POST['place_met']) ? trim($_POST['place_met']) : '';
$industry = isset($_POST['industry']) ? trim($_POST['industry']) : '';
$leadSource = isset($_POST['lead_source']) ? trim($_POST['lead_source']) : '';

$dateMet = !empty($_POST['date_met']) ? $_POST['date_met'] : null;
$followUpDate = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

$status = isset($_POST['status']) ? $_POST['status'] : 'New';
$source = isset($_POST['source']) ? $_POST['source'] : 'Manual Entry';

// Auto-fill leadSource from source if empty
if (empty($leadSource)) {
    $leadSource = $source;
}

$ocrRawText = isset($_POST['ocr_raw_text']) ? $_POST['ocr_raw_text'] : null;
$originalCardImage = isset($_POST['original_card_image']) ? $_POST['original_card_image'] : null;

// Validation: At least one identifying field must be present
if (empty($fullName) && empty($firstName) && empty($lastName) && empty($email) && empty($phone) && empty($company)) {
    json_response(false, 'Please provide at least a name, email, phone, or company for the contact.', [], 400);
}

// Reconstruct full name if missing
if (empty($fullName) && (!empty($firstName) || !empty($lastName))) {
    $fullName = trim($firstName . ' ' . $lastName);
} elseif (!empty($fullName) && empty($firstName) && empty($lastName)) {
    // Basic split on first space
    $parts = explode(' ', $fullName, 2);
    $firstName = $parts[0];
    $lastName = isset($parts[1]) ? $parts[1] : '';
}

// Validate status enum
$validStatuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Not Interested', 'Archived'];
if (!in_repeat_status($status, $validStatuses)) {
    $status = 'New';
}

// Validate source enum
$validSources = ['Business Card', 'Manual Entry', 'Imported'];
if (!in_array($source, $validSources)) {
    $source = 'Manual Entry';
}

// Helper to validate status value
function in_repeat_status($value, $array) {
    return in_array($value, $array);
}

// Phase 8: Check for possible duplicates (if ignore_duplicate flag is not set)
$ignoreDuplicate = isset($_POST['ignore_duplicate']) && $_POST['ignore_duplicate'] === '1';

if (!$ignoreDuplicate) {
    $duplicateFound = false;
    $dupReason = '';
    $existingContactId = null;

    // 1. Check by email
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE email = :email AND user_id = :user_id LIMIT 1");
        $stmt->execute(['email' => $email, 'user_id' => $userId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "A contact with email '" . $email . "' already exists.";
        }
    }

    // 2. Check by phone if no email match
    if (!$duplicateFound && !empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE phone = :phone AND user_id = :user_id LIMIT 1");
        $stmt->execute(['phone' => $phone, 'user_id' => $userId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "A contact with phone '" . $phone . "' already exists.";
        }
    }

    // 3. Check by Name + Company if no email/phone match
    if (!$duplicateFound && !empty($fullName) && !empty($company)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE full_name = :full_name AND company = :company AND user_id = :user_id LIMIT 1");
        $stmt->execute(['full_name' => $fullName, 'company' => $company, 'user_id' => $userId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "A contact named '" . $fullName . "' at '" . $company . "' already exists.";
        }
    }

    if ($duplicateFound) {
        json_response(false, 'Duplicate detected.', [
            'duplicate' => true,
            'reason' => $dupReason,
            'existing_id' => $existingContactId
        ], 200); // 200 OK because it is a managed validation response
    }
}

try {
    // Insert into database
    $sql = "INSERT INTO contacts (
                user_id, first_name, last_name, full_name, job_title, company, 
                phone, alternate_phone, email, alternate_email, website, linkedin_url, 
                address, city, state, country, postal_code, industry, lead_source, date_met, place_met, 
                follow_up_date, status, original_card_image, source, ocr_raw_text
            ) VALUES (
                :user_id, :first_name, :last_name, :full_name, :job_title, :company, 
                :phone, :alternate_phone, :email, :alternate_email, :website, :linkedin_url, 
                :address, :city, :state, :country, :postal_code, :industry, :lead_source, :date_met, :place_met, 
                :follow_up_date, :status, :original_card_image, :source, :ocr_raw_text
            )";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'first_name' => $firstName !== '' ? $firstName : null,
        'last_name' => $lastName !== '' ? $lastName : null,
        'full_name' => $fullName !== '' ? $fullName : null,
        'job_title' => $jobTitle !== '' ? $jobTitle : null,
        'company' => $company !== '' ? $company : null,
        'phone' => $phone !== '' ? $phone : null,
        'alternate_phone' => $alternatePhone !== '' ? $alternatePhone : null,
        'email' => $email !== '' ? $email : null,
        'alternate_email' => $alternateEmail !== '' ? $alternateEmail : null,
        'website' => $website !== '' ? $website : null,
        'linkedin_url' => $linkedinUrl !== '' ? $linkedinUrl : null,
        'address' => $address !== '' ? $address : null,
        'city' => $city !== '' ? $city : null,
        'state' => $state !== '' ? $state : null,
        'country' => $country !== '' ? $country : null,
        'postal_code' => $postalCode !== '' ? $postalCode : null,
        'industry' => $industry !== '' ? $industry : null,
        'lead_source' => $leadSource !== '' ? $leadSource : null,
        'date_met' => $dateMet,
        'place_met' => $placeMet !== '' ? $placeMet : null,
        'follow_up_date' => $followUpDate,
        'status' => $status,
        'original_card_image' => $originalCardImage,
        'source' => $source,
        'ocr_raw_text' => $ocrRawText
    ]);
    
    $contactId = $pdo->lastInsertId();
    
    // If optional notes are provided, save them
    if (!empty($_POST['notes'])) {
        $stmtNote = $pdo->prepare("INSERT INTO notes (contact_id, user_id, note) VALUES (:contact_id, :user_id, :note)");
        $stmtNote->execute([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'note' => trim($_POST['notes'])
        ]);
    }
    
    // If optional tags are provided, save them (comma-separated list of tags)
    if (!empty($_POST['tags'])) {
        $tagNames = array_map('trim', explode(',', $_POST['tags']));
        foreach ($tagNames as $tagName) {
            if ($tagName === '') continue;
            
            // Get or create tag
            $stmtTag = $pdo->prepare("SELECT id FROM tags WHERE user_id = :user_id AND name = :name");
            $stmtTag->execute(['user_id' => $userId, 'name' => $tagName]);
            $tagId = $stmtTag->fetchColumn();
            
            if (!$tagId) {
                $stmtNewTag = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (:user_id, :name)");
                $stmtNewTag->execute(['user_id' => $userId, 'name' => $tagName]);
                $tagId = $pdo->lastInsertId();
            }
            
            // Map tag to contact
            $stmtAttach = $pdo->prepare("INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:contact_id, :tag_id)");
            $stmtAttach->execute(['contact_id' => $contactId, 'tag_id' => $tagId]);
        }
    }
    
    // Log creation event in timeline
    if ($source === 'Business Card') {
        log_interaction($contactId, 'Scan', 'Business card scanned and parsed.');
    } else {
        log_interaction($contactId, 'Meeting', 'Contact manually created.');
    }

    if (!empty($_POST['notes'])) {
        log_interaction($contactId, 'Note', 'Initial meeting notes recorded: ' . trim($_POST['notes']));
    }

    if (!empty($_POST['tags'])) {
        log_interaction($contactId, 'Note', 'Attached tags: ' . trim($_POST['tags']));
    }

    if ($followUpDate) {
        $stmtFu = $pdo->prepare("
            INSERT INTO follow_ups (contact_id, user_id, follow_up_date, priority, status, notes) 
            VALUES (:contact_id, :user_id, :follow_up_date, 'Medium', 'Pending', 'Initial follow-up scheduled after contact registration.')
        ");
        $stmtFu->execute([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'follow_up_date' => $followUpDate
        ]);
        log_interaction($contactId, 'Follow-up', 'Initial follow-up scheduled for ' . format_date_user($followUpDate) . '.');
    }
    
    json_response(true, 'Contact saved successfully.', [
        'contact_id' => $contactId,
        'redirect' => 'contact.php?id=' . $contactId
    ], 201);
    
} catch (\PDOException $e) {
    error_log("Save Contact DB Error: " . $e->getMessage());
    json_response(false, 'An error occurred while saving the contact: ' . $e->getMessage(), [], 500);
}
