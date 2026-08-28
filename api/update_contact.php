<?php
/**
 * API - Update Contact Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF token
validate_csrf();

$userId = $_SESSION['user_id'];
$contactId = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($contactId <= 0) {
    json_response(false, 'Invalid contact ID.', [], 400);
}

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

// Validation: At least one identifying field must be present
if (empty($fullName) && empty($firstName) && empty($lastName) && empty($email) && empty($phone) && empty($company)) {
    json_response(false, 'Please provide at least a name, email, phone, or company for the contact.', [], 400);
}

// Reconstruct full name if missing
if (empty($fullName) && (!empty($firstName) || !empty($lastName))) {
    $fullName = trim($firstName . ' ' . $lastName);
} elseif (!empty($fullName) && empty($firstName) && empty($lastName)) {
    $parts = explode(' ', $fullName, 2);
    $firstName = $parts[0];
    $lastName = isset($parts[1]) ? $parts[1] : '';
}

// Validate status enum
$validStatuses = ['New', 'Contacted', 'Follow-up', 'Converted', 'Not Interested', 'Archived'];
if (!in_array($status, $validStatuses)) {
    $status = 'New';
}

// Duplicate checking for update (excluding current contact)
$ignoreDuplicate = isset($_POST['ignore_duplicate']) && $_POST['ignore_duplicate'] === '1';

if (!$ignoreDuplicate) {
    $duplicateFound = false;
    $dupReason = '';
    $existingContactId = null;

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE email = :email AND user_id = :user_id AND id != :contact_id LIMIT 1");
        $stmt->execute(['email' => $email, 'user_id' => $userId, 'contact_id' => $contactId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "Another contact with email '" . $email . "' already exists.";
        }
    }

    if (!$duplicateFound && !empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE phone = :phone AND user_id = :user_id AND id != :contact_id LIMIT 1");
        $stmt->execute(['phone' => $phone, 'user_id' => $userId, 'contact_id' => $contactId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "Another contact with phone '" . $phone . "' already exists.";
        }
    }

    if (!$duplicateFound && !empty($fullName) && !empty($company)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE full_name = :full_name AND company = :company AND user_id = :user_id AND id != :contact_id LIMIT 1");
        $stmt->execute(['full_name' => $fullName, 'company' => $company, 'user_id' => $userId, 'contact_id' => $contactId]);
        $existingContactId = $stmt->fetchColumn();
        if ($existingContactId) {
            $duplicateFound = true;
            $dupReason = "Another contact named '" . $fullName . "' at '" . $company . "' already exists.";
        }
    }

    if ($duplicateFound) {
        json_response(false, 'Duplicate detected.', [
            'duplicate' => true,
            'reason' => $dupReason,
            'existing_id' => $existingContactId
        ]);
    }
}

try {
    // B2B Company resolution
    $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
    if ($companyId <= 0 && !empty($company)) {
        // Look up by name for this user
        $stmtComp = $pdo->prepare("SELECT id FROM companies WHERE name = :name AND user_id = :user_id");
        $stmtComp->execute(['name' => $company, 'user_id' => $userId]);
        $existingCompId = $stmtComp->fetchColumn();
        
        if ($existingCompId) {
            $companyId = $existingCompId;
        } else {
            // Create new company
            $stmtNewComp = $pdo->prepare("INSERT INTO companies (user_id, name, lead_source) VALUES (:user_id, :name, :lead_source)");
            $stmtNewComp->execute([
                'user_id' => $userId,
                'name' => $company,
                'lead_source' => $leadSource
            ]);
            $companyId = $pdo->lastInsertId();
        }
    }
    $finalCompanyId = ($companyId > 0) ? $companyId : null;

    // Fetch old values to check for changes
    $stmtOld = $pdo->prepare("SELECT status, follow_up_date, industry, lead_source FROM contacts WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmtOld->execute(['id' => $contactId, 'user_id' => $userId]);
    $oldContact = $stmtOld->fetch();
    
    if (!$oldContact) {
        json_response(false, 'Contact not found or unauthorized.', [], 404);
    }

    $pdo->beginTransaction();

    // Assert ownership in query
    $sql = "UPDATE contacts SET 
                company_id = :company_id,
                first_name = :first_name, 
                last_name = :last_name, 
                full_name = :full_name, 
                job_title = :job_title, 
                company = :company, 
                phone = :phone, 
                alternate_phone = :alternate_phone, 
                email = :email, 
                alternate_email = :alternate_email, 
                website = :website, 
                linkedin_url = :linkedin_url, 
                address = :address, 
                city = :city, 
                state = :state, 
                country = :country, 
                postal_code = :postal_code, 
                industry = :industry,
                lead_source = :lead_source,
                date_met = :date_met, 
                place_met = :place_met, 
                follow_up_date = :follow_up_date, 
                status = :status
            WHERE id = :id AND user_id = :user_id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id' => $contactId,
        'user_id' => $userId,
        'company_id' => $finalCompanyId,
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
        'status' => $status
    ]);
    
    // Log timeline interactions based on changes
    $changesMade = [];
    
    if ($oldContact['status'] !== $status) {
        log_interaction($contactId, 'Status Change', "Status updated from '{$oldContact['status']}' to '{$status}'.");
        $changesMade[] = "status";
    }
    
    // Compare follow-up dates (handle null comparisons safely)
    $oldFollowUp = $oldContact['follow_up_date'] ? date('Y-m-d', strtotime($oldContact['follow_up_date'])) : null;
    $newFollowUp = $followUpDate ? date('Y-m-d', strtotime($followUpDate)) : null;
    if ($oldFollowUp !== $newFollowUp) {
        if ($newFollowUp === null) {
            // Clear pending follow-ups
            $stmtDelFu = $pdo->prepare("DELETE FROM follow_ups WHERE contact_id = :contact_id AND user_id = :user_id AND status = 'Pending'");
            $stmtDelFu->execute(['contact_id' => $contactId, 'user_id' => $userId]);
            log_interaction($contactId, 'Follow-up', 'Follow-up date cleared.');
        } else {
            // Check if there is an existing pending follow-up
            $stmtChkFu = $pdo->prepare("SELECT id FROM follow_ups WHERE contact_id = :contact_id AND user_id = :user_id AND status = 'Pending' ORDER BY follow_up_date ASC LIMIT 1");
            $stmtChkFu->execute(['contact_id' => $contactId, 'user_id' => $userId]);
            $fuId = $stmtChkFu->fetchColumn();

            if ($fuId) {
                // Reschedule it
                $stmtUpdFu = $pdo->prepare("UPDATE follow_ups SET follow_up_date = :new_date WHERE id = :id AND user_id = :user_id");
                $stmtUpdFu->execute(['new_date' => $newFollowUp, 'id' => $fuId, 'user_id' => $userId]);
            } else {
                // Create a new pending follow-up
                $stmtInsFu = $pdo->prepare("INSERT INTO follow_ups (contact_id, user_id, follow_up_date, priority, status, notes) VALUES (:contact_id, :user_id, :new_date, 'Medium', 'Pending', 'Follow-up scheduled from profile update.')");
                $stmtInsFu->execute(['contact_id' => $contactId, 'user_id' => $userId, 'new_date' => $newFollowUp]);
            }
            log_interaction($contactId, 'Follow-up', "Follow-up rescheduled to " . format_date_user($newFollowUp) . ".");
        }
        $changesMade[] = "follow-up";
    }
    
    // Log a general profile update if other edits occurred
    if (empty($changesMade)) {
        log_interaction($contactId, 'Note', 'Contact profile updated.');
    }
    
    $pdo->commit();

    json_response(true, 'Contact updated successfully.', [
        'contact_id' => $contactId,
        'redirect' => 'contact.php?id=' . $contactId
    ]);
    
} catch (\PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update Contact DB Error: " . $e->getMessage());
    json_response(false, 'An error occurred while updating the contact: ' . $e->getMessage(), [], 500);
}
