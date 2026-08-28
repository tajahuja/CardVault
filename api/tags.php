<?php
/**
 * API - Tags Management Endpoint
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

// Handle GET requests (Listing tags)
if ($method === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
    
    if ($action === 'list_all') {
        try {
            // Retrieve all tags defined by the active user
            $stmt = $pdo->prepare("SELECT id, name FROM tags WHERE user_id = :user_id ORDER BY name ASC");
            $stmt->execute(['user_id' => $userId]);
            $tags = $stmt->fetchAll();
            json_response(true, 'All tags retrieved.', ['tags' => $tags]);
        } catch (\PDOException $e) {
            error_log("Fetch All Tags DB Error: " . $e->getMessage());
            json_response(false, 'Unable to retrieve tags.', [], 500);
        }
    } else {
        // Default: List tags for a specific contact
        $contactId = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : 0;
        
        if ($contactId <= 0) {
            json_response(false, 'Invalid contact ID.', [], 400);
        }
        
        try {
            // Enforce ownership: select tags matching contact and session user
            $stmt = $pdo->prepare("
                SELECT t.id, t.name 
                FROM tags t 
                JOIN contact_tags ct ON t.id = ct.tag_id 
                JOIN contacts c ON ct.contact_id = c.id 
                WHERE ct.contact_id = :contact_id AND c.user_id = :user_id 
                ORDER BY t.name ASC
            ");
            $stmt->execute([
                'contact_id' => $contactId,
                'user_id' => $userId
            ]);
            $tags = $stmt->fetchAll();
            
            json_response(true, 'Contact tags retrieved.', ['tags' => $tags]);
        } catch (\PDOException $e) {
            error_log("Fetch Contact Tags DB Error: " . $e->getMessage());
            json_response(false, 'Unable to retrieve contact tags.', [], 500);
        }
    }
}

// Handle POST requests (Attach or Detach tags)
if ($method === 'POST') {
    // Validate CSRF
    validate_csrf();
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    
    if ($action === 'attach') {
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $tagName = isset($_POST['tag_name']) ? trim($_POST['tag_name']) : '';
        
        // Clean tag name: alphanumeric, spaces, dashes (max 30 chars)
        $tagNameClean = preg_replace('/[^a-zA-Z0-9\s-]/', '', $tagName);
        $tagNameClean = substr(trim(preg_replace('/\s+/', ' ', $tagNameClean)), 0, 30);
        
        if ($contactId <= 0 || empty($tagNameClean)) {
            json_response(false, 'Contact ID and a valid tag name are required.', [], 400);
        }
        
        try {
            // Verify contact ownership before attaching
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :contact_id AND user_id = :user_id LIMIT 1");
            $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
            }
            
            $pdo->beginTransaction();
            
            // 1. Get or Create tag in tags table
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = :user_id AND name = :name LIMIT 1");
            $stmt->execute([
                'user_id' => $userId,
                'name' => $tagNameClean
            ]);
            $tagId = $stmt->fetchColumn();
            
            if (!$tagId) {
                $stmt = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (:user_id, :name)");
                $stmt->execute([
                    'user_id' => $userId,
                    'name' => $tagNameClean
                ]);
                $tagId = $pdo->lastInsertId();
            }
            
            // 2. Link in contact_tags if not already mapped
            $stmt = $pdo->prepare("SELECT contact_id FROM contact_tags WHERE contact_id = :contact_id AND tag_id = :tag_id LIMIT 1");
            $stmt->execute([
                'contact_id' => $contactId,
                'tag_id' => $tagId
            ]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO contact_tags (contact_id, tag_id) VALUES (:contact_id, :tag_id)");
                $stmt->execute([
                    'contact_id' => $contactId,
                    'tag_id' => $tagId
                ]);
            }
            
            $pdo->commit();
            
            json_response(true, 'Tag attached successfully.', [
                'tag' => [
                    'id' => $tagId,
                    'name' => $tagNameClean
                ]
            ], 201);
            
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Attach Tag DB Error: " . $e->getMessage());
            json_response(false, 'Unable to attach tag: ' . $e->getMessage(), [], 500);
        }
    } 
    elseif ($action === 'detach') {
        $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
        
        if ($contactId <= 0 || $tagId <= 0) {
            json_response(false, 'Invalid contact or tag ID.', [], 400);
        }
        
        try {
            // Verify contact ownership before detaching
            $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :contact_id AND user_id = :user_id LIMIT 1");
            $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
            if (!$stmt->fetch()) {
                json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
            }
            
            // Delete association
            $stmt = $pdo->prepare("DELETE FROM contact_tags WHERE contact_id = :contact_id AND tag_id = :tag_id");
            $stmt->execute([
                'contact_id' => $contactId,
                'tag_id' => $tagId
            ]);
            
            // Clean up orphan tags (tags that have no associations anymore)
            // (Optional garbage collection, but highly recommended to keep database clean)
            $stmt = $pdo->prepare("
                DELETE FROM tags 
                WHERE id = :tag_id 
                  AND user_id = :user_id 
                  AND id NOT IN (SELECT tag_id FROM contact_tags)
            ");
            $stmt->execute([
                'tag_id' => $tagId,
                'user_id' => $userId
            ]);
            
            json_response(true, 'Tag detached successfully.');
        } catch (\PDOException $e) {
            error_log("Detach Tag DB Error: " . $e->getMessage());
            json_response(false, 'Unable to detach tag.', [], 500);
        }
    } else {
        json_response(false, 'Invalid action.', [], 400);
    }
}
