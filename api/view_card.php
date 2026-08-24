<?php
/**
 * API - Secure Private Card Image Viewer Gateway
 * Enforces strict user ownership of contact images and session-level upload context.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];
$contactId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tempName = isset($_GET['temp_name']) ? trim($_GET['temp_name']) : '';

$filename = '';

if ($contactId > 0) {
    try {
        // Enforce ownership: query database for contact owned by this user
        $stmt = $pdo->prepare("SELECT original_card_image FROM contacts WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute([
            'id' => $contactId,
            'user_id' => $userId
        ]);
        $filename = $stmt->fetchColumn();
    } catch (\PDOException $e) {
        error_log("View card DB error: " . $e->getMessage());
        http_response_code(500);
        die("Database error.");
    }
} elseif ($tempName !== '') {
    // Sanitize filename to prevent directory traversal
    $tempNameClean = basename($tempName);
    
    // Check if the filename exists in the user's session of uploaded files
    $uploadedCards = $_SESSION['uploaded_cards'] ?? [];
    if (in_array($tempNameClean, $uploadedCards)) {
        $filename = $tempNameClean;
    } else {
        http_response_code(403);
        die("Unauthorized access to temporary resource.");
    }
}

if (empty($filename)) {
    http_response_code(404);
    die("Image not found or access unauthorized.");
}

// Build absolute filepath
$filePath = dirname(__DIR__) . '/uploads/business_cards/' . basename($filename);

if (!file_exists($filePath)) {
    http_response_code(404);
    die("Image file does not exist on disk.");
}

// Get file mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Force allow only standard image types
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    die("Unsupported file type.");
}

// Clear output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Serve file with cache controls
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=86400');
readfile($filePath);
exit;
