<?php
/**
 * API - Log Custom Interaction Endpoint
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

// Validate CSRF
validate_csrf();

$userId = $_SESSION['user_id'];
$contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($contactId <= 0 || empty($type) || empty($description)) {
    json_response(false, 'Missing required parameters.', [], 400);
}

// Validate type enum
$validTypes = ['Scan', 'Note', 'Call', 'WhatsApp', 'Email', 'Meeting', 'Follow-up', 'Status Change'];
if (!in_array($type, $validTypes)) {
    json_response(false, 'Invalid interaction type.', [], 400);
}

try {
    // Assert ownership: verify user owns this contact before inserting interaction
    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = :contact_id AND user_id = :user_id LIMIT 1");
    $stmt->execute(['contact_id' => $contactId, 'user_id' => $userId]);
    if (!$stmt->fetch()) {
        json_response(false, 'Unauthorized. You do not own this contact.', [], 403);
    }

    $logged = log_interaction($contactId, $type, $description);

    if ($logged) {
        json_response(true, 'Interaction logged successfully.');
    } else {
        json_response(false, 'Unable to log interaction.', [], 500);
    }

} catch (\PDOException $e) {
    error_log("Log Interaction DB Error: " . $e->getMessage());
    json_response(false, 'A database error occurred: ' . $e->getMessage(), [], 500);
}
