<?php
/**
 * API - Follow-up Actions Management Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert login
require_login();

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF
validate_csrf();

$userId = $_SESSION['user_id'];
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Function to sync contact's next pending follow-up date
function syncContactFollowUpDate($pdo, $contactId, $userId) {
    try {
        // Query the next pending follow-up date
        $stmt = $pdo->prepare("
            SELECT follow_up_date 
            FROM follow_ups 
            WHERE contact_id = :contact_id AND user_id = :user_id AND status = 'Pending' 
            ORDER BY follow_up_date ASC 
            LIMIT 1
        ");
        $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
        $nextDate = $stmt->fetchColumn();

        // Update contacts table
        $updateStmt = $pdo->prepare("
            UPDATE contacts 
            SET follow_up_date = :next_date 
            WHERE id = :contact_id AND user_id = :user_id
        ");
        $updateStmt->execute([
            'next_date' => $nextDate ?: null,
            'contact_id' => $contactId,
            'user_id' => $userId
        ]);
    } catch (\PDOException $e) {
        error_log("Failed to sync contact follow_up_date: " . $e->getMessage());
    }
}

// ----------------------------------------------------
// Action Handler: Create Follow-up
// ----------------------------------------------------
if ($action === 'create') {
    $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
    $date = isset($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : '';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($contactId <= 0 || empty($date)) {
        json_response(false, 'Contact ID and date are required.', [], 400);
    }

    // Validate priority
    if (!in_array($priority, ['Low', 'Medium', 'High'])) {
        $priority = 'Medium';
    }

    try {
        // Check ownership
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :contact_id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
        if (!$stmt->fetch()) {
            json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
        }

        // Insert follow-up record
        $stmt = $pdo->prepare("
            INSERT INTO follow_ups (contact_id, user_id, follow_up_date, priority, status, notes) 
            VALUES (:contact_id, :user_id, :follow_up_date, :priority, 'Pending', :notes)
        ");
        $stmt->execute([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'follow_up_date' => $date,
            'priority' => $priority,
            'notes' => $notes !== '' ? $notes : null
        ]);

        syncContactFollowUpDate($pdo, $contactId, $userId);
        
        // Log to timeline
        log_interaction($contactId, 'Follow-up', "Scheduled follow-up for " . format_date_user($date) . " (Priority: $priority)." . ($notes !== '' ? " Note: $notes" : ""));

        json_response(true, 'Follow-up scheduled successfully.');

    } catch (\PDOException $e) {
        error_log("Create Follow-up DB error: " . $e->getMessage());
        json_response(false, 'Unable to create follow-up.', [], 500);
    }
}

// ----------------------------------------------------
// Action Handler: Complete Follow-up
// ----------------------------------------------------
if ($action === 'complete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $notes = isset($_POST['completion_notes']) ? trim($_POST['completion_notes']) : '';

    if ($id <= 0) {
        json_response(false, 'Invalid follow-up ID.', [], 400);
    }

    try {
        // Verify ownership and get contact_id
        $stmt = $pdo->prepare("SELECT contact_id, follow_up_date FROM follow_ups WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $followUp = $stmt->fetch();

        if (!$followUp) {
            json_response(false, 'Follow-up not found or unauthorized.', [], 404);
        }

        $contactId = $followUp['contact_id'];

        // Mark as completed
        $stmt = $pdo->prepare("
            UPDATE follow_ups 
            SET status = 'Completed', completed_at = CURRENT_TIMESTAMP, notes = COALESCE(:notes, notes) 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'notes' => $notes !== '' ? $notes : null
        ]);

        syncContactFollowUpDate($pdo, $contactId, $userId);

        // Log interaction to timeline
        log_interaction($contactId, 'Follow-up', "Follow-up completed." . ($notes !== '' ? " Outcome: $notes" : ""));

        json_response(true, 'Follow-up completed successfully.');

    } catch (\PDOException $e) {
        error_log("Complete Follow-up DB error: " . $e->getMessage());
        json_response(false, 'Unable to complete follow-up.', [], 500);
    }
}

// ----------------------------------------------------
// Action Handler: Snooze Follow-up
// ----------------------------------------------------
if ($action === 'snooze') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $days = isset($_POST['days']) ? intval($_POST['days']) : 0;
    $customDate = isset($_POST['custom_date']) ? trim($_POST['custom_date']) : '';

    if ($id <= 0) {
        json_response(false, 'Invalid follow-up ID.', [], 400);
    }

    if ($days <= 0 && empty($customDate)) {
        json_response(false, 'Specify either snooze duration or custom date.', [], 400);
    }

    try {
        // Verify ownership and get contact_id
        $stmt = $pdo->prepare("SELECT contact_id, follow_up_date FROM follow_ups WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $followUp = $stmt->fetch();

        if (!$followUp) {
            json_response(false, 'Follow-up not found or unauthorized.', [], 404);
        }

        $contactId = $followUp['contact_id'];

        // Calculate new follow-up date
        if (!empty($customDate)) {
            $newDate = $customDate;
        } else {
            $newDate = date('Y-m-d', strtotime("+$days days"));
        }

        // Update follow-up date and reset status to Pending
        $stmt = $pdo->prepare("
            UPDATE follow_ups 
            SET follow_up_date = :new_date, status = 'Pending', completed_at = NULL 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'new_date' => $newDate,
            'id' => $id,
            'user_id' => $userId
        ]);

        syncContactFollowUpDate($pdo, $contactId, $userId);

        // Log timeline interaction
        log_interaction($contactId, 'Follow-up', "Follow-up snoozed to " . format_date_user($newDate) . ".");

        json_response(true, 'Follow-up snoozed successfully.', ['new_date' => $newDate]);

    } catch (\PDOException $e) {
        error_log("Snooze Follow-up DB error: " . $e->getMessage());
        json_response(false, 'Unable to snooze follow-up.', [], 500);
    }
}

// ----------------------------------------------------
// Action Handler: Edit Follow-up
// ----------------------------------------------------
if ($action === 'edit') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $date = isset($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : '';
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($id <= 0 || empty($date)) {
        json_response(false, 'Follow-up ID and date are required.', [], 400);
    }

    try {
        // Verify ownership and get contact_id
        $stmt = $pdo->prepare("SELECT contact_id, follow_up_date FROM follow_ups WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $followUp = $stmt->fetch();

        if (!$followUp) {
            json_response(false, 'Follow-up not found or unauthorized.', [], 404);
        }

        $contactId = $followUp['contact_id'];

        // Update follow-up record
        $stmt = $pdo->prepare("
            UPDATE follow_ups 
            SET follow_up_date = :follow_up_date, priority = :priority, notes = :notes 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            'follow_up_date' => $date,
            'priority' => $priority !== '' ? $priority : 'Medium',
            'notes' => $notes !== '' ? $notes : null,
            'id' => $id,
            'user_id' => $userId
        ]);

        syncContactFollowUpDate($pdo, $contactId, $userId);

        // Log to timeline
        log_interaction($contactId, 'Follow-up', "Follow-up details updated (New date: " . format_date_user($date) . ").");

        json_response(true, 'Follow-up details updated successfully.');

    } catch (\PDOException $e) {
        error_log("Edit Follow-up DB error: " . $e->getMessage());
        json_response(false, 'Unable to update follow-up.', [], 500);
    }
}

json_response(false, 'Invalid action specified.', [], 400);
