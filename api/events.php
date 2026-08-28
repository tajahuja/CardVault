<?php
/**
 * API - CRM Events Management Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Validate CSRF token for mutations
    validate_csrf();
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'create';
    
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $type = isset($_POST['type']) ? trim(strip_tags($_POST['type'])) : 'Meeting';
        $date = isset($_POST['date']) ? trim(strip_tags($_POST['date'])) : '';
        $location = isset($_POST['location']) ? trim(strip_tags($_POST['location'])) : '';
        $description = isset($_POST['description']) ? trim(strip_tags($_POST['description'])) : '';
        
        if (empty($name) || empty($date)) {
            json_response(false, 'Event name and date are required.', [], 400);
        }
        
        $validTypes = ['Trade Show', 'Conference', 'Meeting', 'Networking Event', 'Exhibition', 'Travel', 'Client Visit', 'Other'];
        if (!in_array($type, $validTypes)) {
            $type = 'Meeting';
        }
        
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO events (user_id, name, type, date, location, description) 
                VALUES (:user_id, :name, :type, :date, :location, :description)
            ");
            $stmtInsert->execute([
                'user_id' => $userId,
                'name' => $name,
                'type' => $type,
                'date' => $date,
                'location' => $location !== '' ? $location : null,
                'description' => $description !== '' ? $description : null
            ]);
            
            json_response(true, 'Event created successfully.', ['id' => $pdo->lastInsertId()], 201);
        } catch (\PDOException $e) {
            error_log("Create Event API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while creating the event.', [], 500);
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $type = isset($_POST['type']) ? trim(strip_tags($_POST['type'])) : '';
        $date = isset($_POST['date']) ? trim(strip_tags($_POST['date'])) : '';
        $location = isset($_POST['location']) ? trim(strip_tags($_POST['location'])) : '';
        $description = isset($_POST['description']) ? trim(strip_tags($_POST['description'])) : '';
        
        if ($id <= 0 || empty($name) || empty($date)) {
            json_response(false, 'Invalid ID or empty event name/date.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtOwn = $pdo->prepare("SELECT id FROM events WHERE id = :id AND user_id = :user_id");
            $stmtOwn->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmtOwn->fetchColumn()) {
                json_response(false, 'Event not found or unauthorized.', [], 404);
            }
            
            $stmtUpdate = $pdo->prepare("
                UPDATE events 
                SET name = :name, type = :type, date = :date, location = :location, description = :description 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmtUpdate->execute([
                'name' => $name,
                'type' => $type,
                'date' => $date,
                'location' => $location !== '' ? $location : null,
                'description' => $description !== '' ? $description : null,
                'id' => $id,
                'user_id' => $userId
            ]);
            
            json_response(true, 'Event updated successfully.');
        } catch (\PDOException $e) {
            error_log("Update Event API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while updating the event.', [], 500);
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            json_response(false, 'Invalid event ID.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtCheck = $pdo->prepare("SELECT id FROM events WHERE id = :id AND user_id = :user_id");
            $stmtCheck->execute(['id' => $id, 'user_id' => $userId]);
            if (!$stmtCheck->fetchColumn()) {
                json_response(false, 'Event not found or unauthorized.', [], 404);
            }
            
            $stmtDel = $pdo->prepare("DELETE FROM events WHERE id = :id AND user_id = :user_id");
            $stmtDel->execute(['id' => $id, 'user_id' => $userId]);
            json_response(true, 'Event deleted successfully.');
        } catch (\PDOException $e) {
            error_log("Delete Event API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while deleting the event.', [], 500);
        }
    } elseif ($action === 'add_contact') {
        $eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        
        if ($eventId <= 0 || $contactId <= 0) {
            json_response(false, 'Invalid event or contact target.', [], 400);
        }
        
        try {
            // Verify ownership of both event and contact
            $stmtE = $pdo->prepare("SELECT id FROM events WHERE id = :id AND user_id = :user_id");
            $stmtE->execute(['id' => $eventId, 'user_id' => $userId]);
            if (!$stmtE->fetchColumn()) {
                json_response(false, 'Event not found or unauthorized.', [], 404);
            }
            
            $stmtC = $pdo->prepare("SELECT id FROM contacts WHERE id = :id AND user_id = :user_id");
            $stmtC->execute(['id' => $contactId, 'user_id' => $userId]);
            if (!$stmtC->fetchColumn()) {
                json_response(false, 'Contact not found or unauthorized.', [], 404);
            }
            
            // Check if already linked
            $stmtLink = $pdo->prepare("SELECT COUNT(*) FROM event_contacts WHERE event_id = :event_id AND contact_id = :contact_id");
            $stmtLink->execute(['event_id' => $eventId, 'contact_id' => $contactId]);
            if ($stmtLink->fetchColumn() > 0) {
                json_response(false, 'Contact is already associated with this event.', [], 400);
            }
            
            $stmtAdd = $pdo->prepare("INSERT INTO event_contacts (event_id, contact_id) VALUES (:event_id, :contact_id)");
            $stmtAdd->execute(['event_id' => $eventId, 'contact_id' => $contactId]);
            
            // Log interaction
            $stmtLog = $pdo->prepare("
                INSERT INTO interactions (contact_id, user_id, type, description) 
                VALUES (:contact_id, :user_id, 'Meeting', :desc)
            ");
            $stmtLog->execute([
                'contact_id' => $contactId,
                'user_id' => $userId,
                'desc' => "Associated with event: " . $eventId
            ]);
            
            json_response(true, 'Contact linked to event successfully.');
        } catch (\PDOException $e) {
            error_log("Add Event Contact API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while linking the contact.', [], 500);
        }
    } elseif ($action === 'remove_contact') {
        $eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        
        if ($eventId <= 0 || $contactId <= 0) {
            json_response(false, 'Invalid parameters.', [], 400);
        }
        
        try {
            // Verify ownership
            $stmtE = $pdo->prepare("SELECT id FROM events WHERE id = :id AND user_id = :user_id");
            $stmtE->execute(['id' => $eventId, 'user_id' => $userId]);
            if (!$stmtE->fetchColumn()) {
                json_response(false, 'Event not found or unauthorized.', [], 404);
            }
            
            $stmtRemove = $pdo->prepare("DELETE FROM event_contacts WHERE event_id = :event_id AND contact_id = :contact_id");
            $stmtRemove->execute(['event_id' => $eventId, 'contact_id' => $contactId]);
            json_response(true, 'Contact unlinked from event successfully.');
        } catch (\PDOException $e) {
            error_log("Remove Event Contact API Error: " . $e->getMessage());
            json_response(false, 'An error occurred while unlinking the contact.', [], 500);
        }
    }
} else {
    json_response(false, 'Request method not supported.', [], 405);
}
