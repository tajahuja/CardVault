<?php
/**
 * API - Notes Management Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle GET requests (Listing notes)
if ($method === 'GET') {
    $contactId = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
    
    if ($contactId <= 0) {
        json_response(false, 'Invalid contact ID.', [], 400);
    }
    
    try {
        // Enforce ownership: only select notes if the contact belongs to the user
        $stmt = $pdo->prepare("
            SELECT n.id, n.note, n.created_at 
            FROM notes n 
            JOIN contacts c ON n.contact_id = c.id 
            WHERE n.contact_id = :contact_id AND c.user_id = :user_id 
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([
            'contact_id' => $contactId,
            'user_id' => $userId
        ]);
        $notes = $stmt->fetchAll();
        
        json_response(true, 'Notes retrieved.', ['notes' => $notes]);
    } catch (\PDOException $e) {
        error_log("Fetch Notes DB Error: " . $e->getMessage());
        json_response(false, 'Unable to retrieve notes.', [], 500);
    }
}

// Handle POST requests (Add or Delete note)
if ($method === 'POST') {
    // Validate CSRF
    validate_csrf();
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    
    if ($action === 'add') {
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';
        
        if ($contactId <= 0 || empty($note)) {
            json_response(false, 'Contact ID and note content are required.', [], 400);
        }
        
        try {
            // Verify contact ownership before inserting note
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :contact_id AND user_id = :user_id LIMIT 1");
            $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
            }
            
            // Insert note
            $stmt = $pdo->prepare("INSERT INTO notes (contact_id, user_id, note) VALUES (:contact_id, :user_id, :note)");
            $stmt->execute([
                'contact_id' => $contactId,
                'user_id' => $userId,
                'note' => $note
            ]);
            
            $noteId = $pdo->lastInsertId();
            
            // Log interaction in timeline
            log_interaction($contactId, 'Note', 'Added note: ' . (strlen($note) > 100 ? substr($note, 0, 100) . '...' : $note));
            
            json_response(true, 'Note added successfully.', [
                'note' => [
                    'id' => $noteId,
                    'note' => $note,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ], 201);
            
        } catch (\PDOException $e) {
            error_log("Add Note DB Error: " . $e->getMessage());
            json_response(false, 'Unable to add note.', [], 500);
        }
    } 
    elseif ($action === 'delete') {
        $noteId = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        
        if ($noteId <= 0) {
            json_response(false, 'Invalid note ID.', [], 400);
        }
        
        try {
            // Enforce ownership inside delete query
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                'id' => $noteId,
                'user_id' => $userId
            ]);
            
            if ($stmt->rowCount() === 0) {
                json_response(false, 'Note not found or unauthorized deletion.', [], 403);
            }
            
            json_response(true, 'Note deleted successfully.');
        } catch (\PDOException $e) {
            error_log("Delete Note DB Error: " . $e->getMessage());
            json_response(false, 'Unable to delete note.', [], 500);
        }
    } else {
        json_response(false, 'Invalid action.', [], 400);
    }
}
