<?php
/**
 * API - Delete Contact Endpoint
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
$contactId = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($contactId <= 0) {
    json_response(false, 'Invalid contact ID.', [], 400);
}

try {
    // Assert ownership at the database query level
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        'id' => $contactId,
        'user_id' => $userId
    ]);
    
    if ($stmt->rowCount() === 0) {
        // Double-check if the contact exists but belongs to someone else
        $checkStmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :id LIMIT 1");
        $checkStmt->execute(['id' => $contactId]);
        if ($checkStmt->fetch()) {
            json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
        } else {
            json_response(false, 'Contact not found.', [], 404);
        }
    }
    
    json_response(true, 'Contact deleted successfully.', [
        'redirect' => 'contacts.php'
    ]);
    
} catch (\PDOException $e) {
    error_log("Delete Contact DB Error: " . $e->getMessage());
    json_response(false, 'An error occurred while deleting the contact: ' . $e->getMessage(), [], 500);
}
